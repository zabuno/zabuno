<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Application\Ledger\Port\LedgerPort;
use App\Domain\Money\LedgerEntry;
use App\Domain\Money\Money;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * CORE-12 Money/Ledger — Stage 1 kabul kriterleri.
 *
 * `modules/core-money-ledger.md` iki şey ister: deterministik yuvarlama ve
 * defter değişmezliği. İkisi de burada dondurulur.
 *
 * Requirement ID'leri: LEDGER-BALANCED-01, LEDGER-IMMUTABLE-02,
 * LEDGER-DETERMINISTIC-03, LEDGER-TENANT-04.
 */
final class MoneyLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): int
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        return (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'zeytin-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function ledger(): LedgerPort
    {
        return app(LedgerPort::class);
    }

    // --- LEDGER-BALANCED-01 -----------------------------------------------
    // Çift kayıt bir muhasebe süsü değil, doğruluk kontrolüdür: para bir
    // yerden çıkıp bir yere girer. Aynı hesaba hem borç hem alacak yazmak
    // hareket yokken hareket kaydetmektir.

    public function test_an_entry_must_move_value_between_two_different_accounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LedgerEntry::record(
            1, 'INV-1', 'cash', 'cash',
            Money::fromMinorAmount(1000, 'TRY'), '2026-08-26 10:00:00',
        );
    }

    public function test_a_zero_or_negative_amount_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LedgerEntry::record(
            1, 'INV-1', 'cash', 'revenue',
            Money::fromMinorAmount(0, 'TRY'), '2026-08-26 10:00:00',
        );
    }

    public function test_recording_moves_the_balance_in_both_directions(): void
    {
        $workspaceId = $this->workspace();

        $this->ledger()->record(LedgerEntry::record(
            $workspaceId, 'INV-1', 'cash', 'revenue',
            Money::fromMinorAmount(12500, 'TRY'), '2026-08-26 10:00:00',
            'Aylık abonelik',
        ));

        self::assertSame(12500, $this->ledger()->balanceFor($workspaceId, 'cash'), 'LEDGER-BALANCED-01: borçlanan hesap artmalı.');
        self::assertSame(-12500, $this->ledger()->balanceFor($workspaceId, 'revenue'), 'LEDGER-BALANCED-01: alacaklanan hesap aynı tutarda azalmalı.');
    }

    // --- LEDGER-IMMUTABLE-02 ----------------------------------------------

    public function test_the_ledger_table_carries_no_updated_at_because_a_row_is_never_changed(): void
    {
        self::assertFalse(
            Schema::hasColumn('ledger_entries', 'updated_at'),
            'LEDGER-IMMUTABLE-02: güncellenebilen bir defter, defter değildir. Düzeltme karşı kayıtla yapılır.'
        );
    }

    public function test_the_write_port_exposes_no_update_or_delete_path(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass(LedgerPort::class))->getMethods(),
        );

        foreach (['update', 'delete', 'destroy', 'modify'] as $forbidden) {
            self::assertNotContains(
                $forbidden,
                $methods,
                "LEDGER-IMMUTABLE-02: sözleşme `{$forbidden}` sunmamalı; geçmişi düzeltilebilen defter kanıt değeri taşımaz."
            );
        }
    }

    public function test_a_correction_is_made_by_an_opposing_entry_leaving_both_visible(): void
    {
        $workspaceId = $this->workspace();
        $ledger = $this->ledger();

        $ledger->record(LedgerEntry::record(
            $workspaceId, 'INV-2', 'cash', 'revenue',
            Money::fromMinorAmount(5000, 'TRY'), '2026-08-26 10:00:00', 'Hatalı tutar',
        ));
        $ledger->record(LedgerEntry::record(
            $workspaceId, 'INV-2-REV', 'revenue', 'cash',
            Money::fromMinorAmount(5000, 'TRY'), '2026-08-26 11:00:00', 'Düzeltme',
        ));

        self::assertSame(0, $ledger->balanceFor($workspaceId, 'cash'), 'LEDGER-IMMUTABLE-02: karşı kayıt bakiyeyi sıfırlamalı.');
        self::assertCount(
            2,
            $ledger->entriesFor($workspaceId),
            'LEDGER-IMMUTABLE-02: hem hata hem düzeltmesi görünür KALMALI; silinen bir hata denetlenemez.'
        );
    }

    // --- LEDGER-DETERMINISTIC-03 ------------------------------------------

    public function test_money_is_stored_in_minor_units_so_arithmetic_never_drifts(): void
    {
        $workspaceId = $this->workspace();
        $ledger = $this->ledger();

        // Float ile 0.1 + 0.2 !== 0.3'tür. Minor birimde 10 + 20 === 30'dur.
        foreach ([10, 20] as $index => $minor) {
            $ledger->record(LedgerEntry::record(
                $workspaceId, 'FRAC-'.$index, 'cash', 'revenue',
                Money::fromMinorAmount($minor, 'TRY'), '2026-08-26 10:0'.$index.':00',
            ));
        }

        self::assertSame(
            30,
            $ledger->balanceFor($workspaceId, 'cash'),
            'LEDGER-DETERMINISTIC-03: minor birim aritmetiği kayma üretmemeli.'
        );
    }

    public function test_the_currency_of_the_recorded_amount_is_preserved(): void
    {
        $workspaceId = $this->workspace();

        $this->ledger()->record(LedgerEntry::record(
            $workspaceId, 'INV-3', 'cash', 'revenue',
            Money::fromMinorAmount(999, 'EUR'), '2026-08-26 10:00:00',
        ));

        self::assertSame('EUR', $this->ledger()->entriesFor($workspaceId)[0]['currency_code']);
    }

    // --- LEDGER-TENANT-04 -------------------------------------------------

    public function test_one_workspace_never_sees_another_workspace_balance(): void
    {
        $ledger = $this->ledger();
        $mine = $this->workspace();
        $theirs = $this->workspace();

        $ledger->record(LedgerEntry::record(
            $theirs, 'INV-4', 'cash', 'revenue',
            Money::fromMinorAmount(90000, 'TRY'), '2026-08-26 10:00:00',
        ));

        self::assertSame(0, $ledger->balanceFor($mine, 'cash'), 'LEDGER-TENANT-04: bakiye tenant-scoped olmalı.');
        self::assertSame([], $ledger->entriesFor($mine));
    }
}
