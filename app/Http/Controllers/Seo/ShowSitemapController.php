<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Application\Publication\Port\PublicMenuAddressPort;
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
    public function __construct(
        private readonly PublicMenuAddressPort $addresses,
        private readonly CanonicalUrl $canonical,
    ) {}

    public function __invoke(Request $request): Response
    {
        $base = $request->getSchemeAndHttpHost();
        $entries = [];

        // Pazarlama sayfaları: sunucuda üretilirler ve indekslenebilirler.
        foreach (['/', '/terms', '/privacy', '/kvkk'] as $path) {
            $entries[] = ['loc' => $this->canonical->for($base, $path), 'lastmod' => null];
        }

        foreach ($this->addresses->indexableMenus() as $menu) {
            $entries[] = [
                'loc' => $this->canonical->for(
                    $base,
                    MenuPublicAddress::fromKeyAndSlug($menu['key'], $menu['slug'])->path(),
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
