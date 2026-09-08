<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FOOTER-CONTENT-01…05 — altbilginin pSEO katı KÜTÜKTEN türer.
 *
 * ── Sahibin isteği ve ölçülen gerçek ─────────────────────────────────────
 *
 * İstek (2026-09-08): *"çoook zengin, çok katmanlı, çok row, çok menu grubu,
 * pSEO için footer üzerinde content menus."*
 *
 * Ölçüm: içeriği yazılmış on altı kurumsal sayfanın hiçbiri yayında değil.
 * Elle yazılmış zengin bir ızgara bugün yüzlerce 404'e giden bağlantı olurdu
 * ve altbilgi, kimsenin bakmadığı yerdir — kusur aylarca görünmezdi.
 *
 * ── Kapı tek cümledir ────────────────────────────────────────────────────
 *
 * **Altbilgideki her bağlantı 200 döner.** Bunu sağlayan şey dikkat değil,
 * kaynak: liste ziyaretçinin alacağı HTTP kodunu üreten aynı
 * `ResolvePageDelivery` kararından süzülüyor (`docs/129` §3).
 *
 * Kullanıcı yolculuğu: sahip "Karekod menü" sayfasını yayına alır. O gün, tek
 * bir Blade satırı değişmeden, altbilgide yeni bir grup ve yeni bir bağlantı
 * belirir; arama motoru onu tarar. Yayına almadığı gün ise orada hiçbir şey
 * yoktur — boş bir başlık bile.
 */
final class FooterContentMenusTest extends TestCase
{
    use RefreshDatabase;

    // --- FOOTER-CONTENT-01 -----------------------------------------------------

    public function test_today_the_band_is_not_drawn_at_all(): void
    {
        /*
            BUGÜNÜN DÜRÜST CEVABI. Kütükte yayınlanmış sayfa yok, dolayısıyla
            içerik menüleri katı YOK — başlığı da, kabı da. Boş bir başlık,
            olmayan bir bölümün sözünü vermektir.
        */
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        self::assertStringNotContainsString('site-footer-content', $html);
        self::assertStringNotContainsString('data-nav-group="content-', $html);
    }

    // --- FOOTER-CONTENT-02 -----------------------------------------------------

    public function test_every_link_in_the_whole_footer_resolves(): void
    {
        $this->publishedLibrary();

        /*
            KAPININ KENDİSİ. Sayfayı çiz, altbilgideki HER bağlantıyı topla,
            hepsini iste. Bir tanesi 404 dönerse kapı kırılır.
        */
        $footer = $this->footer('/pricing');

        preg_match_all('~href="(/[^"\#]*)"~', $footer, $matches);

        $targets = array_values(array_unique($matches[1]));

        self::assertGreaterThan(12, count($targets), 'Altbilgi beklenenden kısa — ölçüm dayanaksız.');

        foreach ($targets as $target) {
            $status = $this->get($target)->getStatusCode();

            /*
                200 — 302 DEĞİL. Bağlantı, sunucunun YÖNLENDİRMEDEN sunduğu
                adrese gider. Kütükteki kanonik yol sondaki eğik çizgiyi taşır
                ama adres politikası `never_except_root`; ham yolu yazmak her
                tıklamaya bir tur eklerdi.
            */
            self::assertSame(
                200,
                $status,
                "FOOTER-CONTENT-02: altbilgideki [{$target}] {$status} döndü. "
                .'Bağlantının varlığı, arkasındaki sayfanın YÖNLENDİRMESİZ çalıştığı İDDİASIDIR.'
            );
        }
    }

    // --- FOOTER-CONTENT-03 -----------------------------------------------------

    public function test_a_page_marked_published_but_never_written_never_reaches_the_footer(): void
    {
        $this->publishedLibrary();

        /*
            Türkçe içerik yuvası bilerek boş (`docs/118` E4). Bu satır kütükte
            "yayında" der, ziyaretçiye 404 döner ve altbilgiye ASLA girmez —
            ikisi aynı karardan okunduğu için ayrışamazlar.
        */
        $this->registryPage('tr.urun', 'tr', '/tr/urun/', null, PagePublicationStatus::Published);

        $this->get('/tr/urun/')->assertNotFound();

        self::assertStringNotContainsString('href="/tr/urun', $this->footer('/pricing', 'tr'));
    }

