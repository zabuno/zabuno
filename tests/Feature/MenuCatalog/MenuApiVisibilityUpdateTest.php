<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED HTTP-delivery candidate for the S1-WP03 Menu Catalog API
 * visibility slice (frozen scope per task instruction — mirrors
 * MenuApiPriceUpdateTest's fixtures and auth/enumeration-safety
 * conventions on the visibility path):
 *
 *   GET  /api/workspaces/{workspace}/brand/locations/{location}/menu
 *     -> each menuItems[] entry additionally carries "isVisible": boolean
 *
 *   PUT /api/workspaces/{workspace}/menu-items/{menuItem}/visibility
 *     body: {"isVisible": boolean}
 *     -> 200 {"id": int, "categoryId": int, "productId": int,
 *             "isVisible": bool, "position": int}
 *
 * Same auth/verified/membership and enumeration-safe tenant/IDOR behavior
 * as PUT .../price (UpdateMenuItemPriceController): Permission::MenuView
 * gates read/existence (its absence or a cross-workspace menuItem id is an
 * enumeration-safe 404, never 403), Permission::MenuManage gates the write
 * (a viewer-only Member gets 403). A missing or non-boolean isVisible is
 * rejected with 422 before any write. A successful update changes only
 * the target row's is_visible flag and leaves id/category/product/position
 * untouched, with no duplicate menu_items row, and the field is
 * immediately visible on the next GET of the menu tree.
 *
 * Neither the isVisible projection on ShowMenuController's menuItems[]
 * entries nor any App\Http\Controllers\MenuCatalog\UpdateMenuItemVisibilityController
 * / PUT .../menu-items/{menuItem}/visibility route exists yet (grep
 * confirms only .../allergens and .../price are registered on the
 * {menuItem} resource, and ShowMenuController's menuItems[] map omits
 * isVisible), so every test below is expected to fail RED due to the
 * missing route (404 route-not-found) or the missing GET field
 * (assertJsonPath("categories.0.menuItems.0.isVisible", ...) failing),
 * not due to an authorization-route-independent assertion.
 *
 * Requirement IDs: MENU-API-VISIBILITY-GET-PROJECTION-01,
 * MENU-API-VISIBILITY-UPDATE-SUCCESS-01,
 * MENU-API-VISIBILITY-UPDATE-ROUNDTRIP-01,
 * MENU-API-VISIBILITY-UPDATE-PRESERVES-OTHER-FIELDS-01,
 * MENU-API-VISIBILITY-UPDATE-VALIDATION-01,
 * MENU-API-VISIBILITY-UPDATE-RBAC-01, MENU-API-VISIBILITY-UPDATE-ESCAPE-01,
 * MENU-API-VISIBILITY-UPDATE-NONMEMBER-01,
 * MENU-API-VISIBILITY-UPDATE-GUEST-01.
 */
