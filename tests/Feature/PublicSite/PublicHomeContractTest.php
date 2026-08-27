<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

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
 * HOME-FLUID-04, HOME-NO-REACT-05.
 */
final class PublicHomeContractTest extends TestCase
{
    private function html(string $uri = '/'): string
    {
        return (string) $this->get($uri)->getContent();
    }

    // --- HOME-SSR-01 -------------------------------------------------------

    public function test_a_crawler_receives_the_actual_content_not_an_empty_shell(): void
    {
        $html = $this->html();

        self::assertStringContainsString('Run your restaurant', $html);
        self::assertStringContainsString('Publication &amp; stable QR', $html);
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

    // --- HOME-HONEST-03 ----------------------------------------------------

    public function test_pricing_and_contact_state_their_limits_instead_of_pretending(): void
    {
        $html = $this->html();

        self::assertStringContainsString('no published plan prices yet', $html);
        self::assertStringContainsString('no connected contact form yet', $html);
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

    public function test_the_layout_is_fluid_rather_than_breakpoint_gated(): void
    {
        // Düzen 320 pikselden itibaren akışkan olmalı; kırılma noktasına
        // bağlı bir düzen, aradaki her genişlikte bozuk demektir.
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
            self::assertStringContainsString('pending qualified legal review', $html);
        }
    }
}
