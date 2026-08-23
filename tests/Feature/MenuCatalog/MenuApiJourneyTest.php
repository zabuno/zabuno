<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED HTTP-delivery candidate for the S1-WP03 Menu Catalog API P2
 * slice (frozen scope per task instruction: authenticated + verified JSON
 * contract for the six routes below, layered on top of the already-frozen
 * P1 MenuCatalogRepositoryPort persistence contract — see
 * tests/Feature/MenuCatalog/MenuDraftPersistenceTest.php and siblings).
 *
 * No App\Http\Controllers\MenuCatalog\* controller, no App\Http\Requests\
 * MenuCatalog\* request, and none of the routes below are registered in
 * routes/api.php (grep confirms only /workspaces, /workspace-context,
 * /workspaces/{workspace}/brand[/locations[/{location}]] exist today). Every
 * request below is therefore expected to fail RED with a 404 route-not-found
 * response (Laravel's default "no matching route" JSON), not a logic
 * assertion failure or a syntax/bootstrap defect in this suite.
 *
 * Frozen route + JSON contract under test (this file's own invented
 * placeholder, consistent with the existing Tenancy controller conventions
 * — App\Http\Controllers\Tenancy\StoreLocationController et al. — since no
 * WP03 delivery-contract doc exists yet):
 *
 *   POST /api/workspaces/{workspace}/brand/locations/{location}/menu
 *     body: {"name": string}
 *     -> 201 {"id": int, "workspaceId": int, "locationId": int,
 *             "name": string, "state": "draft"}
 *
 *   GET  /api/workspaces/{workspace}/brand/locations/{location}/menu
 *     -> 200 {"id": int, "workspaceId": int, "locationId": int,
 *             "name": string, "state": string,
 *             "categories": [{"id": int, "menuId": int, "name": string,
 *               "position": int,
 *               "menuItems": [{"id": int, "categoryId": int,
 *                 "productId": int, "productName": string,
 *                 "priceMinorAmount": int, "currencyCode": string,
 *                 "position": int, "allergens": list<string>}]}]}
 *
 *   POST /api/workspaces/{workspace}/menu/{menu}/categories
 *     body: {"name": string}
 *     -> 201 {"id": int, "menuId": int, "name": string, "position": int}
 *
 *   POST /api/workspaces/{workspace}/menu-categories/{category}/products
 *     body: {"name": string}
 *     -> 201 {"id": int, "workspaceId": int, "name": string}
 *
 *   POST /api/workspaces/{workspace}/menu-categories/{category}/menu-items
 *     body: {"productId": int, "price": string (decimal), "currency": string (ISO-4217)}
 *     -> 201 {"id": int, "categoryId": int, "productId": int,
 *             "priceMinorAmount": int, "currencyCode": string, "position": int}
 *
 *   PUT  /api/workspaces/{workspace}/menu-items/{menuItem}/allergens
 *     body: {"allergens": list<string> (taxonomy term names)}
 *     -> 200 {"id": int, "allergens": list<string>}
 *
 * RBAC (Permission::MenuView / Permission::MenuManage, see
 * tests/Unit/Authorization/RolePermissionMappingTest.php): Owner has both
 * and may read+write; Member has view-only and gets 403 on every write
 * route above; a non-member of the workspace and any request naming a
 * nested id (location/menu/category/product/menu-item) belonging to a
 * foreign workspace get an enumeration-safe 404, never a 403 (see
 * MenuApiTenantEscapeTest for the dedicated escape-scope coverage); an
 * unauthenticated or unverified caller gets 401.
 *
 * Requirement IDs: MENU-API-JOURNEY-CREATE-01, MENU-API-JOURNEY-CATEGORY-01,
 * MENU-API-JOURNEY-PRODUCT-01, MENU-API-JOURNEY-ITEM-01,
 * MENU-API-JOURNEY-ALLERGEN-01, MENU-API-JOURNEY-TREE-READ-01.
 */
final class MenuApiJourneyTest extends TestCase
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

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    // --- MENU-API-JOURNEY-CREATE-01 .. MENU-API-JOURNEY-TREE-READ-01 -------

    public function test_owner_can_persist_and_read_back_the_full_menu_category_product_item_allergen_tree_over_http(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-journey');

        $createMenu = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu",
            ['name' => 'Ana Menü']
        );

        $createMenu->assertStatus(201);
        $createMenu->assertJsonPath('workspaceId', $workspaceId);
        $createMenu->assertJsonPath('locationId', $locationId);
        $createMenu->assertJsonPath('name', 'Ana Menü');
        $createMenu->assertJsonPath('state', 'draft');
        $menuId = (int) $createMenu->json('id');
        self::assertGreaterThan(0, $menuId, 'JOURNEY-CREATE-01: oluşturulan menu id\'si pozitif olmalı.');

        $createCategory = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/categories",
            ['name' => 'Çorbalar']
        );

        $createCategory->assertStatus(201);
        $createCategory->assertJsonPath('menuId', $menuId);
        $createCategory->assertJsonPath('name', 'Çorbalar');
        $categoryId = (int) $createCategory->json('id');
        self::assertGreaterThan(0, $categoryId, 'JOURNEY-CATEGORY-01: oluşturulan category id\'si pozitif olmalı.');

        $createProduct = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/products",
            ['name' => 'Mercimek Çorbası']
        );

        $createProduct->assertStatus(201);
        $createProduct->assertJsonPath('workspaceId', $workspaceId);
        $createProduct->assertJsonPath('name', 'Mercimek Çorbası');
        $productId = (int) $createProduct->json('id');
        self::assertGreaterThan(0, $productId, 'JOURNEY-PRODUCT-01: oluşturulan product id\'si pozitif olmalı.');

        $createItem = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items",
            ['productId' => $productId, 'price' => '45.00', 'currency' => 'TRY']
        );

        $createItem->assertStatus(201);
        $createItem->assertJsonPath('categoryId', $categoryId);
        $createItem->assertJsonPath('productId', $productId);
        $createItem->assertJsonPath('priceMinorAmount', 4500);
        $createItem->assertJsonPath('currencyCode', 'TRY');
        $menuItemId = (int) $createItem->json('id');
        self::assertGreaterThan(0, $menuItemId, 'JOURNEY-ITEM-01: oluşturulan menu item id\'si pozitif olmalı.');

        $setAllergens = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/allergens",
            ['allergens' => ['gluten']]
        );

        $setAllergens->assertStatus(200);
        $setAllergens->assertJsonPath('id', $menuItemId);
        $setAllergens->assertJsonPath('allergens', ['gluten']);

        $readTree = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu"
        );

        $readTree->assertStatus(200);
        $readTree->assertJsonPath('id', $menuId);
        $readTree->assertJsonPath('categories.0.id', $categoryId);
        $readTree->assertJsonPath('categories.0.menuItems.0.id', $menuItemId);
        $readTree->assertJsonPath('categories.0.menuItems.0.productId', $productId);
        $readTree->assertJsonPath('categories.0.menuItems.0.priceMinorAmount', 4500);
        $readTree->assertJsonPath('categories.0.menuItems.0.currencyCode', 'TRY');
        $readTree->assertJsonPath('categories.0.menuItems.0.allergens', ['gluten']);
    }

    // --- MENU-API-JOURNEY-ALLERGEN-REPLACE-01 -------------------------------

    /**
     * PUT .../allergens is a full-replacement endpoint (RFC 7231 §4.3.4
     * semantics implied by the frozen contract's PUT verb and its
     * `-> 200 {"id": int, "allergens": list<string>}` response, which
     * echoes the *complete* resulting set — not merely the newly attached
     * terms). A caller that PUTs ["milk"] after ["gluten","milk"] expects
     * "gluten" gone from both the response and product_allergens; a caller
     * that PUTs [] expects all allergens cleared. The current
     * implementation (EloquentMenuCatalogRepository::attachAllergenToProduct)
     * only ever inserts and never deletes, so this must fail RED.
     */
    public function test_put_allergens_replaces_the_full_set_instead_of_only_adding(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-allergen-replace');

        $createMenu = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu",
            ['name' => 'Ana Menü']
        );
        $createMenu->assertStatus(201);
        $menuId = (int) $createMenu->json('id');

        $createCategory = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/categories",
            ['name' => 'Çorbalar']
        );
        $createCategory->assertStatus(201);
        $categoryId = (int) $createCategory->json('id');

        $createProduct = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/products",
            ['name' => 'Mercimek Çorbası']
        );
        $createProduct->assertStatus(201);
        $productId = (int) $createProduct->json('id');

        $createItem = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items",
            ['productId' => $productId, 'price' => '45.00', 'currency' => 'TRY']
        );
        $createItem->assertStatus(201);
        $menuItemId = (int) $createItem->json('id');

        $setBoth = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/allergens",
            ['allergens' => ['gluten', 'milk']]
        );
        $setBoth->assertStatus(200);
        $setBoth->assertJsonPath('id', $menuItemId);
        self::assertEqualsCanonicalizing(
            ['gluten', 'milk'],
            $setBoth->json('allergens'),
            'ALLERGEN-REPLACE-01: ilk PUT sonrası gluten ve milk her ikisi de dönmeli.'
        );

        $replaceWithMilkOnly = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/allergens",
            ['allergens' => ['milk']]
        );
        $replaceWithMilkOnly->assertStatus(200);
        self::assertSame(
            ['milk'],
            $replaceWithMilkOnly->json('allergens'),
            'ALLERGEN-REPLACE-01: ["milk"] ile PUT sonrası gluten kaldırılmalı, yalnız milk kalmalı.'
        );

        $glutenStillPersisted = DB::table('product_allergens')
            ->join('taxonomy_terms', 'taxonomy_terms.id', '=', 'product_allergens.taxonomy_term_id')
            ->where('product_allergens.product_id', $productId)
            ->where('taxonomy_terms.name', 'gluten')
            ->exists();
        self::assertFalse(
            $glutenStillPersisted,
            'ALLERGEN-REPLACE-01: gluten product_allergens tablosunda da silinmiş olmalı.'
        );

        $clearAll = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/allergens",
            ['allergens' => []]
        );
        $clearAll->assertStatus(200);
        self::assertSame(
            [],
            $clearAll->json('allergens'),
            'ALLERGEN-REPLACE-01: [] ile PUT sonrası tüm allergenler temizlenmeli.'
        );

        $remainingCount = DB::table('product_allergens')
            ->where('product_id', $productId)
            ->count();
        self::assertSame(
            0,
            $remainingCount,
            'ALLERGEN-REPLACE-01: product_allergens tablosunda bu ürün için hiç kayıt kalmamalı.'
        );
    }

    // --- RBAC over the same journey routes ---------------------------------

    public function test_member_can_read_but_gets_403_forbidden_on_every_write_route(): void
    {
        $owner = $this->verifiedUser();
        $member = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-journey-member');

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createMenuByMember = $this->actingAs($member)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu",
            ['name' => 'Ana Menü']
        );
        $createMenuByMember->assertStatus(403, 'JOURNEY-RBAC-01: menu.view yetkisi olan ama menu.manage yetkisi olmayan Member menu oluşturamamalı.');

        $menuId = (int) $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu",
            ['name' => 'Ana Menü']
        )->json('id');

        $readMenuByMember = $this->actingAs($member)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu"
        );
        $readMenuByMember->assertStatus(200, 'JOURNEY-RBAC-01: menu.view yetkisi olan Member menu tree\'sini okuyabilmeli.');

        $createCategoryByMember = $this->actingAs($member)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/categories",
            ['name' => 'Çorbalar']
        );
        $createCategoryByMember->assertStatus(403, 'JOURNEY-RBAC-01: Member category oluşturamamalı.');
    }

    public function test_guest_is_unauthenticated_and_nonmember_gets_enumeration_safe_not_found(): void
    {
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-journey-guest');

        $guestResponse = $this->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu",
            ['name' => 'Ana Menü']
        );
        $guestResponse->assertStatus(401, 'JOURNEY-RBAC-02: kimliksiz istek 401 dönmeli.');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu",
            ['name' => 'Ana Menü']
        );
        $strangerResponse->assertStatus(404, 'JOURNEY-RBAC-02: workspace üyesi olmayan kullanıcı enumeration-safe 404 almalı, 403 değil.');
    }
}