    // --- FOOTER-CONTENT-04 -----------------------------------------------------

    public function test_groups_follow_the_page_hierarchy_and_labels_come_from_the_registry(): void
    {
        $this->publishedLibrary();

        $footer = $this->footer('/pricing');

        /*
            Grup ISKELETİ tasarımda sabit değildir; sayfaların KENDİ
            hiyerarşisinden (`parent_key`) çıkar. Bir alt sayfa yayına
            alındığında yeni bir grup doğar, kod değişmez.
        */
        self::assertStringContainsString('data-nav-group="content-urun"', $footer);
        self::assertStringContainsString('data-nav-group="content-urun-menu-yonetimi"', $footer);
        self::assertStringContainsString('data-nav-group="content-explore"', $footer);

        // Etiket sayfanın KENDİ başlığından gelir — katalogda anahtar yok,
        // dolayısıyla yayına alınan her sayfa bir çeviri borcu doğurmaz.
        self::assertStringContainsString('QR menu', $footer);

        // Yaşayan gruplarda zaten duran bir adres burada TEKRAR edilmez.
        self::assertSame(1, substr_count($footer, 'href="/pricing"'));
    }

    // --- FOOTER-CONTENT-05 -----------------------------------------------------

    public function test_the_band_is_fully_readable_without_javascript(): void
    {
        $this->publishedLibrary();

        /*
            pSEO'nun tek şartı budur: adresler SUNUCU HTML'inde bulunur.
            Bant görsel olarak kapalı başlar (dar ekranda ilk ekranı
            doldurmasın diye) ama `<details>` içeriği belgede DURUR — arama
            motoru, betiği engellenmiş ziyaretçi ve JavaScript çalıştırmayan
            AI botları hepsini görür (`docs/118` E8: taban HTML, tavan serbest).
        */
        $withoutScripts = (string) preg_replace(
            '#<script\b.*?</script>#s',
            '',
            (string) $this->get('/pricing')->assertOk()->getContent()
        );

        foreach (['/en/product', '/en/product/qr-menu', '/en/product/menu-management/categories'] as $target) {
            self::assertStringContainsString(
                'href="'.$target.'"',
                $withoutScripts,
                "FOOTER-CONTENT-05: [{$target}] betiksiz gövdede yok — pSEO katı taranamaz."
            );
        }
    }

    // --- Yardımcılar -----------------------------------------------------------

    /**
     * Metni GERÇEKTEN yazılmış beş sayfayı yayına alır.
     *
     * Uydurma sayfa yok: her anahtarın karşılığı `ProductPageLibrary`de
     * yazılıdır ve adresi `config/site-source-paths.php`ten gelir.
     */
    private function publishedLibrary(): void
    {
        $this->registryPage('urun', 'en', '/en/product/', null);
        $this->registryPage('urun.qr-menu', 'en', '/en/product/qr-menu/', 'urun');
        $this->registryPage('urun.menu-yonetimi', 'en', '/en/product/menu-management/', 'urun');
        $this->registryPage(
            'urun.menu-yonetimi.kategoriler',
            'en',
            '/en/product/menu-management/categories/',
            'urun.menu-yonetimi'
        );
        $this->registryPage('cozumler', 'en', '/en/solutions/', null);
    }

    private function registryPage(
        string $pageKey,
        string $locale,
        string $path,
        ?string $parentKey,
        PagePublicationStatus $status = PagePublicationStatus::Published,
    ): ContentPage {
        return ContentPage::query()->create([
            'page_key' => $pageKey,
            'parent_key' => $parentKey,
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'QR menu',
            'priority' => 'P0',
            'publication_status' => $status->value,
            'was_ever_published' => $status === PagePublicationStatus::Published,
        ]);
    }

    private function footer(string $path, string $language = 'en'): string
    {
        $html = (string) $this->withHeaders(['Accept-Language' => $language])
            ->get($path)->assertOk()->getContent();

        preg_match('#<footer\b.*?</footer>#s', $html, $match);

        self::assertNotSame([], $match, 'Sayfada altbilgi yok — ölçüm dayanaksız.');

        return $match[0];
    }
}
