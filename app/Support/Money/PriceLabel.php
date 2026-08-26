<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Domain\Money\MoneyFormatter;
use App\Support\Localization\DocumentLocale;
use InvalidArgumentException;

/**
 * Yayınlanan menüde fiyatın sunum sarmalayıcısı — CORE-12.
 *
 * Alan katmanı bilinmeyen bir para birimini reddeder ve bu doğrudur. Ama
 * yayınlanan menü, kimlik doğrulaması olmayan ve müşterinin telefonunda açılan
 * bir sayfadır: orada bir istisna, beyaz ekran demektir.
 *
 * Bu yüzden burada karar şudur: para birimi çözülemiyorsa fiyat GÖSTERİLMEZ
 * (`null` döner), sayfa ayakta kalır. Eksik bir fiyat görünür biçimde eksiktir
 * ve müşteri sorar; yanlış bir fiyat ise restoranın veremeyeceği bir söz olur.
 */
final class PriceLabel
{
    public static function for(int $minorAmount, string $currencyCode, ?string $locale = null): ?string
    {
        try {
            return MoneyFormatter::format($minorAmount, $currencyCode, $locale ?? DocumentLocale::tag());
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
