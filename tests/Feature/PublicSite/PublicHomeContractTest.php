<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Support\Localization\SiteText;
use Tests\TestCase;

/**
 * Herkese açık ana sayfanın sözleşmesi — SUNUCUDA üretilir.
 *
 * Bu sözleşme daha önce React bileşeni üzerinde donmuştu
 * (`AppShellRootCta.test.tsx`). Sayfa sunucuya taşındığı için sözleşme de
 * taşındı; hiçbir maddesi düşürülmedi. Taşımanın sebebi ölçümdür: istemcide
 * üretilirken bir tarayıcı botunun gördüğü gövde 1.736 bayttı ve içerik
 * `<div id="app"></div>`'den ibaretti.
 *
 * Requirement ID'leri: HOME-SSR-01, HOME-A11Y-02, HOME-HONEST-03,
 * HOME-FLUID-04, HOME-NO-REACT-05, HOME-PRICE-06.
 */
final class PublicHomeContractTest extends TestCase
{
    private function html(string $uri = '/'): string
    {
        return (string) $this->get($uri)->getContent();
    }

    // --- HOME-SSR-01 -------------------------------------------------------

    /**
     * GÜNCELLENDİ (FF-233, `docs/138`).
     *
     * Ölçülen ŞART değişmedi: bir bot boş bir kabuk değil, sayfanın gerçek
     * metnini almalı. Değişen, hangi cümlenin sondaj noktası olduğu.
     *
     * `Publication & stable QR` dört maddelik eski "Features" listesinden
     * geliyordu. O liste, ürünün kendi envanterinden (`ProductOverviewPage`)
     * bağımsız yazılmış dört genel başlıktı ve envanterle ayrışabilirdi —
     * ayrışsa da hiçbir şey kırılmazdı. Yerine on iki parçalık gerçek
     * envanter geldi ve `HOME-REAL-07` ikisinin aynı kalmasını donduruyor.
     *
     * Sondaj bu yüzden artık ENVANTERDEN seçilmiş bir terim: `QR menu`.
     * Adı bir gün değişirse önce `HOME-REAL-07` konuşur, bu test değil.
     */
    public function test_a_crawler_receives_the_actual_content_not_an_empty_shell(): void
    {
        $html = $this->html();

        self::assertStringContainsString('Run your restaurant', $html);
        self::assertStringContainsString('QR menu', $html);
        // Ürünün NE OLMADIĞI da gövdededir: bir bot yalnız vaatleri değil,
        // sınırları da görüyor.
        self::assertStringContainsString('What Zabuno is not', $html);
        self::assertGreaterThan(
            5000,
            strlen($html),
            'HOME-SSR-01: gövde yeniden boş kabuğa dönmüş olabilir — bot içeriği göremez.'
        );
    }

    public function test_every_named_section_is_present_in_the_source(): void
    {
        $html = $this->html();

        foreach (['features', 'how-it-works', 'pricing', 'faq', 'contact'] as $section) {
            self::assertStringContainsString('id="'.$section.'"', $html, "HOME-SSR-01: `{$section}` bölümü yok.");
        }
    }

    // --- HOME-A11Y-02 ------------------------------------------------------

    public function test_the_page_offers_a_skip_link_and_a_main_landmark(): void
    {
        $html = $this->html();

        self::assertStringContainsString('href="#main-content"', $html);
        self::assertStringContainsString('id="main-content"', $html);
        self::assertStringContainsString('<main', $html);
    }

    public function test_the_account_actions_are_real_links(): void
    {
        $html = $this->html();

        foreach (['/app', '/login', '/register'] as $target) {
            self::assertStringContainsString('href="'.$target.'"', $html, "HOME-A11Y-02: {$target} bağlantısı yok.");
        }
    }

    // --- HOME-PRICE-06 : ilk ekranda fiyata giden yol -----------------------

    /**
     * İLK EKRANIN İKİNCİ EYLEMİ BİR FİYAT YOLUDUR, BİR GİRİŞ SAPMASI DEĞİL.
     *
     * Kahramanın iki eylemi var ve ikisi de HENÜZ MÜŞTERİ OLMAYAN için
     * yazılmıştır: birincisi hesap açar, ikincisi "bu bana kaça mal olur"
     * sorusunu cevaplar. `/app` o soruyu cevaplamaz — hesabı olmayan
     * ziyaretçiyi bir giriş ekranına gönderir ve oradan geri döndürmez.
     *
     * Ölçülen şey bir tercih değil, bir YOLCULUK: 320×480'de ilk ekranda
     * yalnız iki düğme sığıyor ve ikincisi kime ait olursa, sayfa onun
     * sorusunu cevaplıyor demektir. Fiyat cevabı sayfanın çok altındaki
     * soru-cevap bağlantısında da duruyor; ilk ekranda DURMUYORDU.
     *
     * `/app` sayfadan silinmez — kabuğun altbilgisinde duruyor ve
     * `HOME-A11Y-02` ile `HOME-SCENE-05` onu ORADA arıyor. Ölçülen,
     * varlığı değil KAHRAMANDAKİ yeri.
     */
    private function heroActions(string $uri = '/'): string
    {
        $html = $this->html($uri);

        self::assertSame(
            1,
            preg_match('#<nav[^>]*class="[^"]*\bhome-actions\b[^"]*"[^>]*>(.*?)</nav>#s', $html, $match),
            'HOME-PRICE-06: kahramanın eylem grubu (`home-actions`) bulunamadı — ölçüm dayanaksız.'
        );

        return $match[1];
    }

