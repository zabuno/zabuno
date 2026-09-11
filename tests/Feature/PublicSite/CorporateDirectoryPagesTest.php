<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C3 — kurumsal DİZİN yüzeyi: künye sayfası, insan için site haritası ve
 * doğrulanmış tek sosyal profil.
 *
 * ── Ne dondurulyor ───────────────────────────────────────────────────────
 *
 * Üç karar, üçü de "uydurma yok" kuralının ayrı bir yüzü:
 *
 * 1. **Künye artık bir SAYFA.** Altbilgide katlanmış yedi satır yerine
 *    adıyla aranabilen bir adres (`/information-society-services`). Girilmemiş
 *    alan hâlâ "girilmedi" der ve sayfa `noindex` döner — eksik bir kimlik
 *    sessizce tamam görünmemeli.
 * 2. **Site haritası gezintiden TÜRER.** Elle yazılmış bir ağaç, ilk yayına
 *    alınan sayfada eskir ve ziyaretçiye 404'e giden bir dal gösterirdi.
 *    Ağaçtaki her hedef, ziyaretçinin alacağı HTTP kodunu üreten aynı
 *    karardan geçer (`docs/129` §3).
 * 3. **Sosyal şerit yalnız DOĞRULANMIŞ adresi çizer.** Doğrulanmamış bir
 *    platform ikonu, hiçbir yere gitmeyen bir bağlantıdır ve ürünün geri
 *    kalanına da gölge düşürür.
 */
final class CorporateDirectoryPagesTest extends TestCase
{
    use RefreshDatabase;

    // --- C3-ISS-01 -------------------------------------------------------------

