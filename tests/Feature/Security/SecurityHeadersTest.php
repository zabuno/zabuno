<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * ASVS V3 (Web Frontend Security) doğrulaması.
 *
 * Bu testler bir denetimin bulgularını dondurur: 2026-08-26'daki ASVS
 * geçişinde uygulama hiçbir güvenlik başlığı göndermiyordu. En kritik açık
 * yayınlanan menü sayfasıydı — restoranın kendi yazdığı metni gösteren,
 * kimlik doğrulaması olmayan, herkese açık bir sayfa.
 *
 * Requirement ID'leri: ASVS-V3-CSP-01, ASVS-V3-NONCE-02,
 * ASVS-V3-NO-UNSAFE-INLINE-03, ASVS-V3-BASELINE-HEADERS-04,
 * ASVS-V3-CLICKJACKING-05, ASVS-V3-API-06.
 */
final class SecurityHeadersTest extends TestCase
{
    // --- ASVS-V3-BASELINE-HEADERS-04 --------------------------------------

    public function test_every_page_carries_the_baseline_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        self::assertStringContainsString(
            'camera=()',
            (string) $response->headers->get('Permissions-Policy'),
            'ASVS-V3-BASELINE-HEADERS-04: istemediğimiz güçlü API\'ler baştan kapatılmalı.'
        );
    }

    // --- ASVS-V3-CLICKJACKING-05 ------------------------------------------

    public function test_the_application_refuses_to_be_framed(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        self::assertStringContainsString(
            "frame-ancestors 'none'",
            (string) $response->headers->get('Content-Security-Policy'),
            'ASVS-V3-CLICKJACKING-05: modern tarayıcılar frame-ancestors okur, eskiler X-Frame-Options.'
        );
    }

    // --- ASVS-V3-CSP-01 / ASVS-V3-NO-UNSAFE-INLINE-03 ---------------------

    public function test_script_execution_never_allows_unsafe_inline_or_unsafe_eval(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        self::assertNotSame('', $policy, 'ASVS-V3-CSP-01: CSP başlığı yok.');

        $scriptDirectives = array_filter(
            array_map('trim', explode(';', $policy)),
            static fn (string $directive): bool => str_starts_with($directive, 'script-src')
                || str_starts_with($directive, 'style-src ')
                || str_starts_with($directive, 'style-src-elem'),
        );

        foreach ($scriptDirectives as $directive) {
            foreach (["'unsafe-inline'", "'unsafe-eval'"] as $forbidden) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $directive,
                    "ASVS-V3-NO-UNSAFE-INLINE-03: {$forbidden}, CSP'nin XSS'e karşı koruduğu tek şeyi geri verir (`{$directive}`)."
                );
            }
        }

        self::assertStringContainsString("object-src 'none'", $policy);
        self::assertStringContainsString("base-uri 'self'", $policy);
    }

    public function test_unsafe_inline_appears_only_where_it_cannot_execute_script(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $relaxed = array_values(array_filter(
            array_map('trim', explode(';', $policy)),
            static fn (string $directive): bool => str_contains($directive, "'unsafe-inline'"),
        ));

        // Tek istisna `style` özniteliğidir: React bileşenleri onu üretir,
        // script çalıştıramaz ve yasaklamak arayüzü kırardı. Başka hiçbir
        // yönergede bulunmamalı.
        self::assertSame(
            ["style-src-attr 'unsafe-inline'"],
            $relaxed,
            'ASVS-V3-NO-UNSAFE-INLINE-03: gevşetme yalnız style özniteliğinde olabilir.'
        );
    }

    // --- ASVS-V3-NONCE-02 -------------------------------------------------

    public function test_each_response_carries_a_fresh_nonce_that_matches_its_inline_scripts(): void
    {
        $response = $this->get('/');
        $policy = (string) $response->headers->get('Content-Security-Policy');

        self::assertSame(
            1,
            preg_match("/'nonce-([A-Za-z0-9]+)'/", $policy, $matches),
            'ASVS-V3-NONCE-02: politika bir nonce taşımalı.'
        );

        $nonce = $matches[1];

        self::assertStringContainsString(
            'nonce="'.$nonce.'"',
            $response->getContent(),
            'ASVS-V3-NONCE-02: satır içi script başlıktaki nonce\'u taşımazsa sayfa sessizce bozulur.'
        );
    }

    public function test_two_requests_never_share_a_nonce(): void
    {
        $first = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $second = (string) $this->get('/')->headers->get('Content-Security-Policy');

        preg_match("/'nonce-([A-Za-z0-9]+)'/", $first, $firstMatch);
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $second, $secondMatch);

        self::assertNotSame(
            $firstMatch[1],
            $secondMatch[1],
            'ASVS-V3-NONCE-02: tekrar eden bir nonce, nonce olmamakla aynıdır.'
        );
    }

    public function test_no_inline_script_or_style_is_left_without_a_nonce(): void
    {
        // Nonce taşımayan tek bir satır içi etiket, ya sayfayı bozar ya da
        // birinin CSP'yi gevşetmesine yol açar. İkisi de kabul edilemez.
        foreach (glob(resource_path('views/**/*.blade.php')) + glob(resource_path('views/*.blade.php')) as $view) {
            $source = (string) file_get_contents($view);

            preg_match_all('/<(script|style)(?![^>]*\bnonce=)[^>]*>/i', $source, $matches);

            self::assertSame(
                [],
                $matches[0],
                'ASVS-V3-NO-UNSAFE-INLINE-03: '.basename($view).' içinde nonce taşımayan satır içi etiket var.'
            );
        }
    }

    public function test_the_public_menu_page_renders_its_inline_style_and_script_with_the_request_nonce(): void
    {
        // Yayınlanan menü, kimlik doğrulaması olmayan en açık yüzeydir ve
        // hem satır içi `<style>` hem de satır içi `<script>` taşır. Bu iki
        // etiket nonce'u kaybederse sayfa sessizce çalışmaz hâle gelir:
        // arama kutusu ve PWA kurulumu ölür, kimse de hata görmez.
        $html = view('public-menu', ['snapshot' => ['categories' => []]])->render();

        preg_match_all('/<(script|style)(?![^>]*\bnonce=)[^>]*>/i', $html, $matches);

        self::assertSame(
            [],
            $matches[0],
            'ASVS-V3-NONCE-02: yayınlanan menü sayfasında nonce taşımayan satır içi etiket var.'
        );
    }

    // --- ASVS-V3-API-06 ---------------------------------------------------

    public function test_api_responses_are_protected_too(): void
    {
        // Kimlik doğrulaması olmayan bir API isteği 401 döner; başlıklar yine
        // de gelmelidir — hata yanıtları da tarayıcıda çalışır.
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/workspaces/1/plans');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
