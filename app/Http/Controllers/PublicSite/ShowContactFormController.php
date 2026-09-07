<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Legal\CompanyProfile;
use App\Http\Controllers\Controller;
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
 */
final class ShowContactFormController extends Controller
{
    public function __construct(private readonly SiteShell $shell) {}

    public function __invoke(Request $request): View
    {
        // Kabuk verisi TEK yerden (`SiteShell`); sayfa yalnız kendi
        // gövdesinin ihtiyacını ekler.
        $shared = $this->shell->context($request, 'contact', '/contact');
        $company = CompanyProfile::fromConfig();

        return view('public.contact', $shared + [
            'plans' => [],
            // Girilmemiş alan ATLANMAZ, "girilmedi" diye yazılır: bir
            // iletişim sayfasında eksik olanı gizlemek, ziyaretçiye o yolun
            // hiç olmadığını değil, hiç aranmadığını düşündürürdü.
            'companyRows' => CompanyIdentity::rows($company, $shared['st']),
            'companyComplete' => $company->isComplete(),
        ]);
    }
}
