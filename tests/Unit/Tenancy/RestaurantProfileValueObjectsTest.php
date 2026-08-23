<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Domain\Tenancy\ValueObject\BrandSlug;
use App\Domain\Tenancy\ValueObject\CurrencyCode;
use App\Domain\Tenancy\ValueObject\LocaleCode;
use App\Domain\Tenancy\ValueObject\StructuredAddress;
use App\Domain\Tenancy\ValueObject\TimezoneIdentifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Blind RED unit candidate for the restaurant Brand + first Location profile
 * slice (frozen scope per task instruction). None of
 * App\Domain\Tenancy\ValueObject\{BrandSlug,LocaleCode,TimezoneIdentifier,
 * CurrencyCode,StructuredAddress} exist yet, so every test below is expected
 * to fail RED with a class-not-found/autoload error, not a logic assertion
 * failure.
 *
 * Requirement IDs (frozen MASTER decisions): PROFILE-SLUG-01, PROFILE-LOCALE-01,
 * PROFILE-TZ-01, PROFILE-CURRENCY-01, PROFILE-ADDRESS-01.
 */
final class RestaurantProfileValueObjectsTest extends TestCase
{
    // --- PROFILE-SLUG-01 ----------------------------------------------------

    public function test_brand_slug_accepts_a_valid_lowercase_kebab_value(): void
    {
        $slug = BrandSlug::fromString('zeytin-restoranlari');

        self::assertSame('zeytin-restoranlari', $slug->value());
    }

    public function test_brand_slug_rejects_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BrandSlug::fromString('');
    }

    public function test_brand_slug_rejects_uppercase_and_spaces(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BrandSlug::fromString('Zeytin Restoranları');
    }

    // --- PROFILE-LOCALE-01 --------------------------------------------------

    public function test_locale_code_defaults_to_en_when_constructed_via_default(): void
    {
        $locale = LocaleCode::default();

        self::assertSame('en', $locale->value());
    }

    public function test_locale_code_accepts_a_supported_code(): void
    {
        $locale = LocaleCode::fromString('tr');

        self::assertSame('tr', $locale->value());
    }

    public function test_locale_code_rejects_an_unsupported_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocaleCode::fromString('zz');
    }

    // --- PROFILE-TZ-01 -------------------------------------------------------

    public function test_timezone_identifier_accepts_a_valid_iana_zone(): void
    {
        $tz = TimezoneIdentifier::fromString('Europe/Istanbul');

        self::assertSame('Europe/Istanbul', $tz->value());
    }

    public function test_timezone_identifier_rejects_an_invalid_zone(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimezoneIdentifier::fromString('Not/AZone');
    }

    // --- PROFILE-CURRENCY-01 --------------------------------------------------

    public function test_currency_code_accepts_a_valid_iso4217_uppercase_code(): void
    {
        $currency = CurrencyCode::fromString('TRY');

        self::assertSame('TRY', $currency->value());
    }

    public function test_currency_code_rejects_lowercase(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CurrencyCode::fromString('try');
    }

    public function test_currency_code_rejects_wrong_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CurrencyCode::fromString('TR');
    }

    // --- PROFILE-ADDRESS-01 -----------------------------------------------------

    public function test_structured_address_accepts_the_minimum_required_fields(): void
    {
        $address = StructuredAddress::fromArray([
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Bağdat Caddesi No:1',
        ]);

        self::assertSame('TR', $address->countryCode());
        self::assertSame('İstanbul', $address->city());
        self::assertSame('Bağdat Caddesi No:1', $address->addressLine1());
    }

    public function test_structured_address_rejects_missing_country_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StructuredAddress::fromArray([
            'city' => 'İstanbul',
            'address_line1' => 'Bağdat Caddesi No:1',
        ]);
    }

    public function test_structured_address_rejects_missing_city(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StructuredAddress::fromArray([
            'country_code' => 'TR',
            'address_line1' => 'Bağdat Caddesi No:1',
        ]);
    }

    public function test_structured_address_rejects_missing_address_line1(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StructuredAddress::fromArray([
            'country_code' => 'TR',
            'city' => 'İstanbul',
        ]);
    }
}