    public function test_the_first_screen_offers_a_route_to_the_price(): void
    {
        $actions = $this->heroActions();

        self::assertStringContainsString(
            'href="/pricing"',
            $actions,
            'HOME-PRICE-06: ilk ekranın eylemleri fiyata giden bir yol taşımıyor — '
            .'"bu bana kaça mal olur" sorusunun cevabı sayfanın altında kalıyor.'
        );

        self::assertStringContainsString(
            'href="/register"',
            $actions,
            'HOME-PRICE-06: birincil eylem (`/register`) değişmedi; kayboluyorsa ölçüm yanlış yerde.'
        );
    }

    public function test_the_first_screen_does_not_detour_through_an_anonymous_app_login(): void
    {
        self::assertStringNotContainsString(
            'href="/app"',
            $this->heroActions(),
            'HOME-PRICE-06: kahraman hâlâ `/app` sapmasını taşıyor — hesabı olmayan '
            .'ziyaretçi bir giriş ekranına gönderiliyor.'
        );

        // Ama yol SİLİNMEDİ: kabuğun altbilgisinde duruyor.
        self::assertStringContainsString('href="/app"', $this->html());
    }

    /**
     * Etiket KATALOGDAN gelir: `site.pricing.heading` zaten çevrilidir ve
     * soru-cevap bağlantısı da aynı anahtarı kullanıyor. Elle yazılmış bir
     * "Pricing", hiçbir PO dosyasında görünmez ve Türkçe sayfada İngilizce
     * kalırdı (`I18N-SSR-RATCHET-16`).
     */
    public function test_the_price_action_carries_the_catalogue_label_in_every_locale(): void
    {
        $text = app(SiteText::class);

        foreach (['en', 'tr'] as $locale) {
            $expected = $text->get('site.pricing.heading', $locale);
            $actions = $this->heroActions('/?language='.$locale);

            self::assertStringContainsString(
                e($expected),
                $actions,
                "HOME-PRICE-06: [{$locale}] fiyat eylemi katalog etiketini (\"{$expected}\") taşımıyor."
            );
        }
    }

    // --- HOME-HONEST-03 ----------------------------------------------------

    /**
     * GÜNCELLENDİ (`docs/88`, P1-01).
     *
     * Bu test eskiden ana sayfanın "henüz fiyat yok" ve "henüz iletişim
     * formu yok" DEMESİNİ donduruyordu. O gün doğruydu: ikisi de yoktu ve
     * varmış gibi yapmak yalan olurdu.
     *
     * İkisi de artık VAR. Sınırı hâlâ ilan etmek, bu kez ters yönde bir
     * yalan olurdu — ziyaretçiyi var olan bir yoldan geri çevirirdi.
     *
     * Kuralın kendisi değişmedi: sayfa ne varsa onu söyler. Ölçülen şey
     * artık cümlelerin YOKLUĞU değil, yolların VARLIĞI.
     */
    public function test_pricing_and_contact_point_at_paths_that_exist(): void
    {
        $html = $this->html();

        self::assertStringNotContainsString('no published plan prices yet', $html);
        self::assertStringNotContainsString('no connected contact form yet', $html);

        self::assertStringContainsString('href="/pricing"', $html);
        self::assertStringContainsString('href="/contact"', $html);

        // Ve o yollar GERÇEKTEN açılır: var olmayan bir sayfaya bağlantı
        // vermek, "henüz yok" demekten daha kötüdür.
        $this->get('/pricing')->assertOk();
        $this->get('/contact')->assertOk();
    }

    public function test_no_fabricated_social_proof_appears(): void
    {
        $html = strtolower($this->html());

        foreach (['testimonial', 'trusted by', 'customers served', '% satisfaction'] as $claim) {
            self::assertStringNotContainsString(
                $claim,
                $html,
                "HOME-HONEST-03: \"{$claim}\" — var olmayan bir kanıt uydurmak, ürünü satmanın en kısa ömürlü yoludur."
            );
        }
    }

    // --- HOME-FLUID-04 -----------------------------------------------------

    public function test_the_base_layout_stays_fluid_without_breakpoint_utility_classes(): void
    {
        // 320 tabanı akışkan kalır; 64rem CSS masaüstü bileşimi sahibin
        // 2026-09-08 kararıyla eklenir. Utility ile mobil geri alma yok.
        preg_match_all('/class="([^"]*)"/', $this->html(), $matches);

        foreach ($matches[1] as $classList) {
            self::assertDoesNotMatchRegularExpression(
                '/(^|\s)(sm|md|lg|xl|2xl):/',
                $classList,
                'HOME-FLUID-04: kırılma noktası jetonu bulundu: '.$classList
            );
        }
    }

    // --- HOME-NO-REACT-05 --------------------------------------------------

    public function test_the_marketing_pages_ship_no_react_bundle(): void
    {
        // Bu sayfalarda etkileşim yok; React paketini yüklemek, botun
        // göremeyeceği bir yükü herkese indirtmek olurdu.
        foreach (['/', '/terms', '/privacy', '/kvkk'] as $uri) {
            $html = $this->html($uri);

            self::assertStringNotContainsString('id="app"', $html, "HOME-NO-REACT-05: {$uri} hâlâ React montaj noktası taşıyor.");
            self::assertStringNotContainsString('app.tsx', $html);
        }
    }

    public function test_the_legal_pages_are_server_rendered_too(): void
    {
        foreach (['/terms' => 'Terms', '/privacy' => 'Privacy', '/kvkk' => 'KVKK'] as $uri => $title) {
            $html = $this->html($uri);

            self::assertStringContainsString('<h1', $html);
            self::assertStringContainsString($title, $html);
            // Yer tutucu gitti (FF-198): sayfa gerçek belgeyi ve sürümünü taşır.
            self::assertStringContainsString('data-legal-document="', $html);
            self::assertStringContainsString('data-legal-version="', $html);
        }
    }
}
