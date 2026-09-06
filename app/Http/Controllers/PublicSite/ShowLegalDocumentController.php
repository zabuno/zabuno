<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Domain\Legal\CompanyProfile;
use App\Domain\Legal\LegalReview;
use App\Http\Controllers\Controller;
use App\Support\Analytics\MeasurementConsent;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Yasal belgelerin TEK denetleyicisi — FF-198 (`docs/107` Faz 1.2).
 *
 * Sekiz adres, tek şablon, tek denetleyici: belge anahtarı adresin
 * kendisidir (`/terms` → `terms`). Sayfa başına denetleyici yazmak, sekiz
 * kopya üretmek olurdu; yeni bir belge eklemek artık kütüphaneye bir kayıt
 * ve rotaya bir satırdır.
 *
 * OTURUM İSTEMEZ ve İNDEKSLENİR: bir sözleşme, kaydolmadan okunabilmeli.
 */
final class ShowLegalDocumentController extends Controller
{
    public function __construct(
        private readonly LegalLibraryPort $library,
        private readonly SiteShell $shell,
    ) {}

    public function __invoke(Request $request): View
    {
        $key = trim($request->getPathInfo(), '/');
        $document = $this->library->find($key);

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
        $shared = $this->shell->context($request, 'legal_'.str_replace('-', '_', $key), '/'.$key);

        return view('public.legal', $shared + [
            // Yer tutucular şirket olgularıyla dolar; girilmemiş olan
            // "not yet provided" olur — uydurulmaz (`CompanyProfile`).
            'document' => $document->withCompany(CompanyProfile::fromConfig()),
            'reviewPending' => LegalReview::fromConfig()->isPending(),
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
