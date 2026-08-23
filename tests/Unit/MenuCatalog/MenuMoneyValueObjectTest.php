<?php

declare(strict_types=1);

namespace Tests\Unit\MenuCatalog;

use App\Domain\MenuCatalog\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Blind RED unit candidate for the S1-WP03 Menu Catalog first vertical slice
 * (frozen MASTER decisions per task instruction: brick/money 0.14.x as the
 * canonical money engine; a product/menu-item price is stored as an integer
 * minor amount + ISO-4217 currency code and must respect each currency's
 * official minor-unit fraction digits — JPY=0, USD=2, KWD=3).
 *
 * App\Domain\MenuCatalog\ValueObject\Money does not exist yet (no
 * app/Domain/MenuCatalog directory, no brick/money dependency in
 * composer.json), so every test below is expected to fail RED with a
 * class-not-found/autoload error, not a logic assertion failure.
 *
 * Requirement IDs: MENU-MONEY-MINOR-01, MENU-MONEY-FRACTION-JPY-01,
 * MENU-MONEY-FRACTION-USD-01, MENU-MONEY-FRACTION-KWD-01,
 * MENU-MONEY-NEGATIVE-REJECT-01, MENU-MONEY-CURRENCY-REJECT-01,
 * MENU-MONEY-EQUALITY-01.
 */
final class MenuMoneyValueObjectTest extends TestCase
{
    // --- MENU-MONEY-MINOR-01 --------------------------------------------

    public function test_money_stores_the_exact_integer_minor_amount_and_currency_code(): void
    {
        $price = Money::fromMinorAmount(1250, 'USD');

        self::assertSame(1250, $price->minorAmount());
        self::assertSame('USD', $price->currencyCode());
    }

    // --- MENU-MONEY-FRACTION-JPY-01 -------------------------------------

    public function test_jpy_has_zero_official_fraction_digits_and_rejects_a_fractional_minor_amount(): void
    {
        $price = Money::fromMinorAmount(500, 'JPY');

        self::assertSame(0, $price->fractionDigits(), 'MONEY-FRACTION-JPY-01: JPY resmi olarak 0 küsurat basamağı taşımalı.');
        self::assertSame(500, $price->minorAmount());
    }

    // --- MENU-MONEY-FRACTION-USD-01 ---------------------------------------

    public function test_usd_has_two_official_fraction_digits(): void
    {
        $price = Money::fromMinorAmount(1999, 'USD');

        self::assertSame(2, $price->fractionDigits(), 'MONEY-FRACTION-USD-01: USD resmi olarak 2 küsurat basamağı taşımalı.');
    }

    // --- MENU-MONEY-FRACTION-KWD-01 ---------------------------------------

    public function test_kwd_has_three_official_fraction_digits(): void
    {
        $price = Money::fromMinorAmount(1500, 'KWD');

        self::assertSame(3, $price->fractionDigits(), 'MONEY-FRACTION-KWD-01: KWD resmi olarak 3 küsurat basamağı taşımalı (dinar alt birimi fils).');
    }

    // --- MENU-MONEY-NEGATIVE-REJECT-01 -------------------------------------

    public function test_money_rejects_a_negative_minor_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorAmount(-100, 'USD');
    }

    // --- MENU-MONEY-CURRENCY-REJECT-01 -------------------------------------

    public function test_money_rejects_an_unknown_or_invalid_currency_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorAmount(100, 'ZZZ');
    }

    // --- MENU-MONEY-EQUALITY-01 ---------------------------------------------

    public function test_two_money_instances_with_the_same_minor_amount_and_currency_are_equal(): void
    {
        $a = Money::fromMinorAmount(1250, 'USD');
        $b = Money::fromMinorAmount(1250, 'USD');
        $c = Money::fromMinorAmount(1250, 'EUR');

        self::assertTrue($a->equals($b), 'MONEY-EQUALITY-01: aynı minor amount + currency eşit sayılmalı.');
        self::assertFalse($a->equals($c), 'MONEY-EQUALITY-01: farklı currency ile eşit sayılmamalı.');
    }
}
