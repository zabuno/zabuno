<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Dto\Invoice;
use App\Application\Billing\Dto\InvoiceDraft;
use App\Application\Billing\Dto\PaymentTransaction;
use App\Application\Billing\Port\BillingProfileRepositoryPort;
use App\Application\Billing\Port\EArchiveGatewayPort;
use App\Application\Billing\Port\InvoiceRepositoryPort;
use App\Domain\Billing\InvoiceKind;
use App\Domain\Billing\VatBreakdown;
use App\Domain\Legal\CompanyProfile;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;

/**
 * Fatura — tahsilatın karşılığındaki belge (docs/107 Faz 1.4, docs/130).
 *
 * Kadıköy'deki kebapçı için: kartı geçti, aboneliği 30 gün ileri gitti ve
 * ARTIK muhasebecisine verebileceği bir belgesi var — numarası sıralı,
 * içeriği değişmez, kâğıda basılabilir. İadesi çıkarsa o belge silinmez;
 * karşısına bir iade belgesi konur.
 *
 * Bu sınıfın YAPMADIĞI şey de sözleşmenin parçası: hiçbir yere e-arşiv ya
 * da e-fatura GÖNDERMEZ ve gönderilmiş gibi göstermez (`EArchiveGatewayPort`).
 */
final class ManageInvoices
{
    public function __construct(
        private readonly InvoiceRepositoryPort $invoices,
        private readonly BillingProfileRepositoryPort $profiles,
        private readonly EArchiveGatewayPort $earchive,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * Başarılı bir tahsilatın belgesini GÜVENCEYE alır.
     *
     * Tekrar tekrar çağrılabilir: zaten kesilmiş belge yeniden kesilmez.
     * Bu bilinçli — ödeme uç duruma geçtiği çağrıda değil, o ödemenin HER
     * uzlaşma denemesinde çağrılır; geç gelen bir webhook eksik kalmış bir
     * belgeyi tamamlayabilsin diye.
     */
    public function ensureForPayment(PaymentTransaction $transaction): ?Invoice
    {
        if ($transaction->state !== 'succeeded' && $transaction->state !== 'refunded') {
            return null;
        }

        $existing = $this->invoices->findForPayment($transaction->id, InvoiceKind::Invoice);

        if ($existing !== null) {
            return $existing;
        }

        return $this->issue($transaction, InvoiceKind::Invoice, null);
    }

    /**
     * İadenin karşı belgesi.
     *
     * Faturayı silmez, tutarını değiştirmez: aynı seriden sıradaki numarayı
     * alan ayrı bir belge keser ve hangi faturayı tersine çevirdiğini
     * söyler. Defterdeki ters kayıtla aynı karar (docs/123 §K6).
     */
    public function ensureCreditNoteForRefund(PaymentTransaction $transaction): ?Invoice
    {
        $existing = $this->invoices->findForPayment($transaction->id, InvoiceKind::CreditNote);

        if ($existing !== null) {
            return $existing;
        }

        $invoice = $this->ensureForPayment($transaction);

        if ($invoice === null) {
            // Faturası olmayan bir tahsilatın karşı belgesi de olamaz.
            return null;
        }

        return $this->issue($transaction, InvoiceKind::CreditNote, $invoice->id);
    }

    /** @return array<string, mixed> */
    public function listFor(int $workspaceId): array
    {
        return [
            'invoices' => array_map(
                static fn (Invoice $invoice): array => $invoice->toArray(),
                $this->invoices->listForWorkspace($workspaceId),
            ),
            /*
                Alıcısı kaydedilmemiş bir tahsilatın belgesi kesilemez
                (aşağıdaki `issue`). Bu sayı o boşluğu GÖRÜNÜR kılar;
                sıfırdan büyükse ürünün söylemesi gereken bir eksiklik var.
            */
            'payments_without_document' => $this->invoices->countPaymentsWithoutDocument($workspaceId),
            'earchive' => [
                'configured' => $this->earchive->isConfigured(),
                'state' => $this->earchive->state()->value,
            ],
        ];
    }

    public function findFor(int $workspaceId, int $invoiceId): ?Invoice
    {
        return $this->invoices->findForWorkspace($workspaceId, $invoiceId);
    }

    // --- iç yardımcılar ---------------------------------------------------

    private function issue(PaymentTransaction $transaction, InvoiceKind $kind, ?int $counterOfInvoiceId): ?Invoice
    {
        $profile = $this->profiles->find($transaction->workspaceId);

        if ($profile === null) {
            /*
                ALICI UYDURULMAZ. Fatura profili olmayan bir tahsilat için
                belge kesmek, üstünde kimin adı yazacağını bilmediğimiz bir
                mali belge üretmek olurdu. Ödeme yolu profili zaten zorunlu
                tutuyor (docs/123 §K3); buraya profilsiz düşen bir kayıt
                sessizce yutulmaz, `payments_without_document` ile sayılır.
            */
            return null;
        }

        $issuedAt = Carbon::now();
        $vat = VatBreakdown::fromGrossAmount($transaction->amountMinor, $this->vatRateBasisPoints());
        $company = CompanyProfile::fromConfig();

        $seller = [];

        foreach (CompanyProfile::FIELDS as $field) {
            $seller[$field] = $company->field($field);
        }

        return $this->invoices->issue(new InvoiceDraft(
            workspaceId: $transaction->workspaceId,
            paymentTransactionId: $transaction->id,
            kind: $kind,
            counterOfInvoiceId: $counterOfInvoiceId,
            issuedAt: $issuedAt->toDateTimeString(),
            // Seri = takvim yılı; numara her yıl 1'den başlar (docs/130 §K2).
            series: $issuedAt->format('Y'),
            currency: $transaction->currency,
            amountMinor: $transaction->amountMinor,
            vatRateBasisPoints: $vat->rateBasisPoints,
            netMinor: $vat->netMinor,
            vatMinor: $vat->vatMinor,
            planName: $this->invoices->planName($transaction->planId) ?? '',
            periodDays: $transaction->periodDays,
            seller: $seller,
            buyer: $profile->toArray(),
            numberPrefix: $this->numberPrefix(),
        ));
    }

    private function vatRateBasisPoints(): ?int
    {
        $configured = $this->config->get('billing.invoice.vat_rate_basis_points');

        if ($configured === null || $configured === '') {
            return null;
        }

        $rate = (int) $configured;

        return $rate >= 0 && $rate <= 10000 ? $rate : null;
    }

    private function numberPrefix(): ?string
    {
        $prefix = $this->config->get('billing.invoice.number_prefix');

        return is_string($prefix) && trim($prefix) !== '' ? trim($prefix) : null;
    }
}
