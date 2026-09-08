<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Legal\CompanyProfile;
use App\Http\Controllers\Controller;
use App\Support\Contact\ResponseCommitment;
use App\Support\Site\CompanyIdentity;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Tıkanırsam kime sorarım?" — `docs/88` (P1-01).
 *
 * Bu sorunun cevabı sayfada "henüz bağlı bir iletişim formu yok" yazıyordu.
 *
 * FF-216: sayfada bir FORM vardı ama satıcının ADRESİ, TELEFONU ve
 * E-POSTASI yoktu. Bir iletişim sayfası "bize yazın" kutusundan ibaret
 * olamaz: ödeme kuruluşunun üye iş yeri incelemesi gerçek adresi, telefonu
 * ve e-postayı arar, ve yazdığı yerin kime ait olduğunu bilmeyen bir
 * ziyaretçi zaten yazmaz. Olgular `/about` ile AYNI kaynaktan
 * (`CompanyProfile` → `.env`) ve aynı parçadan çizilir.
 * FF-201 (`docs/125`): sayfa artık gönderim sonrası REFERANSI gösterir ve
 * yalnız yapılandırılmışsa yanıt taahhüdünü yazar.
 */
final class ShowContactFormController extends Controller
{
    public function __construct(
        private readonly SiteShell $shell,
        private readonly ResponseCommitment $commitment,
    ) {}

    public function __invoke(Request $request): View
    {
        // Kabuk verisi TEK yerden (`SiteShell`); sayfa yalnız kendi
        // gövdesinin ihtiyacını ekler.
        $shell = $this->shell->context($request, 'contact', '/contact');
        $company = CompanyProfile::fromConfig();

        /*
            Taahhüt cümlesi kabuğun SEÇTİĞİ dille aynı dilde kurulur — ve o
            seçim burada TEKRARLANMAZ (FF-249). Bu satır kendi
            `getPreferredLanguage(['en', 'tr'])` pazarlığını yapıyordu; sunulan
            tek dil `en` iken Türkçe bir tarayıcıya Türkçe bir taahhüt cümlesi
            veriyor, aynı sayfadaki gezintiyi İngilizce bırakıyordu.
        */
        $locale = $shell['lang']->ui;

        $reference = $request->session()->get('contact.reference');
        $reference = is_string($reference) && $reference !== '' ? $reference : null;

        return view('public.contact', $shell + [
            'plans' => [],
            // Girilmemiş alan ATLANMAZ, "girilmedi" diye yazılır: bir
            // iletişim sayfasında eksik olanı gizlemek, ziyaretçiye o yolun
            // hiç olmadığını değil, hiç aranmadığını düşündürürdü.
            'companyRows' => CompanyIdentity::rows($company, $shell['st']),
            'companyComplete' => $company->isComplete(),
            // Referans cümlesi BURADA kurulur: şablon yer tutucu bilmez.
            'sentReference' => $reference === null
                ? null
                : str_replace('{reference}', $reference, $shell['st']['contactSentReference']),
            // `null` = yapılandırılmamış = sayfada hiçbir taahhüt yok.
            'commitment' => $this->commitment->sentence($locale),
        ]);
    }
}
