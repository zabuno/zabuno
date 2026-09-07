<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Dto\Invoice;
use App\Application\Billing\Dto\InvoiceDraft;
use App\Application\Billing\Port\InvoiceRepositoryPort;
use App\Domain\Billing\InvoiceKind;
use App\Domain\Billing\InvoiceNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Faturanın kalıcılığı — numara VERİTABANI DÜZEYİNDE tahsis edilir
 * (docs/130 §K2).
 *
 * Üç güvence üst üste durur ve üçü de veritabanının kendi işidir:
 *
 *  1. **Atomik artırım.** `UPDATE … SET next_number = next_number + 1`.
 *     Sayı uygulamada hesaplanmaz; "önce en büyüğü oku, bir ekle, sonra
 *     yaz" iki isteğin arasına sığan bir boşluktur ve o boşlukta ikisi de
 *     aynı sayıyı okur. Bu `UPDATE` satır kilidini alır ve ikinci isteği
 *     kendi sırası gelene kadar bekletir.
 *  2. **Tahsis ile yazma aynı işlemde.** Belge yazılamazsa sayaç da geri
 *     sarar; seride boşluk kalmaz. (Bir PostgreSQL dizisi bunu yapamaz:
 *     geri sarılan işlemde tükettiği sayıyı geri vermez.)
 *  3. **`unique(series, number)`.** Uygulama ne yaparsa yapsın, aynı
 *     numara bir seride ikinci kez yazılamaz.
 */
final class EloquentInvoiceRepository implements InvoiceRepositoryPort
{
    private const TABLE = 'invoices';

    private const SEQUENCE_TABLE = 'invoice_number_sequences';

