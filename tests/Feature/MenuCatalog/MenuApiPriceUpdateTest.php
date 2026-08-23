<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED HTTP-delivery candidate for the S1-WP03 Menu Catalog API
 * price-update slice (frozen scope per task instruction — see
 * MenuApiJourneyTest for the sibling routes' frozen contract this file's
 * conventions mirror, and MenuApiCurrencyValidationTest /
 * MenuApiTenantEscapeTest for the validation and enumeration-safety
 * conventions this file reuses on the update path):
 *
 *   PUT /api/workspaces/{workspace}/menu-items/{menuItem}/price
 *     body: {"price": string (decimal), "currency": string (ISO-4217)}
 *     -> 200 {"id": int, "categoryId": int, "productId": int,
 *             "priceMinorAmount": int, "currencyCode": string, "position": int}
 *
 * Same auth/verified/membership and enumeration-safe tenant/IDOR behavior as
 * PUT .../allergens (UpdateMenuItemAllergensController): Permission::MenuView
 * gates read/existence (its absence or a cross-workspace menuItem id is an
 * enumeration-safe 404, never 403), Permission::MenuManage gates the write
 * (a viewer-only Member gets 403). Currency must match the owning brand's
 * currency and price fraction digits must not exceed the currency's
 * ISO-4217 minor-unit count, mirroring StoreMenuItemController's
 * validatePriceAndCurrency — rejected with 422 before any write. A
 * successful update changes only the target row's price/currency and
 * leaves id/category/product/position untouched, with no duplicate
 * menu_items row.
 *
 * No App\Http\Controllers\MenuCatalog\UpdateMenuItemPriceController exists
 * and no PUT .../menu-items/{menuItem}/price route is registered in
 * routes/api.php (grep confirms only .../allergens is registered on the
 * {menuItem} resource), so every request below is expected to fail RED
 * with a 404 route-not-found response — not a logic assertion failure.
 *
 * Requirement IDs: MENU-API-PRICE-UPDATE-SUCCESS-01,
 * MENU-API-PRICE-UPDATE-PRESERVES-OTHER-FIELDS-01,
 * MENU-API-PRICE-UPDATE-CURRENCY-MISMATCH-01,
 * MENU-API-PRICE-UPDATE-FRACTION-01, MENU-API-PRICE-UPDATE-NEGATIVE-01,
 * MENU-API-PRICE-UPDATE-UNKNOWN-CURRENCY-01,
 * MENU-API-PRICE-UPDATE-RBAC-01, MENU-API-PRICE-UPDATE-ESCAPE-01,
 * MENU-API-PRICE-UPDATE-GUEST-01.
 */
