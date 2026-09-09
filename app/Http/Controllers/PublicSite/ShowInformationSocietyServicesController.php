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
 * BİLGİ TOPLUMU HİZMETLERİ — "bu satıcı kim, neyi yayımlamış, nasıl
 * ulaşırım?" (C3).
 *
 * ═══ NEDEN AYRI BİR SAYFA — ALTBİLGİDEN TAŞINDI ═══
 *
 * Şirket kimliği tablosu altbilginin İKİ sunumunda da çiziliyordu: yedi
 * satırlık bir künye, her kurumsal sayfanın en altında, katlanmış bir
 * `<details>` içinde. Aranan şey ise bir künye SATIRI değil, ADIYLA
 * bulunabilen bir sayfa: e-ticaret mevzuatı ve ticaret sicili bu başlığı
 * tam olarak bu kelimelerle arar ("Bilgi Toplumu Hizmetleri"). Altbilgide
 * katlanmış bir liste, o aramanın hiçbir sonucunu vermez.
 *
 * Taşınan şey YALNIZ ÇİZİM YERİ: değerler aynı `CompanyIdentity::rows()`
 * çağrısından, aynı `CompanyProfile::fromConfig()` kaynağından geliyor.
 * `/about` ve `/contact` de aynı tabloyu göstermeye devam ediyor.
 *
 * ═══ UYDURMA YOK ═══
 *
 * Girilmemiş bir alan ATLANMAZ, "girilmedi" diye YAZILIR ve sayfa
 * `noindex` döner — `/about` ile aynı karar (`ShowAboutController`): eksik
 * bir kimlik sessizce tamam görünmemeli, ama okunamayan bir sayfa da eksik
 * bir sayfadan iyi değildir. Sayfa hiçbir belge, sicil kaydı ya da uyum
 * iddiası ÜRETMEZ: yalnız bu depoda gerçekten var olan yasal belgelere ve
 * çalışan iletişim yoluna bağlanır.
 *
 * VERİTABANINA DOKUNMAZ, OTURUM İSTEMEZ: bu sayfayı okuyan kişinin henüz
 * bir hesabı yoktur ve kimlik ortamdan gelir (`CompanyIdentity`).
 */
final class ShowInformationSocietyServicesController extends Controller
{
    public function __construct(private readonly SiteShell $shell) {}

    public function __invoke(Request $request): Response
    {
        $shared = $this->shell->context($request, 'information-society-services', '/information-society-services');
        $company = CompanyProfile::fromConfig();

        $view = view('public.information-society-services', $shared + [
            'companyRows' => CompanyIdentity::rows($company, $shared['st']),
            'companyComplete' => $company->isComplete(),
            'sellerIdentityMissing' => ! $company->isComplete(),
            /*
                TALEBİN MUHATABI OLAN ADRES — girilmemişse `null`.

                Yasal belge sayfalarıyla AYNI karar ve aynı okuma
                (`ShowLegalDocumentController`): boş dize de "girilmedi"dir,
                çünkü yapılandırmada unutulmuş bir `=` işareti sayfada boş
                bir adres satırı bırakır ve o satır adres varmış gibi görünür.
            */
            'dataRequestAddress' => $this->configuredDataRequestAddress(),
        ]);

        $response = response($view);

        if (! $company->isComplete()) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    private function configuredDataRequestAddress(): ?string
    {
        $address = config('legal.data_request.address');

        if (! is_string($address) || trim($address) === '') {
            return null;
        }

        return trim($address);
    }
}
