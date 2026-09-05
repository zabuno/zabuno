<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Domain\Url\CanonicalUrl;
use App\Support\Localization\SiteText;
use Illuminate\Http\Request;

/**
 * Kurumsal kabuğun VERİSİ — tek yer.
 *
 * Sahibin talebi (2026-09-05): *"masterpage shell (header footer) tüm
 * frontpages'da aynı olsun, güncellendiğinde her yer güncellensin."*
 *
 * Kabuğun tek bir Blade dosyası olması bunun YARISIDIR. Diğer yarısı burada:
 * kabuğun ihtiyaç duyduğu değerler (metin kataloğu, kanonik adres, çıpa
 * öneki, ölçüm kimliği, gezinti) tek bir yerde üretilir. Dört denetleyici
 * aynı diziyi elle kurarken biri bir alanı unutuyordu ve o sayfa sessizce
 * kabuğun eksik bir hâlini çiziyordu — kütükten çizilen sayfaların hiç
 * gezintisi olmamasının sebebi tam olarak buydu.
 *
 * Bir alan eklemek artık TEK dosyayı değiştirir.
 */
final class SiteShell
{
    public function __construct(
        private readonly SiteText $siteText,
        private readonly CanonicalUrl $canonical,
        private readonly SiteNavigation $navigation,
    ) {}

    /**
     * @param  string  $pageKey  Ölçüm kimliği; adresten TÜREMEZ (`docs/100` Faz 3).
     * @param  string|null  $canonicalPath  Kütükten çizilen sayfalarda kaydın kendi yolu.
     * @param  string|null  $pageLocale  Kurumsal sayfanın dili ADRESTEN gelir (`docs/118` E4);
     *                                   yaşayan sayfalarda dil tarayıcıyla pazarlıkla seçilir.
     * @return array<string, mixed>
     */
    public function context(
        Request $request,
        string $pageKey,
        ?string $canonicalPath = null,
        ?string $pageLocale = null,
    ): array {
        /*
            DİLİN İKİ KAYNAĞI VAR VE İKİSİ DE DOĞRU (`docs/118` E4).

            Kütükten çizilen kurumsal sayfada dil ADRESTEDİR: `/tr/…` Türkçe
            okunur, ziyaretçinin tarayıcısı ne derse desin. Bugün yayında
            olan `/pricing` gibi adreslerde dil segmenti yok, dolayısıyla
            tarayıcıyla pazarlık edilir.
        */
        $locale = SiteText::pick($pageLocale ?? $request->getPreferredLanguage(['en', 'tr']));

        $path = $canonicalPath ?? $request->getPathInfo();

        /*
            ÇIPA ÖNEKİ. Ana sayfada `#features` aynı belgedeki başlıktır;
            başka bir sayfada aynı bağlantı `/#features` olmak zorunda, yoksa
            hiçbir yere gitmez.
        */
        $anchorPrefix = trim($path, '/') === '' ? '' : '/';

        return [
            'st' => $this->siteText->all($locale),
            'canonicalUrl' => $this->canonical->for($request->getSchemeAndHttpHost(), $path),
            'anchorPrefix' => $anchorPrefix,
            'pageKey' => $pageKey,
            'coreModuleCount' => count((array) config('core-modules')),
            // `null` ise belge dili uygulamanınkine düşer (`docs/89`).
            'pageLocale' => $pageLocale,
            'nav' => $this->navigation->forShell($anchorPrefix, $locale),
        ];
    }
}
