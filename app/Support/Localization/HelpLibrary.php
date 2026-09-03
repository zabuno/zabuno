<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * Yardım makaleleri — `docs/89` (P1-01 üçüncü ölçüt).
 *
 * BELGE, ARAYÜZ ETİKETİ DEĞİL.
 *
 * Bu makale 40'tan fazla cümle taşıyor. Cümle başına katalog anahtarı
 * makaleler için yanlış şekildir: çevirmen bağlamı göremez, bir paragrafı
 * ikiye bölmek anahtar listesini bozar ve gözden geçiren metni bir bütün
 * olarak okuyamaz. Makaleler DİLE GÖRE DOSYA olarak yaşar — her dokümantasyon
 * sitesinin yaptığı budur.
 *
 * Dosyalar `resources/views` DIŞINDA durur ve ayrı bir görünüm alanı olarak
 * kaydedilir. Sebep şekilsel: çevrilemez-dize sayacı arayüz şablonlarını
 * ölçer ve bir makaleyi orada saymak, ölçümü anlamsızlaştırırdı. Karşılığında
 * bir kapı geliyor — desteklenen her dilin dosyası VAR OLMAK ZORUNDA
 * (`HelpContentTest`), yani eksik bir çeviri kullanıcıya değil CI'a görünür.
 */
final class HelpLibrary
{
    /** Yardımın bugün konuştuğu diller. */
    public const SUPPORTED = ['en', 'tr'];

    private const FALLBACK = 'en';

    public static function pathFor(string $locale): string
    {
        return resource_path('help/'.$locale.'/first-15-minutes.blade.php');
    }

    /**
     * Okuyucunun diline en yakın makale.
     *
     * Dosya yoksa yedeğe düşer; ama bu durum bir kapıyla ZATEN engellenmiş
     * olmalı — burada düşmek, üretimde beyaz ekran göstermemek içindir.
     */
    public static function viewFor(?string $preferred): string
    {
        $locale = in_array((string) $preferred, self::SUPPORTED, true)
            ? (string) $preferred
            : self::FALLBACK;

        if (! is_file(self::pathFor($locale))) {
            $locale = self::FALLBACK;
        }

        return 'help::'.$locale.'.first-15-minutes';
    }

    public static function localeFor(?string $preferred): string
    {
        return in_array((string) $preferred, self::SUPPORTED, true)
            ? (string) $preferred
            : self::FALLBACK;
    }
}
