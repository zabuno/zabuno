<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Bir türevin DEĞİŞMEZ adresi (`docs/76`).
 *
 * Adres, içeriğin sağlama toplamını taşır. İki sonuç:
 *
 *   1. İçerik değişirse adres de değişir; dolayısıyla tarayıcı bu adresi
 *      süresiz saklayabilir ve misafir aynı fotoğrafı bir daha indirmez.
 *   2. Adresler sayılarak taranamaz: kimliği bilmek yetmez, sağlama
 *      toplamını da bilmek gerekir.
 */
final class RenditionUrl
{
    /** Adreste taşınan sağlama toplamı önekinin uzunluğu. */
    public const FINGERPRINT_LENGTH = 32;

    public static function for(int $renditionId, string $checksum, string $format): string
    {
        return '/media/r/'.$renditionId.'-'.substr($checksum, 0, self::FINGERPRINT_LENGTH).'.'.$format;
    }

    public static function matches(string $fingerprint, string $checksum): bool
    {
        // Sabit süreli karşılaştırma: adres kamuya açık olsa da, sağlama
        // toplamını bayt bayt tahmin ettiren bir yan kanal bırakılmaz.
        return hash_equals(substr($checksum, 0, self::FINGERPRINT_LENGTH), $fingerprint);
    }
}
