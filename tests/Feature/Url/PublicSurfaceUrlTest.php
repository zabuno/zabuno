<?php

declare(strict_types=1);

namespace Tests\Feature\Url;

use App\Domain\QrDestination\QrToken;
use App\Domain\Url\CanonicalUrl;
use App\Domain\Url\UrlPolicy;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Halka açık yüzeyin adres sözleşmesi.
 *
 * Requirement ID'leri: URL-CANONICAL-14, URL-CANONICAL-TRACKING-15,
 * URL-ROBOTS-16, URL-ROBOTS-QR-CRAWLABLE-17, URL-NOINDEX-18, URL-QR-CACHE-19.
 */
final class PublicSurfaceUrlTest extends TestCase
{
    use RefreshDatabase;

    private function send(string $uri): Response
    {
        return $this->app->make(Kernel::class)->handle(Request::create($uri, 'GET'));
    }

    // --- URL-CANONICAL-14 --------------------------------------------------

    public function test_the_canonical_address_is_absolute_and_carries_no_fragment(): void
    {
        $canonical = $this->app->make(CanonicalUrl::class)
            ->for('https://menu.example.com', '/menu/abc/');

        self::assertSame('https://menu.example.com/menu/abc', $canonical);
        self::assertStringNotContainsString('#', $canonical, 'URL-CANONICAL-14: kanonik adres fragment taşımaz.');
    }

    // --- URL-CANONICAL-TRACKING-15 -----------------------------------------

    public function test_tracking_parameters_are_excluded_from_the_canonical_address(): void
    {
        // Bir menü, Instagram'dan mı yoksa doğrudan mı açıldığına göre farklı
        // bir sayfa değildir.
        $canonical = $this->app->make(CanonicalUrl::class)->for(
            'https://menu.example.com',
            '/menu/abc',
            ['utm_source' => 'instagram', 'fbclid' => 'x'],
        );

        self::assertSame('https://menu.example.com/menu/abc', $canonical);
    }

    // --- URL-ROBOTS-16 -----------------------------------------------------

    public function test_robots_is_generated_from_the_policy_not_hand_written(): void
    {
        $response = $this->send('/robots.txt');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();

        foreach ($this->app->make(UrlPolicy::class)->disallowPrefixes() as $prefix) {
            self::assertStringContainsString('Disallow: /'.$prefix.'/', $body);
        }
    }

    // --- URL-ROBOTS-QR-CRAWLABLE-17 ----------------------------------------

    public function test_the_qr_resolver_is_left_crawlable_on_purpose(): void
    {
        // Engellenirse bot `noindex` başlığını hiç okuyamaz ve başka bir
        // yerden link verilmiş bir /q/ adresi içeriksiz indekslenebilir.
        $body = (string) $this->send('/robots.txt')->getContent();

        self::assertStringNotContainsString('Disallow: /q/', $body, 'URL-ROBOTS-QR-CRAWLABLE-17: /q/ engellenirse noindex okunamaz.');
        self::assertStringContainsString('Allow: /q/', $body);
    }

    // --- URL-NOINDEX-18 ----------------------------------------------------

    public function test_management_surfaces_say_do_not_index_and_public_ones_do_not(): void
    {
        self::assertStringContainsString('noindex', (string) $this->send('/app')->headers->get('X-Robots-Tag'));
        self::assertStringContainsString('noindex', (string) $this->send('/platform')->headers->get('X-Robots-Tag'));

        self::assertNull($this->send('/terms')->headers->get('X-Robots-Tag'), 'URL-NOINDEX-18: herkese açık sayfa indekslenebilir kalmalı.');
        self::assertNull($this->send('/')->headers->get('X-Robots-Tag'));
    }

    // --- URL-QR-CACHE-19 ---------------------------------------------------

    public function test_the_resolver_refuses_to_be_cached_because_a_printed_code_cannot_be_recalled(): void
    {
        // Önbelleklenen bir yönlendirme, masadaki basılı kodu eski hedefe
        // kilitler ve bunu geri almanın yolu yoktur. Bu yüzden kaynağı değil,
        // GERÇEK yanıtı ölçüyoruz.
        $token = QrToken::generate()->value();
        $this->qrCodeWithToken($token);

        $response = $this->send('/q/'.$token);

        self::assertSame(302, $response->getStatusCode(), 'URL-QR-CACHE-19: 301 kalıcı önbelleklenir; QR hedefi değişebilir olmalı.');
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringEndsWith('/menu/'.$token, (string) $response->headers->get('Location'));
        self::assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
    }

    private function qrCodeWithToken(string $token): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran', 'slug' => 'url-qr-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Marka', 'slug' => 'url-qr-brand-'.uniqid(),
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId, 'display_name' => 'Şube',
            'country_code' => 'TR', 'city' => 'İstanbul', 'address_line1' => 'Adres',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'workspace_id' => $workspaceId, 'location_id' => $locationId, 'name' => 'Ana Menü',
            'state' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId, 'menu_id' => $menuId, 'location_id' => $locationId,
            'version' => 1, 'state' => 'published', 'snapshot' => json_encode(['categories' => []]),
            'published_by' => $owner->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId, 'current_publication_id' => $publicationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
            'workspace_id' => $workspaceId, 'location_id' => $locationId, 'token' => $token,
            'state' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $destinationId = (int) DB::table('qr_destinations')->insertGetId([
            'qr_code_id' => $qrCodeId, 'destination_type' => 'published_menu', 'menu_id' => $menuId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('qr_code_current_destinations')->insert([
            'qr_code_id' => $qrCodeId, 'qr_destination_id' => $destinationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
