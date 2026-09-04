<?php

declare(strict_types=1);

namespace Tests\Feature\Url;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TENANT-URL-ITEM-01 — FF-116, `docs/105` §4.3.
 *
 * Sahibin ilk örneği `#item=101` idi. Fragment sunucuya HİÇ ulaşmaz:
 * indekslenmez, ayrı bir görüntüleme olarak ölçülemez ve paylaşılan bağlantıda
 * hangi ürün olduğu sunucu tarafından bilinemez. Ürünün gerçek adresi bir yol
 * segmentidir:
 *
 *     /restoran/pasa-doner/menu/ab12cd34ef/urun/101-adana-kebap
 *
 * Kalite kapısı: anlatacak şeyi olmayan bir ürün sayfası indekslenmez. Adı ve
 * fiyatı olan ama açıklaması, görseli ve alerjeni olmayan bir sayfa, menüdeki
 * satırın kopyasıdır — programatik SEO'nun tam olarak yapmaması gereken şey.
 */
final class PublicMenuItemPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_item_page_shows_the_dish_and_links_back_to_the_full_menu(): void
    {
        ['key' => $key, 'slug' => $slug, 'richId' => $id] = $this->publishedMenu();

        $response = $this->get("/restoran/{$slug}/menu/{$key}/urun/{$id}-adana-kebap");

        $response->assertStatus(200);
        $html = (string) $response->getContent();

        self::assertStringContainsString('Adana Kebap', $html);
        self::assertStringContainsString('Acılı, elde kıyılmış', $html, 'TENANT-URL-ITEM-01: ürünün açıklaması sayfanın asıl içeriğidir.');
        // Çıkmaz sokak olmaz: misafir tam menüye dönebilmeli.
        self::assertStringContainsString("/restoran/{$slug}/menu/{$key}", $html);
    }

    public function test_a_wrong_dish_slug_is_permanently_moved_not_broken(): void
    {
        ['key' => $key, 'slug' => $slug, 'richId' => $id] = $this->publishedMenu();

        $this->get("/restoran/{$slug}/menu/{$key}/urun/{$id}-eski-isim")
            ->assertStatus(301)
            ->assertRedirect("/restoran/{$slug}/menu/{$key}/urun/{$id}-adana-kebap");
    }

    public function test_a_dish_that_does_not_exist_is_the_same_dead_end_as_any_other(): void
    {
        ['key' => $key, 'slug' => $slug] = $this->publishedMenu();

        // Rota şeklini ifşa eden özel bir hata metni yok; tekdüze çıkmaz sokak.
        $this->get("/restoran/{$slug}/menu/{$key}/urun/999999-yok")->assertStatus(404);
    }

    public function test_a_dish_with_nothing_to_say_is_not_indexable(): void
    {
        /*
            KALİTE KAPISI. Adı ve fiyatı olan ama başka hiçbir şeyi olmayan bir
            ürün sayfası, menüdeki satırın kopyasıdır. Böyle yüzlerce sayfayı
            aramaya açmak, alan adının kalitesini düşürür ve restorana da bir
            faydası olmaz.
        */
        ['key' => $key, 'slug' => $slug, 'thinId' => $id] = $this->publishedMenu();

        $response = $this->get("/restoran/{$slug}/menu/{$key}/urun/{$id}-sade-cay");

        $response->assertStatus(200);
        self::assertStringContainsString(
            'noindex',
            (string) $response->headers->get('X-Robots-Tag'),
        );
    }

    public function test_a_dish_with_real_content_is_indexable_and_describes_itself(): void
    {
        ['key' => $key, 'slug' => $slug, 'richId' => $id] = $this->publishedMenu();

        $response = $this->get("/restoran/{$slug}/menu/{$key}/urun/{$id}-adana-kebap");
        $html = (string) $response->getContent();

        self::assertStringNotContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
        self::assertStringContainsString('"@type":"MenuItem"', $html);
        self::assertStringContainsString(
            "rel=\"canonical\" href=\"http://localhost:8000/restoran/{$slug}/menu/{$key}/urun/{$id}-adana-kebap\"",
            $html,
        );
    }

    public function test_the_menu_page_links_to_dishes_that_have_more_to_show(): void
    {
        ['key' => $key, 'slug' => $slug, 'richId' => $rich, 'thinId' => $thin] = $this->publishedMenu();

        $html = (string) $this->get("/restoran/{$slug}/menu/{$key}")->getContent();

        // Anlatacak şeyi olan ürün tıklanabilir…
        self::assertStringContainsString("/urun/{$rich}-adana-kebap", $html);
        // …olmayan ürün DEĞİL. Hiçbir yere götürmeyen bir bağlantı, bir yalan.
        self::assertStringNotContainsString("/urun/{$thin}-", $html);
    }

    public function test_the_menu_page_still_answers_a_plain_fragment(): void
    {
        // `#item=101` sunucuya ulaşmaz; sayfanın kendi çıpası çalışmalı.
        ['key' => $key, 'slug' => $slug, 'richId' => $id] = $this->publishedMenu();

        $html = (string) $this->get("/restoran/{$slug}/menu/{$key}")->getContent();

        self::assertStringContainsString("id=\"item-{$id}\"", $html);
    }

    /** @return array{key: string, slug: string, richId: int, thinId: int} */
    private function publishedMenu(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Paşa', 'slug' => 'item-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Paşa', 'slug' => 'pasa-'.uniqid(),
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId, 'display_name' => 'Döner',
            'country_code' => 'TR', 'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Adres', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => 'ab12cd34ef', 'workspace_id' => $workspaceId, 'location_id' => $locationId,
            'name' => 'Ana Menü', 'state' => 'published', 'is_indexable' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $snapshot = [
            'categories' => [[
                'name' => 'Kebaplar',
                'menuItems' => [
                    [
                        'menuItemId' => 101,
                        'productName' => 'Adana Kebap',
                        'description' => 'Acılı, elde kıyılmış kuzu eti.',
                        'priceMinorAmount' => 42000,
                        'currencyCode' => 'TRY',
                        'allergens' => ['gluten'],
                    ],
                    [
                        'menuItemId' => 202,
                        'productName' => 'Sade Çay',
                        'priceMinorAmount' => 1500,
                        'currencyCode' => 'TRY',
                        'allergens' => [],
                    ],
                ],
            ]],
        ];

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId, 'menu_id' => $menuId, 'location_id' => $locationId,
            'version' => 1, 'state' => 'published', 'snapshot' => json_encode($snapshot),
            'published_by' => $owner->id, 'published_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId, 'current_publication_id' => $publicationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['key' => 'ab12cd34ef', 'slug' => 'pasa-doner', 'richId' => 101, 'thinId' => 202];
    }
}
