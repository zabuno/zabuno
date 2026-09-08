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
            'agreements' => self::agreements($shared['nav']),
        ]);

        $response = response($view);

        if (! $company->isComplete()) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * Bu satışı bağlayan belgeler — ELLE YAZILMAZ (FF-240).
     *
     * ═══ NEDEN GEZİNTİDEN SÜZÜLÜYOR ═══
     *
     * Ödeme kuruluşunun üye iş yeri incelemesi "hangi sözleşme geçerli"
     * sorusunu bu sayfada arar ve cevabın bir bağlantı listesi olması
     * gerekiyor. Listeyi buraya elle yazmak, deponun aynı listeyi ikinci kez
     * tutması demekti: altbilgiye bir belge eklendiği gün burası sessizce
     * eksik kalır, bir belge kaldırıldığı gün burası 404'e bağlanırdı.
     *
     * Kaynak bu yüzden `SiteNavigation`ın yasal grubudur — altbilgiyle AYNI
     * dizi, aynı etiketler. Kapının kendisi de oradan miras alınır:
     * *altbilgideki her bağlantı 200 döner* (`docs/136` §6.3, `MP-06`).
     *
     * Grup bir gün boşalırsa liste boş döner ve sayfa o bölümü HİÇ çizmez;
     * başlığı çizilip altı boş kalan bir bölüm, olmayan bir belgenin sözünü
     * vermektir.
     *
     * @param  array<string, mixed>  $nav  `SiteShell::context()` çıktısındaki gezinti.
     * @return list<array{label: string, href: string}>
     */
    private static function agreements(array $nav): array
    {
        /** @var list<array{id: string, label: string, items: list<array{label: string, href: string, emphasis: bool}>}> $footer */
        $footer = $nav['footer'] ?? [];

        foreach ($footer as $group) {
            if ($group['id'] !== 'legal') {
                continue;
            }

            return array_map(
                static fn (array $item): array => ['label' => $item['label'], 'href' => $item['href']],
                $group['items'],
            );
        }

        return [];
    }
}
