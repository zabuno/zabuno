<?php

declare(strict_types=1);

namespace Tests\Unit\Money;

use App\Domain\Money\MoneyFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * CORE-12 — paranın tek biçimlendirme sahibi (`docs/13` §4).
 *
 * Bu testler bir denetim bulgusunu dondurur: 2026-08-26'da uygulamada aynı
 * işi yapan beş ayrı kod parçası vardı ve dördü kuruşu sabit 100'e bölüyordu.
 *
 * Requirement ID'leri: MONEY-FORMAT-DIGITS-01, MONEY-FORMAT-LOCALE-02,
 * MONEY-FORMAT-STRICT-03.
 */
final class MoneyFormatterTest extends TestCase
{
    // --- MONEY-FORMAT-DIGITS-01 -------------------------------------------

    public function test_a_currency_without_decimals_is_not_divided_by_a_hundred(): void
    {
        // Japon yeninde ondalık yoktur: 1499 minor = ¥1.499, ¥14,99 DEĞİL.
        $formatted = MoneyFormatter::format(1499, 'JPY', 'en');

        self::assertStringContainsString('1,499', $formatted);
        self::assertStringNotContainsString('14.99', $formatted, 'MONEY-FORMAT-DIGITS-01: sabit 100, JPY\'de yüz kat hata demektir.');
    }

    public function test_a_currency_with_three_decimals_is_divided_by_a_thousand(): void
    {
        // Kuveyt dinarında üç basamak vardır: 1499 minor = 1.499 KWD.
        self::assertStringContainsString('1.499', MoneyFormatter::format(1499, 'KWD', 'en'));
    }

    public function test_a_two_decimal_currency_keeps_its_cents(): void
    {
        self::assertStringContainsString('1,499.00', MoneyFormatter::format(149900, 'USD', 'en'));
    }

    public function test_the_fraction_digits_come_from_the_currency_itself(): void
    {
        self::assertSame(0, MoneyFormatter::fractionDigitsFor('JPY'));
        self::assertSame(2, MoneyFormatter::fractionDigitsFor('TRY'));
        self::assertSame(3, MoneyFormatter::fractionDigitsFor('KWD'));
    }

    // --- MONEY-FORMAT-LOCALE-02 -------------------------------------------

    public function test_the_separators_follow_the_reader_not_the_developer(): void
    {
        $turkish = MoneyFormatter::format(149900, 'TRY', 'tr');
        $english = MoneyFormatter::format(149900, 'USD', 'en');

        // Türkçede binlik ayırıcı nokta, ondalık virgüldür; İngilizcede tersi.
        self::assertStringContainsString('1.499,00', $turkish);
        self::assertStringContainsString('1,499.00', $english);
    }

    public function test_an_unknown_locale_falls_back_instead_of_failing(): void
    {
        self::assertNotSame('', MoneyFormatter::format(149900, 'TRY', 'zz-ZZ'));
    }

    // --- MONEY-FORMAT-STRICT-03 -------------------------------------------

    public function test_an_unknown_currency_is_refused_rather_than_guessed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Tanımadığımız bir para birimini "muhtemelen iki basamaktır" diye
        // biçimlendirmek, yanlış fiyat göstermenin en sessiz yoludur.
        MoneyFormatter::format(1499, 'XYZ', 'en');
    }
}
