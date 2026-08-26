<?php

declare(strict_types=1);

namespace Tests\Feature\Url;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * URL motorunun gerçek HTTP yüzeyindeki davranışı.
 *
 * İstekler test yardımcısı yerine DOĞRUDAN çekirdekten geçirilir. Sebebi
 * önemlidir: Laravel'in `get()` yardımcısı adresi kendisi normalize eder ve
 * sondaki slash'ı gönderilmeden önce siler. Yani yardımcıyla yazılmış bir
 * test, motorun trailing-slash kuralını hiç çalıştırmadan "geçer" — kural
 * kaldırılsa bile yeşil kalırdı.
 *
 * Requirement ID'leri: URL-MW-REDIRECT-07, URL-MW-SINGLE-HOP-08,
 * URL-MW-QR-INTACT-09, URL-MW-POST-SAFE-10, URL-MW-DUPLICATE-11.
 */
final class CanonicalUrlMiddlewareTest extends TestCase
{
    private function send(string $uri, string $method = 'GET'): Response
    {
        return $this->app->make(Kernel::class)->handle(Request::create($uri, $method));
    }

    // --- URL-MW-REDIRECT-07 ------------------------------------------------

    public function test_a_trailing_slash_redirects_once_to_the_canonical_address(): void
    {
        $response = $this->send('/terms/');

        self::assertSame(301, $response->getStatusCode());
        self::assertStringEndsWith('/terms', (string) $response->headers->get('Location'));
    }

    public function test_a_mixed_case_static_path_redirects_to_lower_case(): void
    {
        $response = $this->send('/Privacy');

        self::assertSame(301, $response->getStatusCode());
        self::assertStringEndsWith('/privacy', (string) $response->headers->get('Location'));
    }

    // --- URL-MW-SINGLE-HOP-08 ----------------------------------------------

    public function test_several_deviations_are_fixed_in_one_hop_not_a_chain(): void
    {
        // Zincir hem yavaştır hem tarama bütçesi yer; hedef doğrudan nihai
        // biçim olmalı ve ikinci istek yönlenmemeli.
        $response = $this->send('/Terms//');

        self::assertSame(301, $response->getStatusCode());

        $location = (string) $response->headers->get('Location');
        self::assertStringEndsWith('/terms', $location);

        $second = $this->send((string) parse_url($location, PHP_URL_PATH));
        self::assertSame(200, $second->getStatusCode(), 'URL-MW-SINGLE-HOP-08: hedef hâlâ yönleniyor — zincir var.');
    }

    // --- URL-MW-QR-INTACT-09 -----------------------------------------------

    public function test_a_qr_token_survives_the_engine_untouched(): void
    {
        // Bu testin varlık sebebi: bir normalizasyon kuralı token'a dokunursa
        // masadaki basılı kod sessizce ölür ve kimse fark etmez.
        $token = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-AbCdE';

        $response = $this->send('/q/'.$token);

        self::assertNotSame(301, $response->getStatusCode(), 'URL-MW-QR-INTACT-09: QR yolu normalizasyona uğradı.');
    }

    public function test_an_uppercase_qr_token_is_not_folded_into_a_different_token(): void
    {
        $token = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-abcde';

        $response = $this->send('/q/'.$token);

        self::assertNotSame(301, $response->getStatusCode());
    }

    // --- URL-MW-POST-SAFE-10 -----------------------------------------------

    public function test_a_post_is_never_redirected_because_that_would_drop_the_body(): void
    {
        // Yönlendirilen bir POST, kullanıcının doldurduğu formu sessizce siler.
        $response = $this->send('/logout/', 'POST');

        self::assertNotSame(301, $response->getStatusCode());
    }

    // --- URL-MW-DUPLICATE-11 -----------------------------------------------

    public function test_a_repeated_query_key_is_refused_rather_than_silently_resolved(): void
    {
        // PHP sonuncuyu, bazı ara katmanlar ilkini alır. İki katmanın aynı
        // isteği farklı okuması bir yetki kararını da değiştirebilir.
        $response = $this->send('/terms?sort=a&sort=b');

        self::assertSame(400, $response->getStatusCode());
    }

    public function test_php_array_syntax_is_still_accepted(): void
    {
        $response = $this->send('/terms?tags[]=a&tags[]=b');

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_a_normal_request_passes_through_untouched(): void
    {
        self::assertSame(200, $this->send('/terms')->getStatusCode());
        self::assertSame(200, $this->send('/')->getStatusCode());
    }
}
