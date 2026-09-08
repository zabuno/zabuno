<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Domain\Legal\CompanyProfile;
use App\Domain\Legal\LegalReview;
use App\Http\Controllers\Controller;
use App\Support\Analytics\MeasurementConsent;
use App\Support\Site\CompanyIdentity;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Yasal belgelerin TEK denetleyicisi — FF-198 (`docs/107` Faz 1.2).
 *
 * Dokuz adres, tek şablon, tek denetleyici: belge anahtarı adresin
 * kendisidir (`/terms` → `terms`). Sayfa başına denetleyici yazmak, dokuz
 * kopya üretmek olurdu; yeni bir belge eklemek artık kütüphaneye bir kayıt
 * ve rotaya bir satırdır.
 *
 * OTURUM İSTEMEZ ve İNDEKSLENİR: bir sözleşme, kaydolmadan okunabilmeli.
 *
 * ═══ EKSİK BİR SÖZLEŞME "TAMAM" GÖRÜNMEZ (FF-216) ═══
 *
 * FF-198'de ölçülen hâl bir hukuki riskti: `/distance-sales` 200 dönüyordu,
 * altbilgiden bağlantılıydı, sitemap'te ilan ediliyordu ve sözleşmenin
 * TARAFI sekiz yerde "not yet provided" yazıyordu. Yani sayfa tamam
 * görünüyor, eksik olduğu yalnız satır aralarında okunuyordu.
 *
 * Karar: satıcının kimliğini söylemek ZORUNDA olan belgeler
 * (`LegalDocument::$requiresSellerIdentity`) şirket bilgisi girilmemişken
 * (1) üstte GÖRÜNÜR bir uyarı bandı taşır ve hangi alanların girilmediğini
 * adıyla sayar, (2) `X-Robots-Tag: noindex, nofollow` ile arama motoruna
 * sunulmaz ve sitemap'ten düşer (`ShowSitemapController`).
 *
 * Sayfanın 404 ya da 503 dönmesi DEĞERLENDİRİLDİ ve reddedildi: okunamayan
 * bir sözleşme, eksik bir sözleşmeden daha kötüdür — alıcı neyi kabul
 * ettiğini hiç göremezdi ve ödeme kuruluşunun incelemesi de sayfayı hiç
 * bulamazdı. Eksik olduğunu YÜKSEK SESLE söyleyen bir sayfa dürüst, sessizce
 * tamam görünen bir sayfa değildir.
 */
final class ShowLegalDocumentController extends Controller
{
    public function __construct(
        private readonly LegalLibraryPort $library,
        private readonly SiteShell $shell,
    ) {}

    public function __invoke(Request $request): Response
    {
        $key = trim($request->getPathInfo(), '/');
        $shared = $this->shell->context($request, 'legal_'.str_replace('-', '_', $key), '/'.$key);
        $document = $this->library->find($key, $shared['lang']->ui);

        if ($document === null) {
            abort(404);
        }

        $consent = MeasurementConsent::fromRequest($request);
        $consentState = $consent->isDecided() ? ($consent->isGranted() ? 'granted' : 'denied') : 'undecided';

        /*
            ÖLÇÜM KİMLİĞİ ADRESTEN DEĞİL ANAHTARDAN: `legal_distance_sales`.
            Adres yarın `/tr/` altına taşındığında (`docs/105` §4.1) rapor
            aynı satırda kalır.
        */

        $company = CompanyProfile::fromConfig();
        $sellerIdentityMissing = $document->requiresSellerIdentity && ! $company->isComplete();

        $view = view('public.legal', $shared + [
            // Yer tutucular şirket olgularıyla dolar; girilmemiş olan
            // "not yet provided" olur — uydurulmaz (`CompanyProfile`).
            'document' => $document->withCompany($company),
            'reviewPending' => LegalReview::fromConfig()->isPending(),
            // Sözleşmenin tarafı GİRİLMEDİ: sayfa bunu üstte söyler ve
            // hangi alanların eksik olduğunu adıyla sayar.
            'sellerIdentityMissing' => $sellerIdentityMissing,
            'missingCompanyFields' => $sellerIdentityMissing
                ? CompanyIdentity::labelsFor($company->missing(), $shared['st'])
                : [],
            /*
                HESAP VERİSİ TALEBİ YALNIZ VERİ SAYFASINDA (FF-169, `docs/110`
                P0-09). Aynı bölümü her yasal sayfaya basmak, sahibe talebin
                sekiz ayrı yolu varmış izlenimi verirdi; tek bir yol var.
            */
            'showDataRequest' => $key === 'kvkk',
            'dataRequestAddress' => $this->configuredDataRequestAddress(),
            // Çerez tercihi yalnız çerez politikasında yeniden seçilir; orada
            // alttaki şerit de gizlenir — aynı soruyu iki kez sormak gürültü.
            'showConsentPreference' => $key === 'cookies',
            'hideConsentBanner' => $key === 'cookies',
            'consentState' => $consentState,
            'consentStateLabel' => $shared['st']['cookiesPreference'.ucfirst($consentState)],
            'returnTo' => '/'.$key,
        ]);

        $response = response($view);

        if ($sellerIdentityMissing) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * Talebin iletileceği adres — GİRİLMEMİŞSE `null`.
     *
     * Boş dize de `null` sayılır: yapılandırmada unutulmuş bir `=` işareti,
     * sayfada boş bir "Talebin iletileceği adres:" satırı bırakırdı ve o
     * satır sahibe adres varmış gibi görünürdü.
     */
    private function configuredDataRequestAddress(): ?string
    {
        $address = config('legal.data_request.address');

        if (! is_string($address) || trim($address) === '') {
            return null;
        }

        return trim($address);
    }
}
