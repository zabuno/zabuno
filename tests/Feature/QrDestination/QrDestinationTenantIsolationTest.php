<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * S1-WP04b1 RED — tenant/location/menu isolation for the QR Destination
 * admin routes, mirroring tests/Feature/Publication/
 * PublicationTenantIsolationTest.php: a cross-tenant, foreign-location, or
 * foreign-menu request gets an enumeration-safe 404, never 403 or a
 * cross-tenant token/snapshot leak. Neither route exists yet, so every
 * request below fails RED with a 404 route-not-found response first.
 *
 * Requirement IDs: QR-TENANT-ESCAPE-FOREIGN-LOCATION-01,
 * QR-TENANT-ESCAPE-FOREIGN-MENU-01, QR-TENANT-ESCAPE-NONMEMBER-01,
 * QR-TENANT-ESCAPE-DISABLE-01.
 */
final class QrDestinationTenantIsolationTest extends TestCase
{
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
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$slugSeed,
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
            'snapshot' => json_encode(['categories' => [['name' => 'Starters', 'menuItems' => [['productName' => 'Kahve', 'priceMinorAmount' => 4250, 'currencyCode' => 'TRY']]]]]),
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

        return [$workspaceId, $locationId, $menuId];
    }

    // --- QR-TENANT-ESCAPE-FOREIGN-LOCATION-01 ---------------------------------

    public function test_creating_a_qr_code_under_a_foreign_workspace_location_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        [$workspaceIdA] = $this->workspaceWithCurrentPublication($ownerA, 'tenant-a-qr');

        $ownerB = $this->verifiedUser();
        [, $locationIdB, $menuIdB] = $this->workspaceWithCurrentPublication($ownerB, 'tenant-b-qr');

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceIdA}/brand/locations/{$locationIdB}/qr-codes",
            ['menuId' => $menuIdB]
        );

        $response->assertStatus(404, 'QR-TENANT-ESCAPE-FOREIGN-LOCATION-01: workspaceA, workspaceB\'ye ait location için QR create edememeli — 404, 403 değil.');
    }

    // --- QR-TENANT-ESCAPE-FOREIGN-MENU-01 --------------------------------------

    public function test_creating_a_qr_code_for_a_foreign_menu_under_the_own_location_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        [$workspaceIdA, $locationIdA] = $this->workspaceWithCurrentPublication($ownerA, 'tenant-a-qr-2');

        $ownerB = $this->verifiedUser();
        [, , $menuIdB] = $this->workspaceWithCurrentPublication($ownerB, 'tenant-b-qr-2');

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceIdA}/brand/locations/{$locationIdA}/qr-codes",
            ['menuId' => $menuIdB]
        );

        $response->assertStatus(404, 'QR-TENANT-ESCAPE-FOREIGN-MENU-01: kendi location\'ı altında başka workspace\'in menu id\'si ile QR create edilememeli — 404, 403 değil.');
    }

    // --- QR-TENANT-ESCAPE-NONMEMBER-01 -----------------------------------------

    public function test_a_workspace_nonmember_gets_enumeration_safe_404_on_create_list_and_disable_with_real_ids(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'tenant-nonmember-qr');

        $created = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        )->assertStatus(201);
        $qrCodeId = $created->json('id');

        $stranger = $this->verifiedUser();

        $create = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        );
        $create->assertStatus(404, 'QR-TENANT-ESCAPE-NONMEMBER-01: workspace üyesi olmayan kullanıcı create için 404 almalı, 403 değil.');

        $list = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes"
        );
        $list->assertStatus(404, 'QR-TENANT-ESCAPE-NONMEMBER-01: workspace üyesi olmayan kullanıcı list için 404 almalı, 403 değil.');

        $disable = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/disable"
        );
        $disable->assertStatus(404, 'QR-TENANT-ESCAPE-DISABLE-01: workspace üyesi olmayan kullanıcı disable için 404 almalı, 403 değil.');
    }

    // --- QR-TENANT-ESCAPE-DISABLE-01 --------------------------------------------

    public function test_disabling_a_foreign_workspace_qr_code_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        [$workspaceIdA] = $this->workspaceWithCurrentPublication($ownerA, 'tenant-a-qr-3');

        $ownerB = $this->verifiedUser();
        [$workspaceIdB, $locationIdB, $menuIdB] = $this->workspaceWithCurrentPublication($ownerB, 'tenant-b-qr-3');

        $created = $this->actingAs($ownerB)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceIdB}/brand/locations/{$locationIdB}/qr-codes",
            ['menuId' => $menuIdB]
        )->assertStatus(201);
        $qrCodeIdB = $created->json('id');

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceIdA}/qr-codes/{$qrCodeIdB}/disable"
        );

        $response->assertStatus(404, 'QR-TENANT-ESCAPE-DISABLE-01: workspaceA, workspaceB\'nin QR kodunu disable edememeli — 404, 403 değil.');
        self::assertDatabaseHas('qr_codes', ['id' => $qrCodeIdB, 'state' => 'active']);
    }
}
