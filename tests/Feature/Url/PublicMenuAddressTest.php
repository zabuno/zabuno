<?php

declare(strict_types=1);

namespace Tests\Feature\Url;

use App\Domain\Publication\MenuPublicAddress;
use App\Domain\QrDestination\QrToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Menünün kalıcı herkese açık adresi — owner kararı: menüler arama
 * motorunda görünsün (2026-08-27).
 *
 * Kararın mimari sonucu: QR token'ı bu adres OLAMAZ. Token basılmış bir
 * kodun anahtarıdır ve `/q/` yüzeyi bilerek hız sınırlıdır; onu sitemap'te
 * yayımlamak, taranmasını engellemeye çalıştığımız uzayı toplu hâlde
 * teslim etmek olurdu.
 *
 * Requirement ID'leri: MENU-ADDR-STABLE-27, MENU-ADDR-SELF-HEAL-28,
 * MENU-ADDR-TOKEN-SPLIT-29, MENU-ADDR-FUNNEL-30.
 */
final class PublicMenuAddressTest extends TestCase
{
    use RefreshDatabase;

    // --- MENU-ADDR-STABLE-27 -----------------------------------------------

    public function test_the_key_is_the_identity_and_the_slug_is_only_readability(): void
    {
        $address = MenuPublicAddress::create('abc1234567', 'Çiğköfteci Ömer Şubesi');

        self::assertSame('abc1234567', $address->key);
        self::assertSame('cigkofteci-omer-subesi', $address->slug);
        self::assertSame('/menu/abc1234567/cigkofteci-omer-subesi', $address->path());
    }

    public function test_a_nameless_menu_still_has_a_working_address(): void
    {
        // Uydurulmuş bir slug, yanlış bir slug'dan iyi değildir.
        self::assertSame('/menu/abc1234567', MenuPublicAddress::create('abc1234567', '   ')->path());
    }

    public function test_a_malformed_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MenuPublicAddress::create('TOO-SHORT', 'Kafe');
    }

    public function test_generated_keys_are_not_sequential(): void
    {
        // Sıralı bir kimlik, platformdaki toplam işletme sayısını herkese
        // açık biçimde ilan eder.
        $keys = [];

        for ($index = 0; $index < 25; $index++) {
            $keys[] = MenuPublicAddress::generateKey();
        }

        self::assertCount(25, array_unique($keys));

        foreach ($keys as $key) {
            self::assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $key);
        }
    }

    public function test_the_domain_layer_carries_no_framework_dependency(): void
    {
        // Alan katmanı çerçeveye bağlanamaz; bu kural yazılırken gerçekten
        // ihlal edildi ve `OnionBoundaryTest` yakaladı.
        $source = (string) file_get_contents(app_path('Domain/Publication/MenuPublicAddress.php'));

        self::assertStringNotContainsString('Illuminate', $source);
    }

    // --- MENU-ADDR-SELF-HEAL-28 --------------------------------------------

    public function test_a_stale_slug_is_permanently_moved_to_the_current_one(): void
    {
        [$key, $slug] = $this->publishedMenu();

        $response = $this->get('/menu/'.$key.'/eski-isim');

        // Restoran adını değiştirdiğinde paylaşılmış her bağlantı ölmez;
        // kendini onarır.
        $response->assertStatus(301);
        $response->assertRedirect('/menu/'.$key.'/'.$slug);
    }

    public function test_the_bare_key_also_reaches_the_canonical_address(): void
    {
        [$key, $slug] = $this->publishedMenu();

        $this->get('/menu/'.$key)->assertRedirect('/menu/'.$key.'/'.$slug);
    }

    public function test_the_canonical_address_renders_the_published_menu(): void
    {
        [$key, $slug] = $this->publishedMenu();

        $response = $this->get('/menu/'.$key.'/'.$slug);

        $response->assertStatus(200);
        self::assertStringContainsString('Kahve', (string) $response->getContent());
    }

    // --- MENU-ADDR-TOKEN-SPLIT-29 ------------------------------------------

    public function test_the_token_page_points_search_engines_at_the_canonical_address(): void
    {
        [$key, $slug, $token] = $this->publishedMenu();

        $response = $this->get('/menu/'.$token);

        $response->assertStatus(200);
        self::assertStringContainsString('rel="canonical" href="', (string) $response->getContent());
        self::assertStringContainsString('/menu/'.$key.'/'.$slug, (string) $response->getContent());
        self::assertStringContainsString(
            'noindex',
            (string) $response->headers->get('X-Robots-Tag'),
            'MENU-ADDR-TOKEN-SPLIT-29: token sayfası indekslenirse token arama sonuçlarına düşer.'
        );
    }

    // --- MENU-ADDR-FUNNEL-30 -----------------------------------------------

    public function test_a_scan_still_lands_on_the_attributed_page_so_the_funnel_survives(): void
    {
        [, , $token] = $this->publishedMenu();

        // Taramayı kanonik adrese göndermek, "QR çözümlemesi → menü açılışı"
        // hunisinin ikinci yarısını ölçülemez hâle getirirdi: kanonik sayfa
        // hangi karekodun getirdiğini bilmez.
        $this->get('/q/'.$token)->assertRedirect('/menu/'.$token);

        $this->get('/menu/'.$token)->assertStatus(200);

        self::assertGreaterThan(
            0,
            DB::table('analytics_events')->where('event_type', 'menu_open')->count(),
            'MENU-ADDR-FUNNEL-30: menü açılışı ölçülmüyor — ürünün birincil metriği kayboldu.'
        );
    }

    /** @return array{0: string, 1: string, 2: string} [key, slug, token] */
    private function publishedMenu(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'addr-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Liman', 'slug' => 'liman-'.uniqid(),
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId, 'display_name' => 'Kahve',
            'country_code' => 'TR', 'city' => 'İstanbul', 'address_line1' => 'Adres',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $key = MenuPublicAddress::generateKey();

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => $key, 'workspace_id' => $workspaceId, 'location_id' => $locationId,
            'name' => 'Ana Menü', 'state' => 'draft', 'is_indexable' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId, 'menu_id' => $menuId, 'location_id' => $locationId,
            'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['categories' => [[
                'name' => 'Sıcak',
                'menuItems' => [['productName' => 'Kahve', 'priceMinorAmount' => 4500, 'currencyCode' => 'TRY', 'allergens' => []]],
            ]]]),
            'published_by' => $owner->id, 'published_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId, 'current_publication_id' => $publicationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $token = QrToken::generate()->value();

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

        return [$key, 'liman-kahve', $token];
    }
}
