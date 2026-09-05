<?php

declare(strict_types=1);

namespace App\Infrastructure\Localization;

use App\Application\Localization\Port\TranslationPort;
use App\Support\Localization\PseudoLocalizer;

/**
 * Ölçüm kipini ÇEVİRİCİNİN İÇİNE koyan sarmalayıcı — `docs/121` §4.
 *
 * Dönüşümü her çağıran yerin ayrı ayrı yapması gerekseydi, unutulan her
 * çağrı ekranda "dönüşmemiş metin" olarak görünürdü — yani "kodda gömülü
 * metin" ile aynı belirtiyi verirdi ve araç yalancı pozitif üretirdi.
 * Ölçüm aracının kendisi ölçtüğü kusuru taklit edemez.
 *
 * Yer tutucular dönüşümden SONRA yerleştirilir: sarmalanan çevirici
 * `{count}` gibi işaretleri bozulmadan geri verir (`PseudoLocalizer` onları
 * korur) ve değer değiştirme adımı burada tekrar edilir. Kaynak metni önce
 * doldurup sonra dönüştürseydik, kullanıcı verisi de aksanlanırdı — bir
 * restoranın adı ekranda `Kåřåðêñïž` olurdu.
 */
final class PseudoLocalizingTranslator implements TranslationPort
{
    public function __construct(private readonly TranslationPort $inner) {}

    /** @param  array<string, string>  $params */
    public function translate(string $domain, string $key, string $locale, array $params = []): string
    {
        // Yer tutucular ÇEVİRİCİYE VERİLMEZ: önce şablon alınır, dönüştürülür,
        // sonra değerler konur. Böylece kullanıcı verisi ölçüm diline girmez.
        $template = PseudoLocalizer::transform($this->inner->translate($domain, $key, $locale));

        foreach ($params as $name => $value) {
            $template = str_replace('{'.$name.'}', $value, $template);
        }

        return $template;
    }

    public function missingCount(string $domain, string $locale): int
    {
        // Eksik sayımı ürünün gerçeğidir; ölçüm kipi onu değiştirmez.
        return $this->inner->missingCount($domain, $locale);
    }
}
