<?php

declare(strict_types=1);

namespace App\Domain\Money;

use NumberFormatter;

/**
 * Paranın tek biçimlendirme sahibi — CORE-12 (`docs/13` §4).
 *
 * Bu sınıf var, çünkü aynı işi yapan beş ayrı kod parçası vardı ve dördü
 * kuruşu sabit 100'e bölüyordu. Bu her para biriminde doğru değildir:
 * Japon yeninde ondalık yoktur (¥1499 = 1499 minor), Kuveyt dinarında üç
 * basamak vardır (1.499 KWD = 1499 minor). Sabit 100, yayınlanmış bir
 * menüde yanlış fiyat göstermek demektir — ve fiyat, restoranın müşterisine
 * verdiği taahhüttür.
 *
 * `ext-intl` varsa o kullanılır; yoksa paylaşımlı barındırmada çalışmaya
 * devam etmek için ayırıcılar açık bir tablodan gelir. Sessizce yanlış
 * biçimlendirmek yerine, bilinen bir sadeleşmeye düşeriz.
 */
final class MoneyFormatter
{
    /**
     * `ext-intl` yokken kullanılacak ayırıcılar. Liste tam değildir ve
     * olmadığı bilinerek yazılmıştır — desteklenen altı locale (`docs/13` §2)
     * kapsanır, tanınmayan bir locale İngilizce biçime düşer.
     *
     * @var array<string, array{decimal: string, thousands: string}>
     */
    private const SEPARATORS = [
        'tr' => ['decimal' => ',', 'thousands' => '.'],
        'de' => ['decimal' => ',', 'thousands' => '.'],
        'fr' => ['decimal' => ',', 'thousands' => ' '],
        'ru' => ['decimal' => ',', 'thousands' => ' '],
        'ar' => ['decimal' => ',', 'thousands' => '.'],
        'en' => ['decimal' => '.', 'thousands' => ','],
    ];

    /**
     * Kuruş cinsinden tutarı, para biriminin KENDİ ondalık basamağıyla
     * okunabilir bir metne çevirir.
     */
    public static function format(int $minorAmount, string $currencyCode, string $locale = 'en'): string
    {
        $digits = self::fractionDigitsFor($currencyCode);
        $major = $minorAmount / (10 ** $digits);

        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($major, $currencyCode);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        $separators = self::SEPARATORS[self::languageOf($locale)] ?? self::SEPARATORS['en'];

        return number_format($major, $digits, $separators['decimal'], $separators['thousands'])
            .' '.strtoupper($currencyCode);
    }

    /** Bir para biriminin kaç ondalık basamağı olduğu. */
    public static function fractionDigitsFor(string $currencyCode): int
    {
        return Money::fromMinorAmount(0, $currencyCode)->fractionDigits();
    }

    private static function languageOf(string $locale): string
    {
        return strtolower(substr(str_replace('_', '-', $locale), 0, 2));
    }
}