final class MenuApiVisibilityUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * @return array{0: int, 1: int} [workspaceId, locationId]
     */
    private function workspaceWithLocation(User $owner, string $slugSeed, string $brandCurrency = 'TRY'): array
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
            'currency' => $brandCurrency,
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
    private function fullMenuTreeFor(int $workspaceId, int $locationId, bool $isVisible = false): array
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
            'is_visible' => $isVisible,
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

    private function menuUri(int $workspaceId, int $locationId): string
    {
        return "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu";
    }

    private function visibilityUri(int $workspaceId, int $menuItemId): string
    {
        return "/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/visibility";
    }

    // --- MENU-API-VISIBILITY-GET-PROJECTION-01 -------------------------------

    public function test_the_menu_tree_get_response_exposes_is_visible_on_each_menu_item(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-visibility-get-projection');
        $this->fullMenuTreeFor($workspaceId, $locationId, false);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            $this->menuUri($workspaceId, $locationId)
        );

        $response->assertStatus(200, 'VISIBILITY-GET-PROJECTION-01: menü ağacı GET isteği 200 dönmeli.');
        $response->assertJsonPath('categories.0.menuItems.0.isVisible', false, 'VISIBILITY-GET-PROJECTION-01: her menuItems girdisi isVisible alanı taşımalı.');
    }

    // --- MENU-API-VISIBILITY-UPDATE-SUCCESS-01 -------------------------------
    // --- MENU-API-VISIBILITY-UPDATE-PRESERVES-OTHER-FIELDS-01 ----------------

    public function test_owner_can_flip_visibility_from_false_to_true(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-visibility-update-false-to-true');
        [, $categoryId, $productId, $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, false);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->visibilityUri($workspaceId, $menuItemId),
            ['isVisible' => true]
        );

        $response->assertStatus(200, 'VISIBILITY-UPDATE-SUCCESS-01: geçerli görünürlük güncellemesi 200 dönmeli.');
        $response->assertJsonPath('id', $menuItemId);
        $response->assertJsonPath('categoryId', $categoryId);
        $response->assertJsonPath('productId', $productId);
        $response->assertJsonPath('isVisible', true);
        $response->assertJsonPath('position', 1);

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertNotNull($row, 'VISIBILITY-UPDATE-PRESERVES-OTHER-FIELDS-01: satır silinmemeli.');
        self::assertSame(1, (int) $row->is_visible);
        self::assertSame($categoryId, (int) $row->category_id, 'VISIBILITY-UPDATE-PRESERVES-OTHER-FIELDS-01: category_id değişmemeli.');
        self::assertSame($productId, (int) $row->product_id, 'VISIBILITY-UPDATE-PRESERVES-OTHER-FIELDS-01: product_id değişmemeli.');
        self::assertSame(1, (int) $row->position, 'VISIBILITY-UPDATE-PRESERVES-OTHER-FIELDS-01: position değişmemeli.');
        self::assertSame(4500, (int) $row->price_minor_amount, 'VISIBILITY-UPDATE-PRESERVES-OTHER-FIELDS-01: price değişmemeli.');

        self::assertSame(
            1,
            DB::table('menu_items')->where('category_id', $categoryId)->where('product_id', $productId)->count(),
            'VISIBILITY-UPDATE-PRESERVES-OTHER-FIELDS-01: güncelleme yinelenen bir satır yazmamalı.'
        );
    }

    public function test_owner_can_flip_visibility_from_true_to_false(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-visibility-update-true-to-false');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, true);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->visibilityUri($workspaceId, $menuItemId),
            ['isVisible' => false]
        );

        $response->assertStatus(200, 'VISIBILITY-UPDATE-SUCCESS-01: geçerli görünürlük güncellemesi 200 dönmeli.');
        $response->assertJsonPath('isVisible', false);

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertSame(0, (int) $row->is_visible);
    }

    // --- MENU-API-VISIBILITY-UPDATE-ROUNDTRIP-01 ------------------------------

    public function test_a_visibility_update_is_reflected_on_the_next_menu_tree_get(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-visibility-update-roundtrip');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, false);

        $put = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->visibilityUri($workspaceId, $menuItemId),
            ['isVisible' => true]
        );
        $put->assertStatus(200);

        $get = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            $this->menuUri($workspaceId, $locationId)
        );

        $get->assertStatus(200);
        $get->assertJsonPath('categories.0.menuItems.0.isVisible', true, 'VISIBILITY-UPDATE-ROUNDTRIP-01: PUT sonrası GET yeni değeri göstermeli.');
    }

    // --- MENU-API-VISIBILITY-UPDATE-VALIDATION-01 -----------------------------

    public function test_a_missing_is_visible_field_is_rejected_with_422_and_writes_nothing(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-visibility-update-missing-field');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, false);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->visibilityUri($workspaceId, $menuItemId),
            []
        );

        $response->assertStatus(422, 'VISIBILITY-UPDATE-VALIDATION-01: isVisible alanı eksikse 422 dönmeli.');
        $response->assertJsonValidationErrors('isVisible');

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertSame(0, (int) $row->is_visible, 'VISIBILITY-UPDATE-VALIDATION-01: reddedilen istek görünürlüğü değiştirmemeli.');
    }

    public function test_a_non_boolean_is_visible_value_is_rejected_with_422_and_writes_nothing(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-visibility-update-non-boolean');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, false);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->visibilityUri($workspaceId, $menuItemId),
            ['isVisible' => 'yes']
        );

        $response->assertStatus(422, 'VISIBILITY-UPDATE-VALIDATION-01: boolean olmayan isVisible değeri 422 dönmeli.');
        $response->assertJsonValidationErrors('isVisible');

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertSame(0, (int) $row->is_visible, 'VISIBILITY-UPDATE-VALIDATION-01: reddedilen istek görünürlüğü değiştirmemeli.');
    }

    // --- MENU-API-VISIBILITY-UPDATE-RBAC-01 -----------------------------------

    public function test_member_with_view_only_gets_403_forbidden_on_visibility_update(): void
    {
        $owner = $this->verifiedUser();
        $member = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-visibility-update-rbac');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, false);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($member)->withHeaders($this->jsonHeaders())->putJson(
            $this->visibilityUri($workspaceId, $menuItemId),
            ['isVisible' => true]
        );

        $response->assertStatus(403, 'VISIBILITY-UPDATE-RBAC-01: menu.manage yetkisi olmayan Member görünürlük güncelleyememeli.');

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertSame(0, (int) $row->is_visible, 'VISIBILITY-UPDATE-RBAC-01: reddedilen istek görünürlüğü değiştirmemeli.');
    }

    // --- MENU-API-VISIBILITY-UPDATE-ESCAPE-01 ---------------------------------

    public function test_updating_the_visibility_of_a_foreign_workspace_menu_item_is_enumeration_safe_404_and_writes_nothing(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA] = $this->workspaceWithLocation($ownerA, 'zeytin-visibility-update-escape-a');
        [$workspaceB, $locationB] = $this->workspaceWithLocation($ownerB, 'zeytin-visibility-update-escape-b');
        [, , , $menuItemB] = $this->fullMenuTreeFor($workspaceB, $locationB, false);

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->putJson(
            $this->visibilityUri($workspaceA, $menuItemB),
            ['isVisible' => true]
        );

        $response->assertStatus(404, 'VISIBILITY-UPDATE-ESCAPE-01: workspaceA, workspaceB\'ye ait menu item\'ın görünürlüğünü güncelleyememeli — 404, 403 değil.');

        $row = DB::table('menu_items')->where('id', $menuItemB)->first();
        self::assertSame(0, (int) $row->is_visible, 'VISIBILITY-UPDATE-ESCAPE-01: reddedilen cross-tenant istek görünürlüğü değiştirmemeli.');
    }

    public function test_a_workspace_nonmember_gets_enumeration_safe_404_even_though_the_menu_item_id_is_real(): void
    {
        $owner = $this->verifiedUser();
        $nonmember = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-visibility-update-nonmember');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, false);

        $response = $this->actingAs($nonmember)->withHeaders($this->jsonHeaders())->putJson(
            $this->visibilityUri($workspaceId, $menuItemId),
            ['isVisible' => true]
        );

        $response->assertStatus(404, 'VISIBILITY-UPDATE-NONMEMBER-01: workspace üyesi olmayan kullanıcı 404 almalı, 403 değil.');
    }

    // --- MENU-API-VISIBILITY-UPDATE-GUEST-01 ----------------------------------

    public function test_guest_is_unauthenticated_on_visibility_update(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-visibility-update-guest');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, false);

        $response = $this->withHeaders($this->jsonHeaders())->putJson(
            $this->visibilityUri($workspaceId, $menuItemId),
            ['isVisible' => true]
        );

        $response->assertStatus(401, 'VISIBILITY-UPDATE-GUEST-01: kimliksiz istek 401 dönmeli.');
    }
}
