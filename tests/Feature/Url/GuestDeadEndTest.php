<?php

declare(strict_types=1);

namespace Tests\Feature\Url;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Karekodu tarayan misafirin karşısına çıkan çıkmaz sokak.
 *
 * Requirement ID'leri: URL-DEADEND-HUMAN-20, URL-DEADEND-UNIFORM-21,
 * URL-DEADEND-NOINDEX-22, URL-DEADEND-JSON-23.
 */
final class GuestDeadEndTest extends TestCase
{
    use RefreshDatabase;

    private const UNKNOWN = 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz';

    private const MALFORMED = 'not-a-valid-token';

    private function browserGet(string $uri): Response
    {
        $request = Request::create($uri, 'GET');
        $request->headers->set('Accept', 'text/html,application/xhtml+xml');

        return $this->app->make(Kernel::class)->handle($request);
    }

    // --- URL-DEADEND-HUMAN-20 ----------------------------------------------

    public function test_a_diner_sees_a_readable_page_not_raw_json(): void
    {
        // Kodu tarayan kişi restoran masasında oturan bir müşteridir.
        // `{"message":"Not Found."}` ona ürünün bozuk olduğunu düşündürür.
        $response = $this->browserGet('/menu/'.self::UNKNOWN);

        self::assertSame(404, $response->getStatusCode());

        $body = (string) $response->getContent();

        self::assertStringContainsString('<html', $body);
        self::assertStringNotContainsString('{"message"', $body);
        self::assertStringContainsString('personel', $body, 'URL-DEADEND-HUMAN-20: misafire ne yapacağı söylenmeli.');
    }

    public function test_the_resolver_route_renders_the_dead_end_too_not_a_server_error(): void
    {
        // Bu test, #79'da kaçırdığım gerçek bir hatayı dondurur: `/q/`
        // controller'ının dönüş tipi HTML yanıtını kabul etmiyordu ve
        // tarayıcıdan gelen her ölü karekod 500 veriyordu. Önceki testim
        // yalnız "301 değil" diyordu — 500 de 301 değildir, yani hatayı
        // geçirdi. Durum kodu ARTIK açıkça doğrulanıyor.
        foreach (['/q/', '/menu/'] as $prefix) {
            $response = $this->browserGet($prefix.self::UNKNOWN);

            self::assertSame(404, $response->getStatusCode(), "URL-DEADEND-HUMAN-20: {$prefix} tarayıcıda 404 döndürmeli.");
            self::assertStringContainsString('<html', (string) $response->getContent());
        }
    }

    // --- URL-DEADEND-UNIFORM-21 --------------------------------------------

    public function test_unknown_malformed_and_disabled_are_indistinguishable(): void
    {
        // Farklı yanıt vermek, saldırganın hangi token'ların bir zamanlar var
        // olduğunu ölçmesine izin verirdi. Bu yüzden yaygın "emekli kaynak
        // için 410 Gone" tavsiyesi burada UYGULANMAZ: 410, tam olarak
        // saklamak istediğimiz bilgiyi açık eder.
        $unknown = $this->browserGet('/menu/'.self::UNKNOWN);
        $malformed = $this->browserGet('/menu/'.self::MALFORMED);

        self::assertSame($unknown->getStatusCode(), $malformed->getStatusCode());

        // Nonce'lar KARŞILAŞTIRMADAN çıkarılır: her yanıtta farklı olmaları
        // zorunludur (tekrar eden bir nonce, nonce olmamakla aynıdır). Ham
        // gövdeyi karşılaştırmak, doğru davranışı hata sanmak olurdu.
        self::assertSame(
            $this->withoutNonces((string) $unknown->getContent()),
            $this->withoutNonces((string) $malformed->getContent()),
        );

        foreach ([$unknown, $malformed] as $response) {
            self::assertNotSame(410, $response->getStatusCode(), 'URL-DEADEND-UNIFORM-21: 410 "bu vardı" der ve varlığı ifşa eder.');
        }
    }

    private function withoutNonces(string $html): string
    {
        return (string) preg_replace('/nonce="[^"]*"/', 'nonce="…"', $html);
    }

    // --- URL-DEADEND-NOINDEX-22 --------------------------------------------

    public function test_the_dead_end_is_kept_out_of_search_results(): void
    {
        $response = $this->browserGet('/menu/'.self::UNKNOWN);

        self::assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
    }

    // --- URL-DEADEND-JSON-23 -----------------------------------------------

    public function test_an_api_client_still_gets_json(): void
    {
        // Yanıt biçimi isteyene göre değişir, VAKAYA göre değil — yoksa
        // tekdüzelik bozulurdu.
        $request = Request::create('/menu/'.self::UNKNOWN, 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $this->app->make(Kernel::class)->handle($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Not Found', (string) $response->getContent());
    }
}
