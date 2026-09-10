<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Infrastructure\Content\LocalisedContentPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dil tercihini yazar ve ziyaretçiyi DOĞRU adrese geri gönderir.
 *
 * ── İki tür adres, iki farklı doğru ───────────────────────────────────────
 *
 * Yaşayan rotalar (`/pricing`, `/help`, `/contact`) tek bir adreste yaşar ve
 * dillerini çerezden okur. Onlarda doğru davranış, ziyaretçiyi GELDİĞİ yola
 * geri göndermektir; başka bir yere göndermek, okuduğu şeyi elinden almak
 * olurdu.
 *
 * Kurumsal kütük sayfalarında ise dil ADRESİN KENDİSİNDE yaşar: metin,
 * kaydın `locale` alanından okunur (`ShowCorporatePageController`). Orada
 * aynı yola geri dönmek, ziyaretçinin seçimini sessizce yok saymaktı —
 * `/en/product/qr-menu` üzerinde Türkçeyi seçen kişi Türkçe bir kabuğun
 * içinde İngilizce metni okumaya devam ediyordu.
 *
 * Ayrımın nerede yapılacağı da bir karar: yol tanınan bir kütük satırına
 * çözülüyorsa karşılığı aranır, çözülmüyorsa yol AYNEN korunur. Böylece
 * kütükte olmayan hiçbir adres bu sınıf yüzünden başka bir yere gitmez.
 */
final class StoreSiteLanguageController extends Controller
{
    public function __construct(private readonly LocalisedContentPath $localisedPath) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $offered = array_values(array_intersect(['en', 'tr'], (array) config('i18n.shipped_locales', [])));
        $validated = $request->validate([
            'language' => ['required', 'string', Rule::in($offered)],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $destination = $this->destination(
            $this->localPath($validated['return_to'] ?? null),
            $validated['language'],
        );

        return redirect($destination)->cookie(
            (string) config('i18n.negotiation.methods.explicit.options.cookie', 'zbn_language'),
            $validated['language'],
            365 * 24 * 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'Lax',
        );
    }

    /**
     * Güvenli bulunmuş yerel yolun, seçilen dildeki hâli.
     *
     * GÜVENLİK SIRASI KORUNUR: bu adım yalnız `localPath()` çıktısını alır,
     * yani host, şema, kodlanmış eğik çizgi ve denetim karakteri elemesinden
     * SONRA çalışır. Ürettiği adres de ham girdi değil, kütükteki kanonik
     * yoldur — yani dışarıdan gelen bir metin hiçbir hâlde yönlendirmenin
     * hedefi olamaz.
     */
    private function destination(string $path, string $language): string
    {
        /*
            Sorgu ve çapa AYRILIR. Karşılık başka bir belgedir: bir dildeki
            başlığı adlandıran çapa öteki belgede hiçbir yere götürmez ve
            sorgu dizesi de o belgenin değil bu belgenin sorgusudur. Karşılık
            bulunamazsa ikisi de yerinde kalır, çünkü yol da yerinde kalır.
        */
        $onlyPath = strtok($path, '?#');

        if ($onlyPath === false || $onlyPath === '') {
            return $path;
        }

        return $this->localisedPath->counterpart($onlyPath, $language) ?? $path;
    }

    private function localPath(?string $candidate): string
    {
        if ($candidate === null || ! str_starts_with($candidate, '/')) {
            return '/';
        }

        // Reject URL-shaped and encoded slash/backslash bypasses before redirecting.
        $decoded = rawurldecode($candidate);
        if (str_starts_with($decoded, '//') || str_contains($decoded, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
            return '/';
        }

        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['host']) || isset($parts['scheme'])) {
            return '/';
        }

        return $candidate;
    }
}
