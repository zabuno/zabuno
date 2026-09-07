<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\Invoice;
use App\Application\Billing\Dto\InvoiceDraft;
use App\Domain\Billing\InvoiceKind;

/**
 * Fatura yazma ve okuma sözleşmesi (docs/130).
 *
 * `update` ve `delete` KASTEN yoktur — `LedgerPort` ile aynı gerekçe.
 * Kesilmiş bir belge değiştirilmez; düzeltme karşı belgedir.
 *
 * `issue` numarayı KENDİSİ tahsis eder ve tahsisi belgenin yazılmasıyla
 * aynı işleme kapatır: çağıran bir numara veremez, çünkü uygulamada
 * üretilmiş bir numara iki eşzamanlı tahsilatta aynı olabilir.
 */
interface InvoiceRepositoryPort
{
    /**
     * Belgeyi keser ve numarayı veritabanı düzeyinde tahsis eder.
     *
     * Aynı ödeme için aynı türde bir belge zaten varsa YENİSİ KESİLMEZ:
     * var olan döner (geç gelen webhook ile tarayıcı geri dönüşü ikinci
     * belge yaratamaz).
     */
    public function issue(InvoiceDraft $draft): Invoice;

    public function findForPayment(int $paymentTransactionId, InvoiceKind $kind): ?Invoice;

    public function findForWorkspace(int $workspaceId, int $invoiceId): ?Invoice;

    /** @return list<Invoice> En yeni belge başta. */
    public function listForWorkspace(int $workspaceId): array;

    /** Belgesi olmayan başarılı tahsilat sayısı — eksiklik sessizce yutulmaz. */
    public function countPaymentsWithoutDocument(int $workspaceId): int;

    public function planName(int $planId): ?string;
}
