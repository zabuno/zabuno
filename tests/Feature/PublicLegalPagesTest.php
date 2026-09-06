<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PUBLIC_LEGAL — yasal sayfalar sunucuda üretilir ve kabuğu giyer.
 *
 * Bu test bir zamanlar "hazırlanıyor" yer tutucusunu donduruyordu. FF-198
 * ile yer tutucu gitti: `/terms`, `/privacy`, `/kvkk` artık gerçek belgedir
 * (`LegalDocumentPagesTest` içeriği ölçer). Burada yalnız en küçük sözleşme
 * kaldı: rota var, oturum istemez, sunucuda üretilir, kabuk meta'sını taşır.
 */
final class PublicLegalPagesTest extends TestCase
{
    /**
     * Fixed, non-secret test-local key (32 raw bytes of 0x2A, base64-encoded).
     * phpunit.xml carries no APP_KEY, so the real config/.env stays untouched;
     * this only satisfies the encrypter used by the 'web' middleware group
     * (session/cookie encryption) so these routes can be exercised end-to-end.
     */
    private const TEST_APP_KEY = 'base64:KioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKio=';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => self::TEST_APP_KEY]);
    }

    /**
     * @return list<string>
     */
    public static function legalPathProvider(): array
    {
        return [
            'terms' => ['/terms'],
            'privacy' => ['/privacy'],
            'kvkk' => ['/kvkk'],
        ];
    }

    #[DataProvider('legalPathProvider')]
    public function test_unauthenticated_get_serves_the_server_rendered_document(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        // Sayfa sunucuda üretilir ve GERÇEK belgeyi taşır (FF-198).
        $response->assertSee('data-legal-document="', false);
        $response->assertSee('modules registered', false);
    }
}
