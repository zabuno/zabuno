<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\Localization\HelpLibrary;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Yardım merkezi — `docs/89` (P1-01), `docs/107` Faz 2.8 (FF-230).
 *
 * TEK DENETLEYİCİ, ÇOK MAKALE: makale anahtarı adresin kendisidir
 * (`/help` → giriş makalesi, `/help/who-can-do-what` → o makale). Makale
 * başına denetleyici yazmak dokuz kopya üretmek olurdu; yeni bir makale
 * artık kütüphaneye bir satır ve bir dosyadır.
 *
 * OTURUM İSTEMEZ: tıkanan biri oturum açamıyor olabilir ve yardımın kapı
 * tutması, en çok ihtiyaç duyulduğu anda kapıyı kapatırdı.
 */
final class ShowHelpController extends Controller
{
    public function __construct(private readonly SiteShell $shell) {}

    public function __invoke(Request $request): View
    {
        $slug = HelpLibrary::slugForPath($request->getPathInfo());

        if ($slug === null) {
            abort(404);
        }

        $preferred = $request->getPreferredLanguage(HelpLibrary::SUPPORTED);

        /*
            Sayfanın dili OKUNAN METNİN dilidir, istenen dilin değil.

            Türkçe isteyen okuyucuya henüz çevrilmemiş bir makale açılıyorsa
            sayfa `lang="en"` bildirir; aksi hâlde ekran okuyucu İngilizce
            metni Türkçe telaffuz eder (`docs/89`).
        */
        $documentLocale = HelpLibrary::documentLocaleFor($preferred, $slug);

        /*
            Kabuk verisi TEK yerden gelir (`SiteShell`): metin kataloğu,
            kanonik adres, çıpa öneki, ölçüm kimliği ve gezinti. Her
            denetleyici bu diziyi elle kurarken biri bir alanı unutuyordu.

            Masterpage metni de MAKALENİN dilinde (`docs/100` MP-03).
        */
        $context = $this->shell->context($request, 'help', HelpLibrary::pathOf($slug), $documentLocale);

        return view('public.help', $context + [
            'helpView' => HelpLibrary::viewFor($preferred, $slug),
            'helpLocale' => $documentLocale,
            'helpSlug' => $slug,
        ]);
    }
}
