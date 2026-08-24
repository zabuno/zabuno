<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED HTTP-delivery candidate for cross-tenant escape-scope coverage
 * of the S1-WP03 Menu Catalog API P2 slice (see MenuApiJourneyTest for the
 * frozen route + JSON contract this file assumes; frozen scope per task
 * instruction: every nested id in the six Menu Catalog routes — location,
 * menu, category, product, menu-item — must be enumeration-safe: a
 * workspace-A caller naming a workspace-B id anywhere in the URL gets 404,
 * never 403 or a cross-tenant payload leak, mirroring the P1 write-side
 * tenant guard already covered in
 * tests/Feature/MenuCatalog/MenuCatalogTenantEscapeTest.php).
 *
 * None of the six Menu Catalog routes are registered in routes/api.php, so
 * every request below is expected to fail RED with a 404 route-not-found
 * response before any RBAC/tenant-guard logic runs — not a logic assertion
 * failure or a syntax/bootstrap defect in this suite.
 *
 * Requirement IDs: MENU-API-ESCAPE-LOCATION-01, MENU-API-ESCAPE-MENU-01,
 * MENU-API-ESCAPE-CATEGORY-01, MENU-API-ESCAPE-PRODUCT-01,
 * MENU-API-ESCAPE-ITEM-01.
 */
final class MenuApiTenantEscapeTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * @return array{0: int, 1: int} [workspaceId, locationId]
     */
    private function workspaceWithLocation(User $owner, string $slugSeed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Kadıköy Şubesi',
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} [menuId, categoryId, productId, menuItemId]
     */
    private function fullMenuTreeFor(int $workspaceId, int $locationId): array
    {
        $menuId = (int) DB::table('menus')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Çorbalar',
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Mercimek Çorbası',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuItemId = (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 4500,
            'currency_code' => 'TRY',
            'is_visible' => false,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$menuId, $categoryId, $productId, $menuItemId];
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    // --- MENU-API-ESCAPE-LOCATION-01 ---------------------------------------

    public function test_creating_and_reading_a_menu_against_a_foreign_workspace_location_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA] = $this->workspaceWithLocation($ownerA, 'zeytin-escape-location-a');
        [, $locationB] = $this->workspaceWithLocation($ownerB, 'zeytin-escape-location-b');

        $create = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceA}/brand/locations/{$locationB}/menu",
            ['name' => 'Yabancı Location Menüsü']
        );
        $create->assertStatus(404, 'ESCAPE-LOCATION-01: workspaceA, workspaceB\'ye ait location için menu oluşturamamalı — 404, 403 değil.');

        $read = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceA}/brand/locations/{$locationB}/menu"
        );
        $read->assertStatus(404, 'ESCAPE-LOCATION-01: workspaceA, workspaceB\'ye ait location\'ın menüsünü okuyamamalı — 404, 403 değil.');
    }

    // --- MENU-API-ESCAPE-MENU-01 --------------------------------------------

    public function test_adding_a_category_to_a_foreign_workspace_menu_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA] = $this->workspaceWithLocation($ownerA, 'zeytin-escape-menu-a');
        [$workspaceB, $locationB] = $this->workspaceWithLocation($ownerB, 'zeytin-escape-menu-b');
        [$menuB] = $this->fullMenuTreeFor($workspaceB, $locationB);

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceA}/menu/{$menuB}/categories",
            ['name' => 'Yabancı Menü Kategorisi']
        );

        $response->assertStatus(404, 'ESCAPE-MENU-01: workspaceA, workspaceB\'ye ait menü için kategori ekleyememeli — 404, 403 değil.');
    }

    // --- MENU-API-ESCAPE-CATEGORY-01 ----------------------------------------

    public function test_adding_a_product_to_a_foreign_workspace_category_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA] = $this->workspaceWithLocation($ownerA, 'zeytin-escape-category-a');
        [$workspaceB, $locationB] = $this->workspaceWithLocation($ownerB, 'zeytin-escape-category-b');
        [, $categoryB] = $this->fullMenuTreeFor($workspaceB, $locationB);

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceA}/menu-categories/{$categoryB}/products",
            ['name' => 'Yabancı Ürün']
        );

        $response->assertStatus(404, 'ESCAPE-CATEGORY-01: workspaceA, workspaceB\'ye ait kategoriye ürün ekleyememeli — 404, 403 değil.');
    }

    // --- MENU-API-ESCAPE-PRODUCT-01 -----------------------------------------

    public function test_adding_a_foreign_workspace_product_as_a_menu_item_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA, $locationA] = $this->workspaceWithLocation($ownerA, 'zeytin-escape-product-a');
        [$workspaceB, $locationB] = $this->workspaceWithLocation($ownerB, 'zeytin-escape-product-b');
        [, , $productB] = $this->fullMenuTreeFor($workspaceB, $locationB);
        [, $categoryA] = $this->fullMenuTreeFor($workspaceA, $locationA);

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceA}/menu-categories/{$categoryA}/menu-items",
            ['productId' => $productB, 'price' => '10.00', 'currency' => 'TRY']
        );

        $response->assertStatus(404, 'ESCAPE-PRODUCT-01: workspaceA, workspaceB\'ye ait ürünü kendi kategorisine menu item olarak ekleyememeli — 404, 403 değil.');
    }

    // --- MENU-API-ESCAPE-ITEM-01 --------------------------------------------

    public function test_updating_allergens_on_a_foreign_workspace_menu_item_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA] = $this->workspaceWithLocation($ownerA, 'zeytin-escape-item-a');
        [$workspaceB, $locationB] = $this->workspaceWithLocation($ownerB, 'zeytin-escape-item-b');
        [, , , $menuItemB] = $this->fullMenuTreeFor($workspaceB, $locationB);

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceA}/menu-items/{$menuItemB}/allergens",
            ['allergens' => ['gluten']]
        );

        $response->assertStatus(404, 'ESCAPE-ITEM-01: workspaceA, workspaceB\'ye ait menu item\'ın alerjenlerini güncelleyememeli — 404, 403 değil.');
    }

    // --- Nonmember enumeration-safety across every route --------------------

    public function test_a_workspace_nonmember_gets_enumeration_safe_404_on_every_route_even_though_the_ids_are_real(): void
    {
        $owner = $this->verifiedUser();
        $nonmember = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-escape-nonmember');
        [$menuId, $categoryId, $productId, $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId);

        $routes = [
            ['GET', "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu", []],
            ['POST', "/api/workspaces/{$workspaceId}/menu/{$menuId}/categories", ['name' => 'X']],
            ['POST', "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/products", ['name' => 'X']],
            ['POST', "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items", ['productId' => $productId, 'price' => '1.00', 'currency' => 'TRY']],
            ['PUT', "/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/allergens", ['allergens' => []]],
        ];

        foreach ($routes as [$method, $uri, $payload]) {
            $response = $this->actingAs($nonmember)->withHeaders($this->jsonHeaders())->json($method, $uri, $payload);
            $response->assertStatus(404, "ESCAPE-NONMEMBER-01: {$method} {$uri} workspace üyesi olmayan kullanıcı için 404 dönmeli, 403 değil.");
        }
    }
}
