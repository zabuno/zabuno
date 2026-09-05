<?php

declare(strict_types=1);

namespace App\Support\Localization\Negotiation;

use Illuminate\Http\Request;

/**
 * ADRES ÖNEKİ — içerik ve URL dilinin kaynağı (`docs/120` §4.2).
 *
 * `/tr/urun/qr-menu/` Türkçe YAZILMIŞ bir sayfadır. Tarayıcı ayarı onu
 * İngilizceye çeviremez; çevirebilseydi Türkçe bir metin `lang="en"` ilan
 * ederdi — ekran okuyucu yanlış telaffuz eder, arama motoru yanlış dilde
 * indeksler ve `hreflang` zinciri kendi içinde çelişirdi
 * (`CORP-LOCALE-FROM-PATH-01`).
 *
 * Yalnız İLK segment okunur. `/tr/blog/en-iyi-menuler/` adresindeki `en-iyi`
 * bir dil değil bir slug'dır; ikinci segmente bakan bir çözücü onu dil
 * sanardı.
 */
final class PathPrefixResolver implements LanguageResolver
{
    public function resolve(Request $request, array $options): ?string
    {
        $segments = array_values(array_filter(
            explode('/', trim($request->getPathInfo(), '/')),
            static fn (string $segment): bool => $segment !== '',
        ));

        return $segments === [] ? null : $segments[0];
    }
}
