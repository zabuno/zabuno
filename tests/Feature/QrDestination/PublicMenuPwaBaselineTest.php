<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use Tests\TestCase;

/**
 * S1-WP04b11 RED — public-diner PWA/offline snapshot core baseline:
 *
 * The public-menu Blade view must render the supplied immutable snapshot
 * plus:
 *   - <link rel="manifest" href="/public-menu.webmanifest">
 *   - theme-color / matching manifest theme metadata
 *   - 192x192 and 512x512 icon references
 *   - an accessible install button/status element
 *   - a script that registers /public-diner-sw.js exactly twice, once with
 *     scope /menu/ and once with scope /q/
 *
 * The static manifest at public/public-menu.webmanifest must:
 *   - omit start_url (so the W3C default keeps the token document URL)
 *   - set scope "/menu/"
 *   - set display "standalone"
 *   - carry name/short_name/theme_color/background_color
 *   - list exactly two SVG icons, 192x192 and 512x512, at least one
 *     carrying a "maskable" purpose
 *
 * The classic service worker source at resources/js/public-diner-sw.js is
 * covered separately by resources/js/public-diner-sw.test.ts.
 *
 * None of these assets exist yet (no public-menu.webmanifest, no
 * public-diner-sw.js, no manifest/install/service-worker markup in the
 * Blade view), so every assertion below fails RED first.
 *
 * Requirement IDs: PWA-MANIFEST-LINK-01, PWA-MANIFEST-STATIC-01,
 * PWA-MANIFEST-SCOPE-01, PWA-MANIFEST-ICONS-01, PWA-INSTALL-UI-01,
 * PWA-SW-REGISTER-TWICE-01, PWA-FLUID-320-01.
 */
