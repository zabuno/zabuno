<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * S1-WP04b9 RED — tenant/location/menu isolation and QrCreate-permission
 * enforcement for POST .../tables/bulk, mirroring
 * tests/Feature/QrDestination/QrDestinationTenantIsolationTest.php: a
 * cross-tenant, foreign-location, or foreign-menu request gets an
 * enumeration-safe 404 (never 403 or a cross-tenant leak), and a real
 * workspace member who lacks qr.create gets 403. The route does not exist
 * yet, so every request below is expected to fail RED with a 404
 * route-not-found response first.
 *
 * Requirement IDs: BULKQR-TENANT-ESCAPE-FOREIGN-LOCATION-01,
 * BULKQR-TENANT-ESCAPE-FOREIGN-MENU-01, BULKQR-TENANT-ESCAPE-NONMEMBER-01,
 * BULKQR-AUTHZ-MEMBER-FORBIDDEN-01.
 */
final class BulkQrTenantIsolationTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * @return array{0: int, 1: int, 2: int} [workspaceId, locationId, menuId]
     */
    private function workspaceWithCurrentPublication(User $owner, string $slugSeed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$slugSeed,
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
            'name' => 'Marka '.$slugSeed,
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
            'display_name' => 'Şube '.$slugSeed,
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$slugSeed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            'name' => 'Starters',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Kahve',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 4250,
            'currency_code' => 'TRY',
            'position' => 0,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => json_encode([
                'categories' => [
                    ['name' => 'Starters', 'menuItems' => [['productName' => 'Kahve', 'priceMinorAmount' => 4250, 'currencyCode' => 'TRY']]],
                ],
            ]),
            'published_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId,
            'current_publication_id' => $publicationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Owner 2026-08-26'da bu yeteneği plana bağladı; testler
        // yazıldığında ücretsizdi. Kurulum burada, kurucunun içinde yapılır ki
        // her test kendi plan kurgusunu tekrar yazmasın.
        $this->grantEntitlements($workspaceId);

        return [$workspaceId, $locationId, $menuId];
    }

    private function bulkUrl(int $workspaceId, int $locationId): string
    {
        return "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk";
    }

    private function assertZeroBulkPersistence(): void
    {
        self::assertDatabaseCount('dining_areas', 0);
        self::assertDatabaseCount('dining_tables', 0);
    }

    private function validPayload(int $menuId): array
    {
        return [
            'menuId' => $menuId,
            'areaSectionCount' => 1,
            'tableCount' => 5,
            'seatCountPerTable' => 2,
        ];
    }

    // --- BULKQR-TENANT-ESCAPE-FOREIGN-LOCATION-01 ---------------------------

    public function test_bulk_create_under_a_foreign_workspace_location_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        [$workspaceIdA] = $this->workspaceWithCurrentPublication($ownerA, 'bulk-tenant-a-1');

        $ownerB = $this->verifiedUser();
        [, $locationIdB, $menuIdB] = $this->workspaceWithCurrentPublication($ownerB, 'bulk-tenant-b-1');

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceIdA, $locationIdB),
            $this->validPayload($menuIdB)
        );

        $response->assertStatus(404, 'BULKQR-TENANT-ESCAPE-FOREIGN-LOCATION-01: workspaceA, workspaceB\'ye ait location için bulk create edememeli — 404, 403 değil.');
        $this->assertZeroBulkPersistence();
    }

    // --- BULKQR-TENANT-ESCAPE-FOREIGN-MENU-01 -------------------------------

    public function test_bulk_create_for_a_foreign_menu_under_the_own_location_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        [$workspaceIdA, $locationIdA] = $this->workspaceWithCurrentPublication($ownerA, 'bulk-tenant-a-2');

        $ownerB = $this->verifiedUser();
        [, , $menuIdB] = $this->workspaceWithCurrentPublication($ownerB, 'bulk-tenant-b-2');

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceIdA, $locationIdA),
            $this->validPayload($menuIdB)
        );

        $response->assertStatus(404, 'BULKQR-TENANT-ESCAPE-FOREIGN-MENU-01: kendi location\'ı altında başka workspace\'in menu id\'si ile bulk create edilememeli — 404, 403 değil.');
        $this->assertZeroBulkPersistence();
    }

    // --- BULKQR-TENANT-ESCAPE-NONMEMBER-01 ----------------------------------

    public function test_a_workspace_nonmember_gets_enumeration_safe_404_with_real_ids(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-tenant-nonmember-1');

        $stranger = $this->verifiedUser();

        $response = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            $this->validPayload($menuId)
        );

        $response->assertStatus(404, 'BULKQR-TENANT-ESCAPE-NONMEMBER-01: workspace üyesi olmayan kullanıcı bulk create için 404 almalı, 403 değil.');
        $this->assertZeroBulkPersistence();
    }

    // --- BULKQR-AUTHZ-MEMBER-FORBIDDEN-01 ------------------------------------

    public function test_a_workspace_member_without_qr_create_permission_is_denied_with_403(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'bulk-tenant-member-1');

        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($member)->withHeaders($this->jsonHeaders())->postJson(
            $this->bulkUrl($workspaceId, $locationId),
            $this->validPayload($menuId)
        );

        $response->assertStatus(403, 'BULKQR-AUTHZ-MEMBER-FORBIDDEN-01: Member qr.create taşımaz, bulk POST 403 dönmeli.');
        $this->assertZeroBulkPersistence();
    }
}
