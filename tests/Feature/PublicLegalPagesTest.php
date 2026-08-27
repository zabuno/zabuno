<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PUBLIC_LEGAL_RED
 *
 * Freezes the smallest server-side contract for the synthesized Stage 1
 * legal-readiness pages: unauthenticated GET /terms, /privacy, /kvkk must
 * each resolve (not 404) and return the same React-mounting app shell as
 * GET '/' (sunucuda üretilen aynı kabuk, modül sayısını taşıyan
 * attribute), so the client-side AppShell can take over per-pathname
 * rendering. This test does not assert on rendered legal copy — that is
 * PublicLegalRoutes.test.tsx's job — only that the route exists and serves
 * the app mount.
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
    public function test_unauthenticated_get_returns_the_existing_app_mount(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        // Sayfa artık sunucuda üretilir: montaj noktası yerine METNİN
        // kendisi doğrulanır (bkz. FoundationStatusDeliveryArchitectureTest).
        $response->assertSee('pending qualified legal review', false);
        $response->assertSee('modules registered', false);
    }
}
