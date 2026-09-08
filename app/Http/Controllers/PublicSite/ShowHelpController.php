<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\Localization\HelpLibrary;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "İlk 15 dakika" — `docs/89` (P1-01).
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
            MAKALENİN DİLİ, ARAYÜZÜN DİLİ DEĞİLDİR (FF-249).

            `HelpLibrary::SUPPORTED` bilerek `i18n.shipped_locales`ten
            ayrıdır ve bu bir tutarsızlık değil, iki ayrı sorunun iki ayrı
            cevabıdır. Katalog bir dili ancak TAM olduğunda sunabilir, çünkü
            yarım bir katalog aynı ekranda iki dil gösterir. Bir makale ise
            bütün hâlinde YAZILMIŞTIR: `resources/help/tr/first-15-minutes`
            baştan sona Türkçedir ve dosyanın varlığı bir kapıyla zorlanıyor
            (`HelpContentTest`). Türkçe okuyan birine Türkçe yazılmış makaleyi
            vermemek, var olan bir belgeyi saklamak olurdu.

            Bu yüzden makale kendi dilinde gelir ve belge `lang="tr"` ilan
            eder — doğrusu budur; çevresindeki kabuk ise sunulan dilde çizilir
            ve kendi `lang`ini yazar (`PageLanguage::chromeAttributes()`).
        */
        $locale = HelpLibrary::localeFor($request->getPreferredLanguage(HelpLibrary::SUPPORTED));

        /*
            Kabuk verisi TEK yerden gelir (`SiteShell`): metin kataloğu,
            kanonik adres, çıpa öneki, ölçüm kimliği ve gezinti. Her
            denetleyici bu diziyi elle kurarken biri bir alanı unutuyordu.

            Masterpage metni de MAKALENİN dilinde (`docs/100` MP-03): yardım
            makalesi Türkçe geldiyse üst çubuk da Türkçe okunmalı.
        */
        return view('public.help', $this->shell->context($request, 'help', '/help', $locale) + [
            'helpView' => HelpLibrary::viewFor($locale),
            'helpLocale' => $locale,
        ]);
    }
}
