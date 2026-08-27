<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blind RED HTTP-delivery candidate for the price/currency validation rules
 * of the POST .../menu-items route in the S1-WP03 Menu Catalog API P2 slice
 * (see MenuApiJourneyTest for the frozen route + JSON contract this file
 * assumes; frozen scope per task instruction: the request carries a
 * decimal-string price plus an ISO-4217 currency code; the currency must
 * equal the owning workspace's Brand.currency; the number of fraction
 * digits in the price must not exceed the currency's official ISO-4217
 * minor-unit digit count — see App\Domain\Money\Money —
 * and both checks run, and reject with 422, before any conversion to minor
 * units is attempted, so no menu_items row is ever written for a rejected
 * request).
 *
 * None of the six Menu Catalog routes are registered in routes/api.php, so
 * every request below is expected to fail RED with a 404 route-not-found
 * response before any validation logic runs — not a logic assertion
 * failure or a syntax/bootstrap defect in this suite.
 *
 * Requirement IDs: MENU-API-PRICE-VALID-01, MENU-API-PRICE-FRACTION-01,
 * MENU-API-PRICE-CURRENCY-MISMATCH-01, MENU-API-PRICE-CURRENCY-UNKNOWN-01,
 * MENU-API-PRICE-NEGATIVE-01, MENU-API-PRICE-NO-WRITE-ON-REJECT-01.
 */
final class MenuApiCurrencyValidationTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * @return array{0: int, 1: int, 2: int} [workspaceId, locationId, categoryId]
     */
    private function workspaceWithCategory(User $owner, string $slugSeed, string $brandCurrency = 'TRY'): array
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

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
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

        return [$workspaceId, $locationId, $categoryId];
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function createProduct(User $owner, int $workspaceId, int $categoryId, string $name): int
    {
        return (int) $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/products",
            ['name' => $name]
        )->json('id');
    }

    // --- MENU-API-PRICE-VALID-01 --------------------------------------------

    public function test_a_valid_decimal_price_matching_the_brand_currency_is_accepted_and_converted_to_minor_units(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $categoryId] = $this->workspaceWithCategory($owner, 'zeytin-price-valid', 'TRY');
        $productId = $this->createProduct($owner, $workspaceId, $categoryId, 'Mercimek Çorbası');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items",
            ['productId' => $productId, 'price' => '45.90', 'currency' => 'TRY']
        );

        $response->assertStatus(201, 'PRICE-VALID-01: brand para birimiyle eşleşen geçerli decimal fiyat kabul edilmeli.');
        $response->assertJsonPath('priceMinorAmount', 4590, 'PRICE-VALID-01: "45.90" TRY minor unit\'e (2 basamak) doğru çevrilmeli.');
        $response->assertJsonPath('currencyCode', 'TRY');
    }

    // --- MENU-API-PRICE-FRACTION-01 -----------------------------------------

    public function test_a_price_with_more_fraction_digits_than_the_currency_allows_is_rejected_with_422_before_conversion(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $categoryId] = $this->workspaceWithCategory($owner, 'zeytin-price-fraction', 'TRY');
        $productId = $this->createProduct($owner, $workspaceId, $categoryId, 'Mercimek Çorbası');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items",
            ['productId' => $productId, 'price' => '45.999', 'currency' => 'TRY']
        );

        $response->assertStatus(422, 'PRICE-FRACTION-01: TRY için 2 basamaktan fazla ondalık basamak taşıyan fiyat 422 ile reddedilmeli.');
        $response->assertJsonValidationErrors('price');
        self::assertSame(0, DB::table('menu_items')->count(), 'PRICE-FRACTION-01: reddedilen istek menu_items tablosuna satır yazmamalı.');
    }

    // --- MENU-API-PRICE-CURRENCY-MISMATCH-01 --------------------------------

    public function test_a_currency_that_does_not_match_the_brand_currency_is_rejected_with_422_before_conversion(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $categoryId] = $this->workspaceWithCategory($owner, 'zeytin-price-currency-mismatch', 'TRY');
        $productId = $this->createProduct($owner, $workspaceId, $categoryId, 'Mercimek Çorbası');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items",
            ['productId' => $productId, 'price' => '45.90', 'currency' => 'USD']
        );

        $response->assertStatus(422, 'PRICE-CURRENCY-MISMATCH-01: brand.currency (TRY) ile eşleşmeyen para birimi 422 ile reddedilmeli.');
        $response->assertJsonValidationErrors('currency');
        self::assertSame(0, DB::table('menu_items')->count(), 'PRICE-CURRENCY-MISMATCH-01: reddedilen istek menu_items tablosuna satır yazmamalı.');
    }

    // --- MENU-API-PRICE-CURRENCY-UNKNOWN-01 ---------------------------------

    public function test_an_unknown_iso4217_currency_code_is_rejected_with_422(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $categoryId] = $this->workspaceWithCategory($owner, 'zeytin-price-currency-unknown', 'TRY');
        $productId = $this->createProduct($owner, $workspaceId, $categoryId, 'Mercimek Çorbası');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items",
            ['productId' => $productId, 'price' => '10.00', 'currency' => 'XXX']
        );

        $response->assertStatus(422, 'PRICE-CURRENCY-UNKNOWN-01: geçersiz ISO-4217 kodu 422 ile reddedilmeli.');
        $response->assertJsonValidationErrors('currency');
    }

    // --- MENU-API-PRICE-NEGATIVE-01 -----------------------------------------

    public function test_a_negative_or_zero_price_is_rejected_with_422(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $categoryId] = $this->workspaceWithCategory($owner, 'zeytin-price-negative', 'TRY');
        $productId = $this->createProduct($owner, $workspaceId, $categoryId, 'Mercimek Çorbası');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items",
            ['productId' => $productId, 'price' => '-5.00', 'currency' => 'TRY']
        );

        $response->assertStatus(422, 'PRICE-NEGATIVE-01: negatif fiyat 422 ile reddedilmeli.');
        $response->assertJsonValidationErrors('price');
        self::assertSame(0, DB::table('menu_items')->count(), 'PRICE-NEGATIVE-01: reddedilen istek menu_items tablosuna satır yazmamalı.');
    }

    // --- MENU-API-PRICE-CURRENCY-MISMATCH zero-fraction-digit currency -----

    public function test_a_zero_fraction_digit_currency_like_jpy_rejects_any_decimal_point(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $categoryId] = $this->workspaceWithCategory($owner, 'zeytin-price-jpy', 'JPY');
        $productId = $this->createProduct($owner, $workspaceId, $categoryId, 'Sushi Seti');

        $rejected = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items",
            ['productId' => $productId, 'price' => '500.50', 'currency' => 'JPY']
        );
        $rejected->assertStatus(422, 'PRICE-FRACTION-01: JPY 0 fraction digit taşır, "500.50" reddedilmeli.');
        $rejected->assertJsonValidationErrors('price');

        $accepted = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items",
            ['productId' => $productId, 'price' => '500', 'currency' => 'JPY']
        );
        $accepted->assertStatus(201, 'PRICE-VALID-01: JPY için tam sayı fiyat kabul edilmeli.');
        $accepted->assertJsonPath('priceMinorAmount', 500);
    }
}
