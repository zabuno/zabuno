<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Domain\Tenancy\ValueObject\CurrencyCode;
use App\Domain\Tenancy\ValueObject\StructuredAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * RED correction candidate raised by independent P2 review of the Restaurant
 * Profile slice: App\Domain\Tenancy\ValueObject\CurrencyCode currently only
 * checks the ISO-4217 *shape* (`/^[A-Z]{3}$/`) and does not check the code
 * against the actual ISO-4217 currency list, so a well-formed but non-real
 * code such as 'ZZZ' is wrongly accepted today. Likewise
 * App\Domain\Tenancy\ValueObject\StructuredAddress does not validate
 * country_code against the ISO-3166-1 alpha-2 list at all — any non-empty
 * string, including a bogus 'XX', is accepted today.
 *
 * These tests are expected to fail RED against the current implementation
 * (no InvalidArgumentException thrown for 'ZZZ' / 'XX'), not from a
 * bootstrap/syntax defect in this suite.
 *
 * Requirement IDs: PROFILE-CURRENCY-ISO-01, PROFILE-ADDRESS-COUNTRY-ISO-01.
 */
final class RestaurantProfileStandardsTest extends TestCase
{
    // --- PROFILE-CURRENCY-ISO-01 --------------------------------------------

    /**
     * @return list<array{0: string}>
     */
    public static function validIso4217CurrencyProvider(): array
    {
        return [
            'Turkish lira' => ['TRY'],
            'US dollar' => ['USD'],
            'Japanese yen' => ['JPY'],
            'Kuwaiti dinar' => ['KWD'],
        ];
    }

    #[DataProvider('validIso4217CurrencyProvider')]
    public function test_currency_code_accepts_real_iso4217_codes(string $code): void
    {
        $currency = CurrencyCode::fromString($code);

        self::assertSame($code, $currency->value());
    }

    public function test_currency_code_rejects_a_well_formed_but_non_existent_iso4217_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CurrencyCode::fromString('ZZZ');
    }

    // --- PROFILE-ADDRESS-COUNTRY-ISO-01 --------------------------------------

    /**
     * @return list<array{0: string}>
     */
    public static function validIso3166CountryProvider(): array
    {
        return [
            'Turkey' => ['TR'],
            'United States' => ['US'],
            'Germany' => ['DE'],
        ];
    }

    #[DataProvider('validIso3166CountryProvider')]
    public function test_structured_address_accepts_real_iso3166_country_codes(string $countryCode): void
    {
        $address = StructuredAddress::fromArray([
            'country_code' => $countryCode,
            'city' => 'İstanbul',
            'address_line1' => 'Bağdat Caddesi No:1',
        ]);

        self::assertSame($countryCode, $address->countryCode());
    }

    public function test_structured_address_rejects_a_well_formed_but_non_existent_iso3166_country_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StructuredAddress::fromArray([
            'country_code' => 'XX',
            'city' => 'İstanbul',
            'address_line1' => 'Bağdat Caddesi No:1',
        ]);
    }
}
