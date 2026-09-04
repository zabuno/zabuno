<?php

declare(strict_types=1);

namespace App\Domain\Publication;

/**
 * İşletmenin türü ve o türün adreste yazılan hâli — `docs/105` §4.2.
 *
 * Adresin başındaki segment iki iş yapar:
 *
 * 1. **İnsana ne olduğunu söyler.** `/restoran/pasa-doner/menu/...` telefonda
 *    söylenebilir, kartvizite yazılabilir ve okuyan kişi ne olduğunu anlar.
 *    Arama motoru ve dil modeli için de bir varlık ipucudur.
 * 2. **Kiracıya kendi kökünü verir.** Kurumsal site `/tr/urun/...` altında
 *    yaşayacak; kiracı adresleri ayrı bir kökte durursa bir işletmenin slug'ı
 *    hiçbir zaman `/pricing` ya da `/help` ile çakışamaz. Bu yüzden segmentler
 *    rezerve kelimedir.
 *
 * BUGÜN TEK TÜR VAR ve bu dürüst bir ifadedir: Zabuno'nun bildiği her kiracı
 * bir yeme-içme işletmesidir. Ürün başka türleri gerçekten öğrendiğinde
 * (kafe, bar, otel) buraya yeni bir `case` eklenir ve tür markadan okunur;
 * bugün olmayan bir ayrımı veri modeline yazmak, doldurulmayacak bir sütun
 * üretmek olurdu.
 */
enum BusinessType: string
{
    case Restaurant = 'restaurant';

    /**
     * Segmentin dile göre yazılışı.
     *
     * Çevirisi tanımlı olmayan bir dilde uydurma bir kelime üretilmez;
     * uluslararası hâl kullanılır. Okunmayan bir adres, İngilizce bir
     * adresten kötüdür.
     */
    public function segment(string $locale): string
    {
        return match ([$this, $this->normaliseLocale($locale)]) {
            [self::Restaurant, 'tr'] => 'restoran',
            default => 'restaurant',
        };
    }

    /** Ürün segmentinin dile göre yazılışı: `.../urun/101-adana-kebap`. */
    public function itemSegment(string $locale): string
    {
        return match ([$this, $this->normaliseLocale($locale)]) {
            [self::Restaurant, 'tr'] => 'urun',
            default => 'dish',
        };
    }

    /** Adresten türü çözer; tanınmayan segment `null` döner. */
    public static function fromSegment(string $segment): ?self
    {
        foreach (self::cases() as $case) {
            foreach (self::LOCALES as $locale) {
                if ($case->segment($locale) === $segment) {
                    return $case;
                }
            }
        }

        return null;
    }

    /**
     * Adreste yer alabilecek BÜTÜN segmentler.
     *
     * İki yerde kullanılır: rota kısıtı (ilk segment yalnız bunlardan biri
     * olabilir, yoksa rota `/pricing`'i de yutar) ve rezerve kelime listesi.
     *
     * @return list<string>
     */
    public static function allSegments(): array
    {
        $segments = [];

        foreach (self::cases() as $case) {
            foreach (self::LOCALES as $locale) {
                $segments[] = $case->segment($locale);
                $segments[] = $case->itemSegment($locale);
            }
        }

        return array_values(array_unique($segments));
    }

    /** Rota kısıtı: `restoran|restaurant`. */
    public static function segmentPattern(): string
    {
        $typeSegments = [];

        foreach (self::cases() as $case) {
            foreach (self::LOCALES as $locale) {
                $typeSegments[] = preg_quote($case->segment($locale), '/');
            }
        }

        return implode('|', array_values(array_unique($typeSegments)));
    }

    /** Rota kısıtı: `urun|dish`. */
    public static function itemSegmentPattern(): string
    {
        $segments = [];

        foreach (self::cases() as $case) {
            foreach (self::LOCALES as $locale) {
                $segments[] = preg_quote($case->itemSegment($locale), '/');
            }
        }

        return implode('|', array_values(array_unique($segments)));
    }

    /** Segmentlerin yazıldığı diller. */
    private const array LOCALES = ['tr', 'en'];

    private function normaliseLocale(string $locale): string
    {
        // `tr-TR` da Türkçedir; bölge etiketi adres biçimini değiştirmez.
        $base = strtolower(explode('-', trim($locale))[0]);

        return in_array($base, self::LOCALES, true) ? $base : 'en';
    }
}
