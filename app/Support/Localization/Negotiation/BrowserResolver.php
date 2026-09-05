<?php

declare(strict_types=1);

namespace App\Support\Localization\Negotiation;

use App\Support\Localization\Language;
use Illuminate\Http\Request;

/**
 * TARAYICI VE CİHAZ — `Accept-Language` (`docs/120` §4.2).
 *
 * Başlık YOKSA hiçbir şey çözülmez ve bu bir eksiklik değildir: sinyalsiz
 * bir istekte karar vermek, dili başka bir yerde bilerek ayarlamış olan
 * tarafı sessizce ezmek olurdu.
 *
 * Başlık VARSA kullanıcının kendi sıralaması korunur: `de;q=0.9, tr;q=0.8`
 * diyen bir tarayıcıya Türkçe vermek, tarayıcının söylediğini duymamaktır.
 * Bu yüzden kütükteki diller o sırayla taranır — `getPreferredLanguage`
 * eşleşme bulamadığında listenin ilkini döndürür ve o davranış burada
 * İSTENMEZ: eşleşme yoksa cevap `null`'dır, sırayı bir sonraki yöntem alır.
 */
final class BrowserResolver implements LanguageResolver
{
    public function resolve(Request $request, array $options): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }

        $known = array_map(static fn (Language $language): string => $language->value, Language::cases());

        foreach ($request->getLanguages() as $tag) {
            $language = Language::tryFromTag((string) $tag);

            if ($language !== null && in_array($language->value, $known, true)) {
                return $language->value;
            }
        }

        return null;
    }
}