final class MenuApiPriceUpdateTest extends TestCase
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
    private function fullMenuTreeFor(int $workspaceId, int $locationId, int $priceMinorAmount = 4500, string $currencyCode = 'TRY'): array
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
            'price_minor_amount' => $priceMinorAmount,
            'currency_code' => $currencyCode,
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

    private function priceUri(int $workspaceId, int $menuItemId): string
    {
        return "/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/price";
    }

    // --- MENU-API-PRICE-UPDATE-SUCCESS-01 -----------------------------------
    // --- MENU-API-PRICE-UPDATE-PRESERVES-OTHER-FIELDS-01 --------------------

    public function test_owner_can_update_the_price_and_currency_of_an_existing_menu_item(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-update-success');
        [, $categoryId, $productId, $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, 4500, 'TRY');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceId, $menuItemId),
            ['price' => '52.50', 'currency' => 'TRY']
        );

        $response->assertStatus(200, 'PRICE-UPDATE-SUCCESS-01: geçerli fiyat güncellemesi 200 dönmeli.');
        $response->assertJsonPath('id', $menuItemId);
        $response->assertJsonPath('categoryId', $categoryId);
        $response->assertJsonPath('productId', $productId);
        $response->assertJsonPath('priceMinorAmount', 5250, 'PRICE-UPDATE-SUCCESS-01: "52.50" TRY minor unit\'e (2 basamak) doğru çevrilmeli.');
        $response->assertJsonPath('currencyCode', 'TRY');
        $response->assertJsonPath('position', 1);

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertNotNull($row, 'PRICE-UPDATE-PRESERVES-OTHER-FIELDS-01: satır silinmemeli.');
        self::assertSame(5250, (int) $row->price_minor_amount);
        self::assertSame('TRY', $row->currency_code);
        self::assertSame($categoryId, (int) $row->category_id, 'PRICE-UPDATE-PRESERVES-OTHER-FIELDS-01: category_id değişmemeli.');
        self::assertSame($productId, (int) $row->product_id, 'PRICE-UPDATE-PRESERVES-OTHER-FIELDS-01: product_id değişmemeli.');
        self::assertSame(1, (int) $row->position, 'PRICE-UPDATE-PRESERVES-OTHER-FIELDS-01: position değişmemeli.');

        self::assertSame(
            1,
            DB::table('menu_items')->where('category_id', $categoryId)->where('product_id', $productId)->count(),
            'PRICE-UPDATE-PRESERVES-OTHER-FIELDS-01: güncelleme yinelenen bir satır yazmamalı.'
        );
    }

    // --- MENU-API-PRICE-UPDATE-CURRENCY-MISMATCH-01 -------------------------

    public function test_a_currency_that_does_not_match_the_brand_currency_is_rejected_with_422_and_writes_nothing(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-update-currency-mismatch', 'TRY');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, 4500, 'TRY');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceId, $menuItemId),
            ['price' => '45.90', 'currency' => 'USD']
        );

        $response->assertStatus(422, 'PRICE-UPDATE-CURRENCY-MISMATCH-01: brand.currency (TRY) ile eşleşmeyen para birimi 422 ile reddedilmeli.');
        $response->assertJsonValidationErrors('currency');

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertSame(4500, (int) $row->price_minor_amount, 'PRICE-UPDATE-CURRENCY-MISMATCH-01: reddedilen istek fiyatı değiştirmemeli.');
        self::assertSame('TRY', $row->currency_code);
    }

    // --- MENU-API-PRICE-UPDATE-FRACTION-01 -----------------------------------

    public function test_a_price_with_more_fraction_digits_than_the_currency_allows_is_rejected_with_422_and_writes_nothing(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-update-fraction', 'TRY');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, 4500, 'TRY');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceId, $menuItemId),
            ['price' => '45.999', 'currency' => 'TRY']
        );

        $response->assertStatus(422, 'PRICE-UPDATE-FRACTION-01: TRY için 2 basamaktan fazla ondalık basamak taşıyan fiyat 422 ile reddedilmeli.');
        $response->assertJsonValidationErrors('price');

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertSame(4500, (int) $row->price_minor_amount, 'PRICE-UPDATE-FRACTION-01: reddedilen istek fiyatı değiştirmemeli.');
    }

    // --- MENU-API-PRICE-UPDATE-NEGATIVE-01 ------------------------------------

    public function test_a_zero_or_negative_price_is_rejected_with_422_and_writes_nothing(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-update-negative', 'TRY');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, 4500, 'TRY');

        $negative = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceId, $menuItemId),
            ['price' => '-5.00', 'currency' => 'TRY']
        );
        $negative->assertStatus(422, 'PRICE-UPDATE-NEGATIVE-01: negatif fiyat 422 ile reddedilmeli.');
        $negative->assertJsonValidationErrors('price');

        $zero = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceId, $menuItemId),
            ['price' => '0.00', 'currency' => 'TRY']
        );
        $zero->assertStatus(422, 'PRICE-UPDATE-NEGATIVE-01: sıfır fiyat 422 ile reddedilmeli.');
        $zero->assertJsonValidationErrors('price');

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertSame(4500, (int) $row->price_minor_amount, 'PRICE-UPDATE-NEGATIVE-01: reddedilen istek fiyatı değiştirmemeli.');
    }

    // --- MENU-API-PRICE-UPDATE-UNKNOWN-CURRENCY-01 ----------------------------

    public function test_an_unknown_iso4217_currency_code_is_rejected_with_422(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-update-unknown-currency', 'TRY');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, 4500, 'TRY');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceId, $menuItemId),
            ['price' => '10.00', 'currency' => 'XXX']
        );

        $response->assertStatus(422, 'PRICE-UPDATE-UNKNOWN-CURRENCY-01: geçersiz ISO-4217 kodu 422 ile reddedilmeli.');
        $response->assertJsonValidationErrors('currency');
    }

    // --- MENU-API-PRICE-UPDATE-RBAC-01 ----------------------------------------

    public function test_member_with_view_only_gets_403_forbidden_on_price_update(): void
    {
        $owner = $this->verifiedUser();
        $member = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-update-rbac');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, 4500, 'TRY');

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($member)->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceId, $menuItemId),
            ['price' => '99.00', 'currency' => 'TRY']
        );

        $response->assertStatus(403, 'PRICE-UPDATE-RBAC-01: menu.manage yetkisi olmayan Member fiyat güncelleyememeli.');

        $row = DB::table('menu_items')->where('id', $menuItemId)->first();
        self::assertSame(4500, (int) $row->price_minor_amount, 'PRICE-UPDATE-RBAC-01: reddedilen istek fiyatı değiştirmemeli.');
    }

    // --- MENU-API-PRICE-UPDATE-ESCAPE-01 --------------------------------------

    public function test_updating_the_price_of_a_foreign_workspace_menu_item_is_enumeration_safe_404_and_writes_nothing(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA] = $this->workspaceWithLocation($ownerA, 'zeytin-price-update-escape-a');
        [$workspaceB, $locationB] = $this->workspaceWithLocation($ownerB, 'zeytin-price-update-escape-b');
        [, , , $menuItemB] = $this->fullMenuTreeFor($workspaceB, $locationB, 4500, 'TRY');

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceA, $menuItemB),
            ['price' => '99.00', 'currency' => 'TRY']
        );

        $response->assertStatus(404, 'PRICE-UPDATE-ESCAPE-01: workspaceA, workspaceB\'ye ait menu item\'ın fiyatını güncelleyememeli — 404, 403 değil.');

        $row = DB::table('menu_items')->where('id', $menuItemB)->first();
        self::assertSame(4500, (int) $row->price_minor_amount, 'PRICE-UPDATE-ESCAPE-01: reddedilen cross-tenant istek fiyatı değiştirmemeli.');
    }

    public function test_a_workspace_nonmember_gets_enumeration_safe_404_even_though_the_menu_item_id_is_real(): void
    {
        $owner = $this->verifiedUser();
        $nonmember = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-update-nonmember');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, 4500, 'TRY');

        $response = $this->actingAs($nonmember)->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceId, $menuItemId),
            ['price' => '99.00', 'currency' => 'TRY']
        );

        $response->assertStatus(404, 'PRICE-UPDATE-NONMEMBER-01: workspace üyesi olmayan kullanıcı 404 almalı, 403 değil.');
    }

    // --- MENU-API-PRICE-UPDATE-GUEST-01 ---------------------------------------

    public function test_guest_is_unauthenticated_on_price_update(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-update-guest');
        [, , , $menuItemId] = $this->fullMenuTreeFor($workspaceId, $locationId, 4500, 'TRY');

        $response = $this->withHeaders($this->jsonHeaders())->putJson(
            $this->priceUri($workspaceId, $menuItemId),
            ['price' => '99.00', 'currency' => 'TRY']
        );

        $response->assertStatus(401, 'PRICE-UPDATE-GUEST-01: kimliksiz istek 401 dönmeli.');
    }
}