    public function test_the_seller_identity_page_shows_every_field_and_never_invents_one(): void
    {
        config()->set('legal.company', [
            'legal_name' => 'Örnek Teknoloji A.Ş.',
            'address' => 'Örnek Mah. 1. Sok. No:1, İstanbul',
            'mersis' => '0000000000000000',
            'tax_office' => 'Örnek',
            'tax_number' => '1234567890',
            'email' => 'destek@example.test',
            // Telefon BİLEREK girilmedi: sayfanın eksik alanı nasıl
            // gösterdiği, dolu alanı nasıl gösterdiği kadar önemli.
            'phone' => null,
        ]);

        $response = $this->get('/information-society-services')->assertOk();
        $html = (string) $response->getContent();

        foreach (['legal_name', 'address', 'mersis', 'tax_office', 'tax_number', 'email', 'phone'] as $field) {
            self::assertStringContainsString(
                'data-company-field="'.$field.'"',
                $html,
                "C3-ISS-01: [{$field}] alanı sayfada atlanmış — atlanan satır, o alanın hiç istenmediği izlenimi verir."
            );
        }

        self::assertStringContainsString('data-company-identity="incomplete"', $html);
        self::assertStringContainsString('data-missing="true"', $html);

        /*
            EKSİK KİMLİKTE `noindex` — `/about` ile aynı karar. Eksik bir
            künyeyi arama motoruna sunmak, satıcının kim olduğunu söylediğini
            iddia edip söylememektir.
        */
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    // --- C3-ISS-02 -------------------------------------------------------------

    public function test_the_page_only_links_to_documents_and_routes_that_really_exist(): void
    {
        $html = (string) $this->get('/information-society-services')->assertOk()->getContent();

        preg_match_all('#<main\b.*?</main>#s', $html, $main);
        $body = $main[0][0] ?? '';

        self::assertNotSame('', $body, 'C3-ISS-02: sayfanın gövdesi okunamadı — ölçüm dayanaksız.');

        // Yasal belge listesi ve iletişim yolu; hepsi bugün 200 dönmeli.
        preg_match_all('#href="(/[a-z/-]*)"#', $body, $matches);
        $targets = array_values(array_unique($matches[1]));

        self::assertContains('/contact', $targets, 'C3-ISS-02: veri hakkı talebinin yolu sayfada yok.');
        self::assertContains('/terms', $targets, 'C3-ISS-02: yasal belge listesi çizilmemiş.');

        foreach ($targets as $target) {
            $status = $this->get($target)->getStatusCode();

            self::assertContains(
                $status,
                [200, 302],
                "C3-ISS-02: künye sayfasındaki [{$target}] {$status} döndü — ölü bağlantı yasak."
            );
        }
    }

    // --- C3-MAP-01 -------------------------------------------------------------

    public function test_the_site_map_is_a_clickable_nested_list_rooted_at_home(): void
    {
        $html = (string) $this->get('/site-map')->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        // GÖRSEL DEĞİL: ağaç anlamsal bir listedir ve resim içermez.
        self::assertSame(0, $xpath->query('//*[@data-site-map]//img')->length);
        self::assertSame(1, $xpath->query('//*[@data-site-map]//a[@href="/"]')->length);

        // İÇ İÇE: kök liste → dal → yaprak.
        self::assertGreaterThan(
            0,
            $xpath->query('//*[@data-site-map]//ul//ul//ul')->length,
            'C3-MAP-01: ağaç iç içe değil — bir site haritası hiyerarşiyi gösterir.'
        );

        $groups = $xpath->query('//*[@data-site-map-group]');
        self::assertGreaterThan(0, $groups->length, 'C3-MAP-01: hiç grup düğümü yok.');

        foreach ($groups as $group) {
            // Grup adı bir sayfa DEĞİL: tıklanabilir görünmemeli.
            self::assertSame('span', $group->nodeName, 'C3-MAP-01: grup adı bir bağlantı olarak çizilmiş.');
        }

        foreach ($xpath->query('//*[@data-site-map]//a') as $link) {
            $href = $link->getAttribute('href');
            self::assertStringStartsWith('/', $href, "C3-MAP-01: haritada iç olmayan bir hedef var: {$href}");
            self::assertContains(
                $this->get($href)->getStatusCode(),
                [200, 302],
                "C3-MAP-01: haritadaki [{$href}] bugün açılmıyor — yayınlanmamış hedef haritaya giremez."
            );
        }
    }

    // --- C3-SOCIAL-01 ----------------------------------------------------------

    public function test_only_the_verified_profile_is_drawn_and_an_unset_one_leaves_no_dead_icon(): void
    {
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        // Doğrulanmış tek adres — sahibin 2026-09-11 kararıyla ürünün
        // organizasyonu değil, ürünü YAPAN (`config/social.php`).
        self::assertStringContainsString('href="https://atonota.com"', $html);
        self::assertStringContainsString('data-social="github"', $html);

        // İkon TEK BAŞINA duruyor; adı bağlantıda yaşar, ikonda değil
        // (`ICON-03`: ekran okuyucu aynı şeyi iki kez söylemez).
        preg_match('#<a[^>]*data-social="github"[^>]*>#', $html, $anchor);
        self::assertNotSame([], $anchor, 'C3-SOCIAL-01: sosyal bağlantı bulunamadı.');

        /*
            AD GÖRÜNÜR BİR DÜĞÜMDE, `aria-label`DA DEĞİL.

            Etiket artık gizli bir öznitelikte değil, bağlantının içindeki
            gerçek metinde yaşıyor: iki kaynak bir gün ayrışır ve ayrışan
            taraf her zaman kimsenin bakmadığı taraf olur. Bu madde onu
            tersinden kilitler — `aria-label` GERİ GELİRSE kırılır.
        */
        self::assertStringNotContainsString('aria-label=', $anchor[0]);
        self::assertMatchesRegularExpression(
            '#<a[^>]*data-social="github"[^>]*>.*?<span class="site-social-name">İsmail Karaca</span>#s',
            $html,
            'C3-SOCIAL-01: yapımcının adı bağlantının içinde görünür bir düğüm olarak yok; '
            .'vurgu yokken (dokunmalı cihaz) bağlantı adsız kalır.'
        );
        self::assertStringContainsString('rel="me noopener"', $anchor[0]);

        /*
            YAPILANDIRILMAMIŞ PROFİL HİÇ ÇİZİLMEZ ve HTTPS OLMAYAN bir adres
            de çizilmez: yapılandırmadan gelen bir dize doğrudan `href`e
            yazılıyor ve süzgeç olmasaydı bir `.env` satırı bir betik yüzeyi
            olurdu.
        */
        config()->set('social.profiles.github', null);
        self::assertStringNotContainsString('data-social="github"', (string) $this->get('/pricing')->getContent());

        config()->set('social.profiles.github', 'javascript:alert(1)');
        self::assertStringNotContainsString('data-social="github"', (string) $this->get('/pricing')->getContent());
    }

    // --- C3-WIRING-01 ----------------------------------------------------------

    public function test_both_new_pages_are_reachable_from_the_footer_and_declared_in_the_xml_sitemap(): void
    {
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        foreach (['/information-society-services', '/site-map'] as $path) {
            self::assertStringContainsString(
                'href="'.$path.'"',
                $html,
                "C3-WIRING-01: [{$path}] altbilgiden erişilemiyor."
            );
        }

        /*
            XML SİTEMAP — kimlik TAMKEN. Künye sayfası satıcı kimliği
            kuralına tabidir: eksikken sayfa `noindex` döner ve bu dosyadan
            da düşer, yani iki sinyal aynı şeyi söyler (`ShowSitemapController`).
        */
        config()->set('legal.company', [
            'legal_name' => 'Örnek Teknoloji A.Ş.',
            'address' => 'Örnek Mah. 1. Sok. No:1, İstanbul',
            'mersis' => '0000000000000000',
            'tax_office' => 'Örnek',
            'tax_number' => '1234567890',
            'email' => 'destek@example.test',
            'phone' => '+90 000 000 00 00',
        ]);

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        self::assertStringContainsString('/information-society-services</loc>', $xml);
        self::assertStringContainsString('/site-map</loc>', $xml);
    }
}
