<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

use App\Domain\Billing\InvoiceKind;

/**
 * Kesilmek ÜZERE olan belge — numarası henüz yok.
 *
 * Numarayı taşımaması bilinçlidir: numarayı veritabanı tahsis eder ve
 * ancak belgenin yazıldığı işlemin içinde tahsis eder (docs/130 §K2).
 * Uygulamada üretilmiş bir numara taşısaydı, iki eşzamanlı tahsilat aynı
 * sayıyla buraya gelebilirdi.
 *
 * Satıcı ve alıcı alanları ANLIK KOPYADIR; belge yazıldıktan sonra kaynak
 * değişse bile belge değişmez.
 */
final readonly class InvoiceDraft
{
    /**
     * @param  array{legal_name: ?string, address: ?string, mersis: ?string, tax_office: ?string, tax_number: ?string, email: ?string, phone: ?string}  $seller
     * @param  array{legal_name: string, tax_number: string, tax_office: string, address: string, city: string, country: string, email: string, phone: string}  $buyer
     */
    public function __construct(
        public int $workspaceId,
        public int $paymentTransactionId,
        public InvoiceKind $kind,
        public ?int $counterOfInvoiceId,
        public string $issuedAt,
        public string $series,
        public string $currency,
        public int $amountMinor,
        public ?int $vatRateBasisPoints,
        public ?int $netMinor,
        public ?int $vatMinor,
        public string $planName,
        public int $periodDays,
        public array $seller,
        public array $buyer,
        public ?string $numberPrefix,
    ) {}
}
