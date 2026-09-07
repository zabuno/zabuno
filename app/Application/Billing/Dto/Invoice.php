<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

use App\Domain\Billing\EArchiveDispatchState;
use App\Domain\Billing\InvoiceKind;
use App\Domain\Legal\CompanyProfile;

/**
 * Kesilmiş belge — okunur, yazdırılır, DEĞİŞTİRİLMEZ.
 *
 * `toArray()` panele giden biçimdir. Satıcı alanları girilmemişse `null`
 * kalır ve `seller.missing` hangi alanların eksik olduğunu ADIYLA söyler;
 * arayüz de belge de o eksikliği gösterir, doldurmaz (FF-198).
 */
final readonly class Invoice
{
    /**
     * @param  array{legal_name: ?string, address: ?string, mersis: ?string, tax_office: ?string, tax_number: ?string, email: ?string, phone: ?string}  $seller
     * @param  array{legal_name: string, tax_number: string, tax_office: string, address: string, city: string, country: string, email: string, phone: string}  $buyer
     */
    public function __construct(
        public int $id,
        public int $workspaceId,
        public int $paymentTransactionId,
        public InvoiceKind $kind,
        public ?int $counterOfInvoiceId,
        public string $series,
        public int $number,
        public string $documentNumber,
        public string $issuedAt,
        public string $currency,
        public int $amountMinor,
        public ?int $vatRateBasisPoints,
        public ?int $netMinor,
        public ?int $vatMinor,
        public string $planName,
        public int $periodDays,
        public array $seller,
        public array $buyer,
    ) {}

    /** @return list<string> Girilmemiş satıcı alanları — belgede "not yet provided". */
    public function missingSellerFields(): array
    {
        $missing = [];

        foreach (CompanyProfile::FIELDS as $field) {
            if (($this->seller[$field] ?? null) === null) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    public function sellerIsComplete(): bool
    {
        return $this->missingSellerFields() === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'document_number' => $this->documentNumber,
            'series' => $this->series,
            'number' => $this->number,
            'issued_at' => $this->issuedAt,
            'currency' => $this->currency,
            'amount_minor' => $this->amountMinor,
            'vat_rate_basis_points' => $this->vatRateBasisPoints,
            'net_minor' => $this->netMinor,
            'vat_minor' => $this->vatMinor,
            'plan_name' => $this->planName,
            'period_days' => $this->periodDays,
            'counter_of_invoice_id' => $this->counterOfInvoiceId,
            /*
                Belge kesildi, dışarıya hiçbir şey gönderilmedi ve
                gönderilmiş gibi de gösterilmez (docs/130 §K4).
            */
            'earchive_state' => EArchiveDispatchState::NotDispatched->value,
            'seller' => [
                'complete' => $this->sellerIsComplete(),
                'missing' => $this->missingSellerFields(),
                'legal_name' => $this->seller['legal_name'],
                'address' => $this->seller['address'],
                'mersis' => $this->seller['mersis'],
                'tax_office' => $this->seller['tax_office'],
                'tax_number' => $this->seller['tax_number'],
                'email' => $this->seller['email'],
                'phone' => $this->seller['phone'],
            ],
            'buyer' => $this->buyer,
            'document_url' => "/api/workspaces/{$this->workspaceId}/invoices/{$this->id}/document.pdf",
        ];
    }
}