    public function issue(InvoiceDraft $draft): Invoice
    {
        $existing = $this->findForPayment($draft->paymentTransactionId, $draft->kind);

        if ($existing !== null) {
            return $existing;
        }

        try {
            $id = DB::transaction(function () use ($draft): int {
                $number = $this->allocateNumber($draft->series);
                $documentNumber = (new InvoiceNumber($draft->series, $number, $draft->numberPrefix))->toString();

                return (int) DB::table(self::TABLE)->insertGetId([
                    'workspace_id' => $draft->workspaceId,
                    'payment_transaction_id' => $draft->paymentTransactionId,
                    'kind' => $draft->kind->value,
                    'counter_of_invoice_id' => $draft->counterOfInvoiceId,
                    'series' => $draft->series,
                    'number' => $number,
                    'document_number' => $documentNumber,
                    'issued_at' => $draft->issuedAt,
                    'currency' => $draft->currency,
                    'amount_minor' => $draft->amountMinor,
                    'vat_rate_basis_points' => $draft->vatRateBasisPoints,
                    'net_minor' => $draft->netMinor,
                    'vat_minor' => $draft->vatMinor,
                    'plan_name' => $draft->planName,
                    'period_days' => $draft->periodDays,
                    'seller_legal_name' => $draft->seller['legal_name'],
                    'seller_address' => $draft->seller['address'],
                    'seller_mersis' => $draft->seller['mersis'],
                    'seller_tax_office' => $draft->seller['tax_office'],
                    'seller_tax_number' => $draft->seller['tax_number'],
                    'seller_email' => $draft->seller['email'],
                    'seller_phone' => $draft->seller['phone'],
                    'buyer_legal_name' => $draft->buyer['legal_name'],
                    'buyer_tax_number' => $draft->buyer['tax_number'],
                    'buyer_tax_office' => $draft->buyer['tax_office'],
                    'buyer_address' => $draft->buyer['address'],
                    'buyer_city' => $draft->buyer['city'],
                    'buyer_country' => $draft->buyer['country'],
                    'buyer_email' => $draft->buyer['email'],
                    'buyer_phone' => $draft->buyer['phone'],
                    'created_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            /*
                Yarışı KAYBETMEK bir hata değildir: aynı ödeme için başka bir
                istek belgeyi bu arada kesmiş olabilir (webhook + tarayıcı
                geri dönüşü). O zaman kesilmiş belge döner. Başka her
                kalıcılık hatası YUTULMAZ — yutulsaydı, belgesiz bir tahsilat
                sessizce belgeliymiş gibi görünürdü.
            */
            $raced = $this->findForPayment($draft->paymentTransactionId, $draft->kind);

            if ($raced !== null) {
                return $raced;
            }

            throw $exception;
        }

        /** @var object $row */
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->hydrate($row);
    }

    public function findForPayment(int $paymentTransactionId, InvoiceKind $kind): ?Invoice
    {
        $row = DB::table(self::TABLE)
            ->where('payment_transaction_id', $paymentTransactionId)
            ->where('kind', $kind->value)
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findForWorkspace(int $workspaceId, int $invoiceId): ?Invoice
    {
        $row = DB::table(self::TABLE)
            ->where('workspace_id', $workspaceId)
            ->where('id', $invoiceId)
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function listForWorkspace(int $workspaceId): array
    {
        return array_map(
            fn (object $row): Invoice => $this->hydrate($row),
            DB::table(self::TABLE)->where('workspace_id', $workspaceId)->orderByDesc('id')->get()->all(),
        );
    }

    public function countPaymentsWithoutDocument(int $workspaceId): int
    {
        return DB::table('payment_transactions')
            ->where('workspace_id', $workspaceId)
            ->whereIn('state', ['succeeded', 'refunded'])
            ->whereNotExists(static function ($query) {
                $query->select(DB::raw(1))
                    ->from(self::TABLE)
                    ->whereColumn('invoices.payment_transaction_id', 'payment_transactions.id')
                    ->where('invoices.kind', InvoiceKind::Invoice->value);
            })
            ->count();
    }

    public function planName(int $planId): ?string
    {
        $name = DB::table('plans')->where('id', $planId)->value('name');

        return is_string($name) ? $name : null;
    }

    // --- iç yardımcılar ---------------------------------------------------

    /** Çağıran ZATEN bir işlemin içindedir; sayaç belgeyle birlikte geri sarar. */
    private function allocateNumber(string $series): int
    {
        /*
            Seri satırı yoksa açılır. `insertOrIgnore` çakışmada İSTİSNA
            FIRLATMAZ (PostgreSQL'de `ON CONFLICT DO NOTHING`), yani yıl
            dönümünde iki isteğin aynı anda seriyi açmaya çalışması dış
            işlemi zehirlemez.
        */
        DB::table(self::SEQUENCE_TABLE)->insertOrIgnore([
            'series' => $series,
            'next_number' => 0,
            'created_at' => now(),
        ]);

        DB::table(self::SEQUENCE_TABLE)
            ->where('series', $series)
            ->update(['next_number' => DB::raw('next_number + 1')]);

        return (int) DB::table(self::SEQUENCE_TABLE)->where('series', $series)->value('next_number');
    }

    private function hydrate(object $row): Invoice
    {
        return new Invoice(
            id: (int) $row->id,
            workspaceId: (int) $row->workspace_id,
            paymentTransactionId: (int) $row->payment_transaction_id,
            kind: InvoiceKind::from((string) $row->kind),
            counterOfInvoiceId: $row->counter_of_invoice_id === null ? null : (int) $row->counter_of_invoice_id,
            series: (string) $row->series,
            number: (int) $row->number,
            documentNumber: (string) $row->document_number,
            issuedAt: Carbon::parse($row->issued_at)->toIso8601String(),
            currency: (string) $row->currency,
            amountMinor: (int) $row->amount_minor,
            vatRateBasisPoints: $row->vat_rate_basis_points === null ? null : (int) $row->vat_rate_basis_points,
            netMinor: $row->net_minor === null ? null : (int) $row->net_minor,
            vatMinor: $row->vat_minor === null ? null : (int) $row->vat_minor,
            planName: (string) $row->plan_name,
            periodDays: (int) $row->period_days,
            seller: [
                'legal_name' => $this->nullableString($row->seller_legal_name),
                'address' => $this->nullableString($row->seller_address),
                'mersis' => $this->nullableString($row->seller_mersis),
                'tax_office' => $this->nullableString($row->seller_tax_office),
                'tax_number' => $this->nullableString($row->seller_tax_number),
                'email' => $this->nullableString($row->seller_email),
                'phone' => $this->nullableString($row->seller_phone),
            ],
            buyer: [
                'legal_name' => (string) $row->buyer_legal_name,
                'tax_number' => (string) $row->buyer_tax_number,
                'tax_office' => (string) $row->buyer_tax_office,
                'address' => (string) $row->buyer_address,
                'city' => (string) $row->buyer_city,
                'country' => (string) $row->buyer_country,
                'email' => (string) $row->buyer_email,
                'phone' => (string) $row->buyer_phone,
            ],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
