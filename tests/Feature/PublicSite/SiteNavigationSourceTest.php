<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use App\Support\Content\SiteMapParser;
use App\Support\Site\SiteNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * NAV-REGISTRY-01…04 — gezinti YENİ SAYFA YARATMAZ.
 *
 * `docs/105` §2.2 (madde 3) ve `docs/118` §2: header, mega menü, mobil menü
 * ve altbilgi aynı canonical'a bağlanır; yayınlanmamış bir sayfa hiçbir
 * yerden iç bağlantı almaz. Kural TEK yerde yaşar:
 * `PageRenderDecision::isLinkable()`.
 *
 * Neden ölçülüyor: bir mega menü, doğası gereği "ileride olacak" sayfaları
 * listelemeye davet eder. O bağlantıların hepsi bugün 404 döner — yani
 * ziyaretçiye çalışmayan bir site gösterilir ve deponun kendi bağlantı
 * taraması kırılır. Bir bağlantının varlığı, arkasındaki sayfanın gerçekten
 * çalıştığı iddiasıdır.
 *
 * Kullanıcı yolculuğu: sahip `/tr/urun/` sayfasının içeriğini yazıp yayına
 * alır. Hiçbir Blade dosyası değişmeden, üst menüdeki "Ürün" bağlantısı o gün
 * belirir. Yazmadığı gün ise menüde hiç görünmez — ziyaretçi hiçbir zaman
 * boş bir sayfaya götürülmez.
 */
final class SiteNavigationSourceTest extends TestCase
{
    use RefreshDatabase;

    // --- NAV-REGISTRY-01 -------------------------------------------------------

    public function test_navigation_never_invents_a_page(): void
    {
        $registryPaths = array_column(
            SiteMapParser::parse((string) file_get_contents(base_path('docs/106-SITE-MAP-INPUT.md'))),
            'canonical_path'
        );

        self::assertNotSame([], $registryPaths, 'Site haritası okunamadı — ölçüm dayanaksız.');

        $routed = [];

        foreach (Route::getRoutes()->getRoutesByMethod()['GET'] ?? [] as $route) {
            $routed[] = '/'.ltrim($route->uri(), '/');
        }

        $targets = app(SiteNavigation::class)->declaredTargets();

        self::assertNotSame([], $targets, 'Gezintide hiç hedef yok — ölçüm dayanaksız.');

        foreach ($targets as $target) {
            $known = in_array($target, $registryPaths, true) || in_array($target, $routed, true);

            self::assertTrue(
                $known,
                "NAV-REGISTRY-01: gezintideki [{$target}] ne sayfa kütüğünde ne de bir rotada var. "
                .'Gezinti yeni sayfa yaratamaz; yalnız var olan canonical yollara bağlanır.'
            );
        }
    }

    // --- NAV-REGISTRY-02 -------------------------------------------------------

    public function test_an_unpublished_registry_page_is_never_linked(): void
    {
        $this->registryPage('/tr/urun/', PagePublicationStatus::Planned);

        foreach (['/', '/pricing', '/help'] as $path) {
            self::assertStringNotContainsString(
                'href="/tr/urun/"',
                $this->chrome($path),
                "NAV-REGISTRY-02: [{$path}] yayınlanmamış bir sayfaya bağlantı veriyor — o adres 404 döner."
            );
        }
    }

    public function test_a_group_with_nothing_to_show_is_not_rendered_at_all(): void
    {
        /*
            Boş bir başlık, olmayan bir bölümün sözünü verir. Kütükte hiçbir
            sayfa yayında değilken keşif grubu HİÇ çizilmemeli — başlığı da,
            kabı da.
        */
        self::assertStringNotContainsString(
            'data-nav-group="explore"',
            $this->chrome('/pricing'),
            'NAV-REGISTRY-04: bağlanabilir tek sayfası olmayan grup yine de çizilmiş.'
        );
    }

    // --- NAV-REGISTRY-03 -------------------------------------------------------

    public function test_publishing_a_registry_page_makes_it_appear_in_navigation_without_a_code_change(): void
    {
        /*
            SAYFA GERÇEKTEN AÇILAN BİR SAYFA OLMALI.

            Eskiden bu test Türkçe bir `/tr/urun/` satırını "yayında"
            işaretliyor ve gezintide görünmesini bekliyordu. Ölçüldü
            (2026-09-08): o adres yayına alınsa BİLE 404 döner, çünkü Türkçe
            içerik yuvası bilerek boş (`docs/118` E4) ve
            `ShowCorporatePageController` son emniyet kemerini uyguluyor. Yani
            test, gezintinin ÇALIŞMAYAN bir adrese bağlanmasını doğru
            sayıyordu.

            Artık gezinti de ziyaretçiyle aynı kararı okuyor
            (`ResolvePageDelivery`), dolayısıyla kanıt gerçekten açılan bir
            sayfayla verilir: `urun` / `en`, metni depoda yazılı.
        */
        $this->registryPage('/en/product/', PagePublicationStatus::Published, 'urun', 'en');

        foreach (['/', '/pricing', '/contact'] as $path) {
            $chrome = $this->chrome($path, ['Accept-Language' => 'en']);

            self::assertStringContainsString(
                'href="/en/product"',
                $chrome,
                "NAV-REGISTRY-03: [{$path}] yayınlanmış sayfayı gezintide göstermiyor."
            );
            self::assertStringContainsString(
                'data-nav-group="content-explore"',
                $chrome,
                "NAV-REGISTRY-03: [{$path}] içerik menüleri katı çizilmemiş."
            );
        }
    }

    // --- NAV-REGISTRY-05 -------------------------------------------------------

    public function test_a_page_marked_published_but_never_written_is_never_linked(): void
    {
        /*
            KÜTÜKTEKİ DURUM BİR İDDİADIR, KANIT DEĞİL.

            `publication_status` elle ileri sürülebilir; o dilde yazılmış bir
            metin olup olmadığı ayrı bir sorudur ve ziyaretçinin aldığı HTTP
            kodunu O soru belirler. İkisini ayrı hesaplayan bir gezinti,
            altbilgi zenginleştikçe yüzlerce 404'e bağlanırdı.
        */
        $page = $this->registryPage('/tr/urun/', PagePublicationStatus::Published);

        $this->get($page->canonical_path)->assertNotFound();

        foreach (['/', '/pricing', '/contact'] as $path) {
            self::assertStringNotContainsString(
                'href="/tr/urun/"',
                $this->chrome($path, ['Accept-Language' => 'tr']),
                "NAV-REGISTRY-05: [{$path}] 404 dönen bir adrese bağlantı veriyor."
            );
        }
    }

    // --- Yardımcılar -----------------------------------------------------------

    private function registryPage(
        string $path,
        PagePublicationStatus $status,
        ?string $pageKey = null,
        string $locale = 'tr',
    ): ContentPage {
        return ContentPage::query()->create([
            'page_key' => $pageKey ?? trim(str_replace('/', '.', $path), '.'),
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'Ürün genel bakış',
            'priority' => 'P0',
            'publication_status' => $status->value,
            'was_ever_published' => $status === PagePublicationStatus::Published,
        ]);
    }

    /** @param  array<string, string>  $headers */
    private function chrome(string $path, array $headers = []): string
    {
        $html = (string) $this->withHeaders($headers)->get($path)->assertOk()->getContent();
        $chrome = '';

        foreach (['header', 'footer'] as $tag) {
            preg_match("#<{$tag}\b.*?</{$tag}>#s", $html, $match);
            $chrome .= $match[0] ?? '';
        }

        return $chrome;
    }
}