final class PublicMenuPwaBaselineTest extends TestCase
{
    private function snapshotFixture(): array
    {
        return [
            'categories' => [
                [
                    'name' => 'İçecekler',
                    'menuItems' => [
                        [
                            'productName' => 'Ayran',
                            'priceMinorAmount' => 2500,
                            'currencyCode' => 'TRY',
                            'allergens' => ['süt'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function renderPublicMenu(): string
    {
        return view('public-menu', ['snapshot' => $this->snapshotFixture()])->render();
    }

    private function twoCategorySnapshot(): array
    {
        return [
            'categories' => [
                [
                    'name' => 'İçecekler',
                    'menuItems' => [
                        [
                            'productName' => 'Ayran',
                            'priceMinorAmount' => 2500,
                            'currencyCode' => 'TRY',
                            'allergens' => ['süt'],
                        ],
                        [
                            'productName' => 'Kola',
                            'priceMinorAmount' => 3000,
                            'currencyCode' => 'TRY',
                            'allergens' => [],
                        ],
                    ],
                ],
                [
                    'name' => 'Tatlılar',
                    'menuItems' => [
                        [
                            'productName' => 'Baklava',
                            'priceMinorAmount' => 8000,
                            'currencyCode' => 'TRY',
                            'allergens' => ['fındık', 'süt'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function renderPublicMenuWith(array $snapshot): string
    {
        return view('public-menu', ['snapshot' => $snapshot])->render();
    }

    public function test_it_still_renders_the_supplied_immutable_snapshot(): void
    {
        $html = $this->renderPublicMenu();

        $this->assertStringContainsString('Ayran', $html);
        $this->assertStringContainsString('İçecekler', $html);
    }

    public function test_it_links_the_web_app_manifest(): void
    {
        $html = $this->renderPublicMenu();

        $this->assertMatchesRegularExpression(
            '#<link[^>]+rel="manifest"[^>]+href="/public-menu\.webmanifest"#',
            $html,
            'expected a <link rel="manifest" href="/public-menu.webmanifest"> tag'
        );
    }

    public function test_it_declares_theme_color_metadata_matching_the_manifest(): void
    {
        $html = $this->renderPublicMenu();

        $this->assertMatchesRegularExpression(
            '#<meta[^>]+name="theme-color"[^>]+content="#',
            $html,
            'expected a <meta name="theme-color"> tag'
        );
    }

    public function test_it_references_the_192_and_512_icons(): void
    {
        $html = $this->renderPublicMenu();

        $this->assertStringContainsString('192x192', $html);
        $this->assertStringContainsString('512x512', $html);
    }

    public function test_it_renders_an_accessible_install_button(): void
    {
        $html = $this->renderPublicMenu();

        $this->assertMatchesRegularExpression(
            '#<button[^>]+id="pwa-install-button"[^>]*>#',
            $html,
            'expected a <button id="pwa-install-button"> element'
        );

        $this->assertMatchesRegularExpression(
            '#id="pwa-install-status"[^>]+role="status"#',
            $html,
            'expected a #pwa-install-status element with role="status"'
        );
    }

    public function test_it_registers_the_service_worker_exactly_twice_with_explicit_scopes(): void
    {
        $html = $this->renderPublicMenu();

        preg_match_all(
            '#navigator\.serviceWorker\.register\(\s*["\']\/public-diner-sw\.js["\']\s*,\s*\{\s*scope:\s*["\'](/menu/|/q/)["\']#',
            $html,
            $matches
        );

        $this->assertCount(
            2,
            $matches[1],
            'expected exactly two navigator.serviceWorker.register(...) calls for /public-diner-sw.js'
        );
        $this->assertContains('/menu/', $matches[1]);
        $this->assertContains('/q/', $matches[1]);
    }

    public function test_new_ui_markup_avoids_breakpoint_media_queries(): void
    {
        $html = $this->renderPublicMenu();

        $this->assertDoesNotMatchRegularExpression(
            '/@media[^{]*\(\s*(min|max)-width/i',
            $html,
            'new PWA/install UI must stay 320-fluid — no breakpoint media queries'
        );
    }

    public function test_static_manifest_file_exists_with_required_fields(): void
    {
        $manifestPath = public_path('public-menu.webmanifest');

        $this->assertFileExists($manifestPath, 'expected public/public-menu.webmanifest to exist');

        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($manifest);
        $this->assertArrayNotHasKey(
            'start_url',
            $manifest,
            'manifest must omit start_url so the W3C default keeps the token document URL'
        );
        $this->assertSame('/menu/', $manifest['scope'] ?? null);
        $this->assertSame('standalone', $manifest['display'] ?? null);
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('short_name', $manifest);
        $this->assertArrayHasKey('theme_color', $manifest);
        $this->assertArrayHasKey('background_color', $manifest);
    }

    public function test_static_manifest_declares_exactly_two_svg_icons_with_a_maskable_purpose(): void
    {
        $manifestPath = public_path('public-menu.webmanifest');

        $this->assertFileExists($manifestPath, 'expected public/public-menu.webmanifest to exist');

        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $icons = $manifest['icons'] ?? [];

        $this->assertCount(2, $icons, 'expected exactly two icons in the manifest');

        $sizes = array_map(static fn (array $icon) => $icon['sizes'] ?? null, $icons);
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        foreach ($icons as $icon) {
            $this->assertSame('image/svg+xml', $icon['type'] ?? null, 'every icon must be an SVG');
        }

        $purposes = array_map(static fn (array $icon) => $icon['purpose'] ?? '', $icons);
        $maskable = array_filter($purposes, static fn (string $purpose) => str_contains($purpose, 'maskable'));
        $this->assertNotEmpty($maskable, 'at least one icon must carry a "maskable" purpose');
    }

    // --- S1-WP04b11-followup RED: visible header/summary/nav/search/list ----

    public function test_it_renders_an_h1_menu_heading_and_an_honest_published_menu_subtitle(): void
    {
        $html = $this->renderPublicMenu();

        $this->assertMatchesRegularExpression(
            '#<h1[^>]*>\s*Menü\s*#u',
            $html,
            'expected a visible <h1>Menü</h1> page heading'
        );

        $this->assertMatchesRegularExpression(
            '/Yayınlanan menü/u',
            $html,
            'expected a concise honest published-menu subtitle'
        );
    }

    public function test_it_renders_real_category_and_item_counts_derived_from_the_snapshot(): void
    {
        $html = $this->renderPublicMenuWith($this->twoCategorySnapshot());

        /*
            2 kategori, 3 ürün — snapshot'tan HESAPLANIR, sabit ya da uydurma
            bir sayı değildir.

            İddia SAYIYA bakar, cümlenin diline değil: özet metni artık
            katalogdan geliyor (`docs/85`) ve "1 categories" gibi bir çoğul
            hatasından kaçınmak için etiket-değer biçimine geçti (`docs/86`).
            Cümleyi teste sabitlemek, metni her düzelttiğimizde testi
            kırardı.
        */
        $this->assertMatchesRegularExpression(
            '#<p class="qr-menu-summary">[^<]*\b2\b[^<]*\b3\b[^<]*</p>#u',
            $html,
        );
    }

    public function test_it_renders_accessible_category_navigation_with_deterministic_loop_index_anchors(): void
    {
        $html = $this->renderPublicMenuWith($this->twoCategorySnapshot());

        $this->assertMatchesRegularExpression(
            '#<nav[^>]+aria-label="[^"]*[Kk]ategori[^"]*"[^>]*>#u',
            $html,
            'expected an accessible category navigation landmark'
        );

        $this->assertStringContainsString('href="#category-0"', $html);
        $this->assertStringContainsString('id="category-0"', $html);
        $this->assertStringContainsString('href="#category-1"', $html);
        $this->assertStringContainsString('id="category-1"', $html);
    }

    public function test_it_renders_a_labeled_client_side_search_input_and_an_honest_no_match_status(): void
    {
        $html = $this->renderPublicMenu();

        $this->assertMatchesRegularExpression(
            '#<label[^>]+for="menu-search"[^>]*>#',
            $html,
            'expected a <label for="menu-search"> element'
        );
        $this->assertMatchesRegularExpression(
            '#<input[^>]+id="menu-search"[^>]*>#',
            $html,
            'expected a #menu-search input element'
        );
        $this->assertMatchesRegularExpression(
            '#<input[^>]+type="search"[^>]+id="menu-search"[^>]*>|<input[^>]+id="menu-search"[^>]+type="search"[^>]*>#',
            $html,
            'expected #menu-search to be type="search"'
        );

        $this->assertMatchesRegularExpression(
            '#id="menu-search-status"[^>]+role="status"#',
            $html,
            'expected a #menu-search-status role="status" element to announce result count/no matches'
        );

        /*
            ARAMA SONUÇLARI AĞDAN GELMEZ.

            Kural eskiden "sayfada hiç `fetch(` olmasın" diye yazılmıştı ve o
            gün doğruydu: sayfada başka bir ağ çağrısı yoktu, dolayısıyla
            varlığı ancak aramanın sunucuya sorması anlamına gelebilirdi.

            `docs/84` ile sayfa ÖLÇÜM gönderiyor. Kuralın NİYETİ değişmedi —
            arama tuşa her basıldığında sunucuya gitmemeli, öneri servisi
            olmamalı — ama ifadesi artık niyeti aşıyordu.

            Yeni ifade: sayfadaki ağ hedefi YALNIZ ölçüm ucudur. Arama
            sonuçları DOM'dan gelir ve bunu bir sonraki iddia dondurur.
        */
        preg_match_all('#(?:fetch|sendBeacon)\s*\(\s*[\'"]([^\'"]+)[\'"]#', $html, $networkTargets);

        foreach ($networkTargets[1] as $target) {
            $this->assertSame(
                '/q/events',
                $target,
                'misafir sayfası yalnız ölçüm ucuna ağ çağrısı yapmalı; arama sonuçları DOM\'dan gelir'
            );
        }

        // Arama girdisinin dinleyicisi sonucu SAYARAK bulur, isteyerek değil.
        $this->assertMatchesRegularExpression(
            '/searchInput\.addEventListener\([^)]*\'input\'/',
            $html,
            'arama istemci tarafında, girdi olayına bağlı olmalı'
        );

        $this->assertDoesNotMatchRegularExpression('/\.setItem\s*\(\s*["\']menu-search/', $html);
    }

    public function test_menu_items_render_as_semantic_lists_with_deterministic_search_data_attributes(): void
    {
        $html = $this->renderPublicMenuWith($this->twoCategorySnapshot());

        $this->assertMatchesRegularExpression('#<ul[^>]+class="[^"]*qr-menu-item-list[^"]*"#', $html);
        $this->assertMatchesRegularExpression('#<li[^>]+class="[^"]*qr-menu-item[^"]*"#', $html);
        $this->assertMatchesRegularExpression('#<li[^>]+data-item-name="Ayran"#u', $html);
    }

    public function test_it_renders_an_honest_empty_menu_state_when_the_snapshot_has_no_categories(): void
    {
        $html = $this->renderPublicMenuWith(['categories' => []]);

        // Sıfır da hesaplanır ve GÖSTERİLİR: boş bir özet, sayfanın
        // yüklenmediği izlenimi verirdi.
        $this->assertMatchesRegularExpression(
            '#<p class="qr-menu-summary">[^<]*\b0\b[^<]*\b0\b[^<]*</p>#u',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/[Bb]u menüde (henüz )?(ürün|kategori) (yok|bulunmuyor)/u',
            $html,
            'expected an honest empty-menu status when the snapshot has no categories'
        );
    }

    public function test_new_search_and_layout_markup_uses_intrinsic_fluid_sizing_with_no_fixed_min_width(): void
    {
        $html = $this->renderPublicMenu();

        $this->assertDoesNotMatchRegularExpression(
            '/min-width\s*:\s*\d/i',
            $html,
            'layout must stay intrinsic/fluid — no fixed pixel/unit min-width constraint'
        );
    }
}
