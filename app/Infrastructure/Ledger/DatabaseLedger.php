<?php

declare(strict_types=1);

namespace App\Infrastructure\Ledger;

use App\Application\Ledger\Port\LedgerPort;
use App\Domain\Money\LedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Yalnız-ekleme defter uygulaması.
 *
 * Bu sınıfta güncelleme veya silme yolu yoktur ve olmamalıdır. Kısıt veritabanı
 * seviyesinde de tekrarlanır (`ledger_entries` tetikleyicileri değil, uygulama
 * disiplini + `updated_at` sütununun bulunmaması): bir satırın değiştirilmiş
 * olması, defterin bütün kanıt değerini yok eder.
 */
final class DatabaseLedger implements LedgerPort
{
    public function record(LedgerEntry $entry): int
    {
        return (int) DB::table('ledger_entries')->insertGetId([
            'workspace_id' => $entry->workspaceId,
            'reference' => $entry->reference,
            'debit_account' => $entry->debitAccount,
            'credit_account' => $entry->creditAccount,
            'amount_minor' => $entry->amount->minorAmount(),
            'currency_code' => $entry->amount->currencyCode(),
            'description' => $entry->description,
            'occurred_at' => $entry->occurredAt,
            'created_at' => now(),
        ]);
    }

    public function hasReference(int $workspaceId, string $reference): bool
    {
        return DB::table('ledger_entries')
            ->where('workspace_id', $workspaceId)
            ->where('reference', $reference)
            ->exists();
    }

    /** @return list<array<string, mixed>> */
    public function entriesFor(int $workspaceId): array
    {
        return DB::table('ledger_entries')
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    public function balanceFor(int $workspaceId, string $account): int
    {
        $debited = (int) DB::table('ledger_entries')
            ->where('workspace_id', $workspaceId)
            ->where('debit_account', $account)
            ->sum('amount_minor');

        $credited = (int) DB::table('ledger_entries')
            ->where('workspace_id', $workspaceId)
            ->where('credit_account', $account)
            ->sum('amount_minor');

        return $debited - $credited;
    }
}
