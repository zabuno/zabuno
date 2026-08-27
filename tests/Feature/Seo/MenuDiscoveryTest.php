<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Domain\Publication\MenuIndexability;
use App\Domain\Publication\MenuPublicAddress;
use App\Domain\QrDestination\QrToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `URL-SEO-v1` Faz 1 — menü keşfi.
 *
 * Owner kararı "menüler arama motorunda görünsün" idi. Menü sayfalarına iç
 * bağlantı yoktur: bir menüye ya basılı bir karekodla ya da bu katmanla
 * ulaşılır. Bu faz olmadan karar kâğıt üstünde kalırdı.
 *
 * Requirement ID'leri: SEO-SITEMAP-01, SEO-NO-TOKEN-02,
 * SEO-INDEXABILITY-03, SEO-CONSISTENT-04, SEO-JSONLD-05, SEO-LANG-06.
 */
final class MenuDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    // --- SEO-SITEMAP-01 ----------------------------------------------------

    public function test_a_published_menu_with_content_appears_in_the_sitemap(): void
    {
        [$key, $slug] = $this->publishedMenu(true);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        self::assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('/menu/'.$key.'/'.$slug, (string) $response->getContent());
    }

    public function test_the_sitemap_also_carries_the_marketing_pages(): void
    {
        $xml = (string) $this->get('/sitemap.xml')->getContent();

        foreach (['/terms', '/privacy', '/kvkk'] as $path) {
            self::assertStringContainsString($path, $xml);
        }
    }

    public function test_robots_announces_where_the_sitemap_is(): void
    {
        self::assertStringContainsString('Sitemap:', (string) $this->get('/robots.txt')->getContent());
    }

    // --- SEO-NO-TOKEN-02 ---------------------------------------------------

    public function test_no_qr_token_ever_reaches_the_sitemap(): void
    {
        [, , $token] = $this->publishedMenu(true);

        $xml = (string) $this->get('/sitemap.xml')->getContent();

        self::assertStringNotContainsString($token, $xml, 'SEO-NO-TOKEN-02: QR token sitemap\'e sızdı.');
        self::assertStringNotContainsString('/q/', $xml);
    }

    // --- SEO-INDEXABILITY-03 ----------------------------------------------

    public function test_an_empty_menu_is_kept_out_of_the_index(): void
    {
        [$key] = $this->publishedMenu(false);

        self::assertStringNotContainsString('/menu/'.$key, (string) $this->get('/sitemap.xml')->getContent());
    }

    public function test_the_gate_measures_what_a_guest_would_actually_see(): void
    {
        self::assertFalse(MenuIndexability::isIndexable(['categories' => []]));
        self::assertFalse(MenuIndexability::isIndexable(['categories' => [['name' => 'Sıcak', 'menuItems' => []]]]));
        self::assertTrue(MenuIndexability::isIndexable([
            'categories' => [['name' => 'Sıcak', 'menuItems' => [['productName' => 'Kahve']]]],
        ]));
    }

    public function test_removing_every_item_and_republishing_takes_the_menu_back_out(): void
    {
        [$key, , , $menuId] = $this->publishedMenu(true);

        self::assertTrue((bool) DB::table('menus')->where('id', $menuId)->value('is_indexable'));

        $this->republish($menuId, ['categories' => []]);

        self::assertFalse((bool) DB::table('menus')->where('id', $menuId)->value('is_indexable'));
        self::assertStringNotContainsString('/menu/'.$key, (string) $this->get('/sitemap.xml')->getContent());
    }

    // --- SEO-CONSISTENT-04 -------------------------------------------------

    public function test_the_sitemap_and_the_page_never_disagree(): void
    {
        [$key, $slug] = $this->publishedMenu(true);

        $xml = (string) $this->get('/sitemap.xml')->getContent();
        $page = $this->get('/menu/'.$key.'/'.$slug);

        self::assertStringContainsString('/menu/'.$key.'/'.$slug, $xml);
        self::assertNull($page->headers->get('X-Robots-Tag'), 'SEO-CONSISTENT-04: sitemap\'teki sayfa noindex dönüyor.');
    }

    // --- SEO-JSONLD-05 -----------------------------------------------------

    public function test_the_menu_page_describes_itself_with_structured_data(): void
    {
        [$key, $slug] = $this->publishedMenu(true);

        $html = (string) $this->get('/menu/'.$key.'/'.$slug)->getContent();

        self::assertSame(
            1,
            preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches),
            'SEO-JSONLD-05: yapılandırılmış veri bloğu yok.'
        );

        $data = json_decode(html_entity_decode($matches[1]), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('https://schema.org', $data['@context']);
        self::assertSame('Restaurant', $data['@type']);
        self::assertSame('Liman', $data['name']);

        $item = $data['hasMenu']['hasMenuSection'][0]['hasMenuItem'][0];

        self::assertSame('Kahve', $item['name']);
        self::assertSame('45.00', $item['offers']['price']);
        self::assertSame('TRY', $item['offers']['priceCurrency']);
        self::assertStringContainsString('Kahve', $html);
    }

    // --- SEO-LANG-06 -------------------------------------------------------

    public function test_the_page_declares_the_language_the_menu_is_written_in(): void
    {
        [$key, $slug] = $this->publishedMenu(true);

        $html = (string) $this->get('/menu/'.$key.'/'.$slug)->getContent();

        self::assertStringContainsString('<html lang="tr"', $html);
    }

    /** @param array<string, mixed> $snapshot */
    private function republish(int $menuId, array $snapshot): void
    {
        $menu = DB::table('menus')->where('id', $menuId)->first();

        $this->app->make(PublicationRepositoryPort::class)->publish(
            (int) $menu->workspace_id,
            $menuId,
            (int) $menu->location_id,
            $snapshot,
            (int) DB::table('users')->min('id'),
        );
    }

    /** @return array{0: string, 1: string, 2: string, 3: int} */
    private function publishedMenu(bool $withItems): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'seo-'.uniqid(), 'state' => 'active',
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
            'name' => 'Ana Menü', 'state' => 'draft', 'is_indexable' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->republish($menuId, $withItems
            ? ['categories' => [['name' => 'Sıcak', 'menuItems' => [
                ['productName' => 'Kahve', 'priceMinorAmount' => 4500, 'currencyCode' => 'TRY', 'allergens' => []],
            ]]]]
            : ['categories' => []]);

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

        return [$key, 'liman-kahve', $token, $menuId];
    }
}
