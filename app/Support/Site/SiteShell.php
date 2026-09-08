<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Domain\Legal\CompanyProfile;
use App\Domain\Url\CanonicalUrl;
use App\Support\Localization\PageLanguage;
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
     * @param  string|null  $contentLocale  Sayfanın YAZILMIŞ metninin dili — kurumsal
     *                                      kaydın `locale`i (`docs/118` E4) ya da yardım
     *                                      makalesinin dosya dili. Böyle bir metin yoksa
     *                                      `null` ve belge dili arayüzünkine eşittir.
     * @return array<string, mixed>
     */
    public function context(
        Request $request,
        string $pageKey,
        ?string $canonicalPath = null,
        ?string $contentLocale = null,
    ): array {
        /*
            DİLİN İKİ KAVRAMI VAR VE İKİSİ DE DOĞRU (`docs/118` E4) — ama
            kaynağı TEKTİR (`PageLanguage`).

            Burada şu satır duruyordu:

                SiteText::pick($pageLocale ?? $request->getPreferredLanguage(['en', 'tr']))

            Yani kabuk, uygulamanın zaten yaptığı pazarlığı (`NegotiateLocale`,
            `i18n.shipped_locales`) görmezden gelip elle yazılmış bir listeyle
            İKİNCİ bir pazarlık yapıyordu. Ölçülen sonuç (2026-09-08):
            `shipped_locales` yalnız `en` iken `Accept-Language: tr-TR` ile
            gelen ana sayfa `<html lang="en">` ilan edip Türkçe gövde
            basıyordu — üstelik Türkçe `site` kataloğunun 135 metni boş
            olduğu için gövdenin kendisi de iki dilliydi.

            Artık arayüz dili pazarlıktan gelir, belge dili ise sayfanın
            YAZILMIŞ metninden; ikisi tek nesnede doğduğu için ayrışamazlar.
        */
        $language = PageLanguage::for($contentLocale);

        $locale = $language->ui;

        $path = $canonicalPath ?? $request->getPathInfo();

        /*
            ÇIPA ÖNEKİ. Ana sayfada `#features` aynı belgedeki başlıktır;
            başka bir sayfada aynı bağlantı `/#features` olmak zorunda, yoksa
            hiçbir yere gitmez.
        */
        $anchorPrefix = trim($path, '/') === '' ? '' : '/';

        $siteText = $this->siteText->all($locale);

        /*
            SATICI KİMLİĞİ ARTIK KABUĞUN VERİSİDİR (FF-237).

            Altbilgide bir kurumsal kimlik satırı var ve o satır her kurumsal
            adreste çizilir; dolayısıyla kimlik artık `/about`, `/contact` ve
            yasal sayfaların değil, KABUĞUN ihtiyacı. Üç denetleyicinin
            hepsi onu ayrıca hesaplamaya devam ediyor ve bu bir tekrar DEĞİL:
            oradaki liste sayfanın GÖVDESİNDE, buradaki altbilgide, ikisi de
            aynı `CompanyIdentity::rows()` çağrısından çıkıyor. Değer tek
            kaynaktan (`CompanyProfile::fromConfig()`) okunuyor, dolayısıyla
            ikisi ayrışamaz.

            Veritabanına dokunmaz: kimlik ortamdan gelir (`CompanyIdentity`
            sınıf başlığındaki üç gerekçe), bu yüzden altbilgiye kimlik
            koymak kurumsal sayfalara bir veritabanı bağımlılığı EKLEMEZ.
        */
        $company = CompanyProfile::fromConfig();

        return [
            'st' => $siteText,
            'companyRows' => CompanyIdentity::rows($company, $siteText),
            'companyComplete' => $company->isComplete(),
            'canonicalUrl' => $this->canonical->for($request->getSchemeAndHttpHost(), $path),
            'anchorPrefix' => $anchorPrefix,
            'pageKey' => $pageKey,
            'coreModuleCount' => count((array) config('core-modules')),
            /*
                Kabuk, `<html lang>` için ikinci bir kaynağa DÜŞMEZ. Eskiden
                şablonda `$pageLocale ?? DocumentLocale::tag()` yazıyordu ve o
                `??` kusurun kendisiydi: soldaki `null` olduğunda sağdaki,
                metnin çizildiği dilden BAĞIMSIZ bir değer veriyordu. Artık
                metnin dili de belgenin dili de bu nesnenin içinde.
            */
            'lang' => $language,
            'nav' => $this->navigation->forShell($anchorPrefix, $locale),
        ];
    }
}
