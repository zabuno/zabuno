<?php

declare(strict_types=1);

namespace Tests\Unit\Money;

use App\Domain\Money\Money;
use App\Domain\Money\Proration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * CORE-12 — LEDGER-DETERMINISTIC-03 (yuvarlama/proration).
 *
 * `modules/core-money-ledger.md` kabul kriteri: "Yuvarlama/proration
 * deterministik testi." Burada dondurulan sözleşme: hiçbir bölme işlemi
 * kuruş yaratmaz veya yok etmez, ve aynı girdi hep aynı çıktıyı verir.
 */
final class ProrationTest extends TestCase
{
    public function test_a_period_split_never_creates_or_loses_a_cent(): void
    {
        // 31 günlük ay, her yükseltme günü — hepsinde toplam korunmalı.
        $total = Money::fromMinorAmount(29999, 'TRY');

        for ($day = 0; $day <= 31; $day++) {
            [$used, $left] = Proration::byPeriod($total, $day, 31);

            self::assertSame(
                29999,
                $used->minorAmount() + $left->minorAmount(),
                "LEDGER-DETERMINISTIC-03: {$day}. günde parçaların toplamı tutarı korumalı."
            );
        }
    }

    public function test_the_same_input_always_produces_the_same_output(): void
    {
        $total = Money::fromMinorAmount(10000, 'TRY');

        $first = Proration::byPeriod($total, 10, 31);
        $second = Proration::byPeriod($total, 10, 31);

        self::assertTrue($first[0]->equals($second[0]));
        self::assertTrue($first[1]->equals($second[1]));
    }

    public function test_the_boundaries_are_whole_and_empty(): void
    {
        $total = Money::fromMinorAmount(10000, 'TRY');

        self::assertSame(0, Proration::byPeriod($total, 0, 30)[0]->minorAmount());
        self::assertSame(10000, Proration::byPeriod($total, 30, 30)[0]->minorAmount());
    }

    public function test_an_indivisible_amount_is_split_without_drift(): void
    {
        // 100 kuruş / 3 = 33.33… — klasik kayıp kuruş vakası.
        $slices = Proration::split(Money::fromMinorAmount(100, 'TRY'), 3);

        self::assertSame([34, 33, 33], array_map(
            static fn (Money $slice): int => $slice->minorAmount(),
            $slices,
        ));
    }

    public function test_the_currency_survives_every_split(): void
    {
        foreach (Proration::split(Money::fromMinorAmount(1000, 'EUR'), 7) as $slice) {
            self::assertSame('EUR', $slice->currencyCode());
        }
    }

    public function test_an_impossible_period_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Proration::byPeriod(Money::fromMinorAmount(1000, 'TRY'), 40, 30);
    }

    public function test_a_zero_length_period_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Proration::byPeriod(Money::fromMinorAmount(1000, 'TRY'), 0, 0);
    }
}
