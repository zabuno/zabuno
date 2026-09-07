<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Domain\Legal\CompanyProfile;
use App\Domain\Publication\MenuPublicAddress;
use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `sitemap.xml` — arama motorunun yayınlanmış menüleri bulma yolu.
 *
 * Menü sayfalarına iç bağlantı yoktur: bir menüye ya basılı bir karekodla
 * ya da bu dosyayla ulaşılır. Sitemap olmadan "menüler indekslensin"
 * kararı kâğıt üstünde kalır.
 *
 * İki kural pazarlığa kapalıdır:
 *
 * 1. **QR token'ı ASLA girmez.** Token basılmış bir kodun anahtarıdır ve
 *    `/q/` yüzeyi bilerek hız sınırlıdır; token listesini yayımlamak,
 *    taranmasını engellemeye çalıştığımız uzayı toplu hâlde teslim etmek
 *    olurdu (`docs/38` §18).
 * 2. **Yalnız indekslenebilir menüler girer.** Sitemap ile sayfanın kendi
 *    robots sinyali aynı cevabı vermek zorundadır; çelişki gönderen bir
 *    site, arama motorunun kendi kararını vermesine davetiye çıkarır.
 */
final class ShowSitemapController extends Controller
{
    /**
     * Satıcının kimliğini SÖYLEMEK zorunda olan sayfalar (FF-216).
     *
     * `LegalDocument::$requiresSellerIdentity` ile aynı olgu; burada yol
     * olarak duruyor çünkü `/about` bir yasal belge değil, kurumsal bir
     * sayfadır ve aynı kurala tabidir.
     *
     * @var list<string>
     */
    private const SELLER_IDENTITY_PATHS = ['/about', '/distance-sales', '/pre-information', '/delivery'];

    public function __construct(
        private readonly PublicMenuAddressPort $addresses,
        private readonly CanonicalUrl $canonical,
    ) {}

    public function __invoke(Request $request): Response
    {
        $base = $request->getSchemeAndHttpHost();
        $entries = [];

        // Pazarlama sayfaları: sunucuda üretilirler ve indekslenebilirler.
        // Yasal belgelerin hepsi indekslenebilir (FF-198): bir sözleşme
        // arama motorunda bulunabilmeli.
        foreach (['/', '/about', '/terms', '/privacy', '/kvkk', '/distance-sales', '/pre-information', '/delivery', '/refund-policy', '/cookies', '/marketing-consent'] as $path) {
            /*
                EKSİK BİR SÖZLEŞME İNDEKSLENMEZ (FF-216).

                Satıcının kimliği girilmemişken mesafeli satış, ön
                bilgilendirme ve teslimat metinleri tarafını "not yet
                provided" diye gösterir. O hâlleriyle sayfa yine 200 döner ve
                okunabilir — ama arama motoruna sunulmaz. Sitemap ile
                sayfanın kendi robots sinyalinin AYNI cevabı vermesi bu
                sınıfın iki pazarlığa kapalı kuralından biriydi; burası o
                kuralın yeni hâli.
            */
            if (in_array($path, self::SELLER_IDENTITY_PATHS, true) && ! CompanyProfile::fromConfig()->isComplete()) {
                continue;
            }

            $entries[] = ['loc' => $this->canonical->for($base, $path), 'lastmod' => null];
        }

        foreach ($this->addresses->indexableMenus() as $menu) {
            $entries[] = [
                'loc' => $this->canonical->for(
                    $base,
                    // Sitemap adresi, sayfanın kanonik ilan ettiği adresin
                    // AYNISI olmalı — biri `/restoran/`, diğeri `/restaurant/`
                    // derse tarayıcı iki farklı sayfa görür ve ikisini de
                    // yarım indeksler.
                    MenuPublicAddress::fromKeyAndSlug(
                        $menu['key'],
                        $menu['slug'],
                        $menu['locale'],
                    )->path(),
                ),
                'lastmod' => $this->lastModified($menu['published_at']),
            ];
        }

        return response($this->render($entries), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            // Sitemap sık değişir ve bayat bir kopya, yeni menülerin
            // keşfini geciktirir.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** @param list<array{loc: string, lastmod: string|null}> $entries */
    private function render(array $entries): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($entries as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>';

            if ($entry['lastmod'] !== null) {
                $lines[] = '    <lastmod>'.$entry['lastmod'].'</lastmod>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    private function lastModified(string $publishedAt): ?string
    {
        if ($publishedAt === '') {
            return null;
        }

        // Uydurulmuş bir tarih, tarih olmamasından kötüdür: arama motoruna
        // değişmemiş bir sayfayı yeniden tarat demektir.
        $timestamp = strtotime($publishedAt);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }
}
