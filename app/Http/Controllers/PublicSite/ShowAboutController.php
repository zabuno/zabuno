<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Legal\CompanyProfile;
use App\Http\Controllers\Controller;
use App\Support\Site\CompanyIdentity;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Kimden alışveriş yapıyorum?" — FF-216.
 *
 * Ölçülen boşluk: sitede satıcının kim olduğunu söyleyen bir sayfa yoktu.
 * Şirket kimliği yalnız yasal belgelerin içinde, `{company.*}` yer
 * tutucularının doldurduğu paragraflarda geçiyordu; ödeme kuruluşunun üye iş
 * yeri incelemesi ise onu ayrı bir başlıkta arıyor.
 *
 * BU SAYFA VERİTABANINA DOKUNMAZ ve kimseyi tanımaz. Bütün olgular
 * `CompanyProfile` üzerinden ortamdan gelir — `/contact` ile AYNI kaynak,
 * aynı çizim (`public.partials.company-identity`).
 *
 * ŞİRKET BİLGİSİ EKSİKKEN: sayfa açılır ama eksik olduğunu söyler ve
 * `noindex` döner. `/distance-sales` ile aynı karar
 * (`ShowLegalDocumentController`): eksik bir kimlik sessizce tamam
 * görünmemeli, ama okunamayan bir sayfa da eksik bir sayfadan iyi değil.
 */
final class ShowAboutController extends Controller
{
    public function __construct(private readonly SiteShell $shell) {}

    public function __invoke(Request $request): Response
    {
        $shared = $this->shell->context($request, 'about', '/about');
        $company = CompanyProfile::fromConfig();

        $view = view('public.about', $shared + [
            'companyRows' => CompanyIdentity::rows($company, $shared['st']),
            'companyComplete' => $company->isComplete(),
            'sellerIdentityMissing' => ! $company->isComplete(),
        ]);

        $response = response($view);

        if (! $company->isComplete()) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
