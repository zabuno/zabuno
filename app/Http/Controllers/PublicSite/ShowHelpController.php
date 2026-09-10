<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\Localization\HelpLibrary;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Yardım makaleleri — `docs/89` (P1-01), HELP-PHOTO-01.
 *
 * TEK DENETLEYİCİ, ÇOK MAKALE: makale anahtarı adresin kendisidir (`/help`
 * → giriş makalesi, `/help/a-photo-on-a-dish` → o makale). Makale başına
 * denetleyici yazmak aynı on satırı kopyalamak olurdu; yeni bir makale artık
 * kütüphaneye bir satır ve dil başına bir dosyadır.
 *
 * OTURUM İSTEMEZ: tıkanan biri oturum açamıyor olabilir ve yardımın kapı
 * tutması, en çok ihtiyaç duyulduğu anda kapıyı kapatırdı.
 */
final class ShowHelpController extends Controller
{
    public function __construct(private readonly SiteShell $shell) {}

    public function __invoke(Request $request): View
    {
        /*
            Adres bir DOSYA YOLUNA ÇEVRİLMEZ, kütüphanede ARANIR.

            Rotalar zaten literal olarak kaydedildiği için buraya yalnız
            kayıtlı bir adres ulaşır; yine de arama burada tekrarlanıyor,
            çünkü denetleyicinin güvenliği rotanın nasıl kaydedildiğine
            BAĞLI OLMAMALI. Bilinmeyen bir slug bir görünüm arama yüzeyi
            değil, 404'tür.
        */
        $slug = HelpLibrary::slugForPath($request->getPathInfo());

        if ($slug === null) {
            abort(404);
        }

        // Her makalenin desteklenen her dilde dosyası VAR (`HelpContentTest`).
        // Honor the already negotiated interface choice, including its
        // explicit cookie, rather than letting the browser header
        // independently override the selected language.
        $locale = HelpLibrary::localeFor(app()->getLocale());

        /*
            Kabuk verisi TEK yerden gelir (`SiteShell`): metin kataloğu,
            kanonik adres, çıpa öneki, ölçüm kimliği ve gezinti. Her
            denetleyici bu diziyi elle kurarken biri bir alanı unutuyordu.

            Masterpage metni de MAKALENİN dilinde (`docs/100` MP-03): yardım
            makalesi Türkçe geldiyse üst çubuk da Türkçe okunmalı.
        */
        return view('public.help', $this->shell->context($request, 'help', HelpLibrary::pathOf($slug), $locale) + [
            'helpView' => HelpLibrary::viewFor($locale, $slug),
            'helpLocale' => $locale,
            'helpSlug' => $slug,
        ]);
    }
}
