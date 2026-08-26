<?php

declare(strict_types=1);

namespace App\Application\Ledger\UseCase;

use App\Application\Ledger\Port\LedgerPort;

/**
 * Bir workspace'in defterini okunur hâle getirir — CORE-12.
 *
 * Yalnız okuma yapar; defterin değişmezliği burada da korunur.
 */
final class ShowWorkspaceLedger
{
    public function __construct(private readonly LedgerPort $ledger) {}

    /**
     * @return array{
     *     entries: list<array<string, mixed>>,
     *     balances: array<string, int>,
     *     currency: string|null
     * }
     */
    public function forWorkspace(int $workspaceId): array
    {
        $rows = $this->ledger->entriesFor($workspaceId);

        $balances = [];
        $currency = null;

        foreach ($rows as $row) {
            $currency ??= (string) $row['currency_code'];
            $amount = (int) $row['amount_minor'];

            $debit = (string) $row['debit_account'];
            $credit = (string) $row['credit_account'];

            $balances[$debit] = ($balances[$debit] ?? 0) + $amount;
            $balances[$credit] = ($balances[$credit] ?? 0) - $amount;
        }

        ksort($balances);

        return [
            'entries' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'reference' => (string) $row['reference'],
                'debitAccount' => (string) $row['debit_account'],
                'creditAccount' => (string) $row['credit_account'],
                'amountMinor' => (int) $row['amount_minor'],
                'currencyCode' => (string) $row['currency_code'],
                'description' => $row['description'] !== null ? (string) $row['description'] : null,
                'occurredAt' => (string) $row['occurred_at'],
            ], $rows),
            'balances' => $balances,
            'currency' => $currency,
        ];
    }
}
