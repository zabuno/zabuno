<?php

declare(strict_types=1);

namespace App\Application\Ledger\Port;

use App\Domain\Money\LedgerEntry;

/**
 * Defter yazma sözleşmesi — CORE-12.
 *
 * Yalnız `record` vardır. `update` ve `delete` KASTEN yoktur: bir defterin
 * tek değeri geçmişin sonradan düzeltilememesidir. Yanlış bir kayıt silinmez,
 * karşı kayıtla dengelenir — böylece hem hata hem düzeltmesi görünür kalır.
 */
interface LedgerPort
{
    public function record(LedgerEntry $entry): int;

    /** Aynı referans daha önce yazıldı mı — çift kayıt koruması. */
    public function hasReference(int $workspaceId, string $reference): bool;

    /** @return list<array<string, mixed>> */
    public function entriesFor(int $workspaceId): array;

    /** Bir workspace'in hesap bakiyesi (minor birim). */
    public function balanceFor(int $workspaceId, string $account): int;
}
