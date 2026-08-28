<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * S1-WP04a RED — tenant/location/menu isolation for the Publication routes,
 * mirroring tests/Feature/MenuCatalog/MenuApiTenantEscapeTest.php: a
 * cross-tenant or foreign-menu request gets an enumeration-safe 404, never
 * 403 or a cross-tenant snapshot leak. Neither route exists yet, so every
 * request below fails RED with a 404 route-not-found response first.
 *
 * Requirement IDs: PUB-TENANT-ESCAPE-FOREIGN-MENU-01,
 * PUB-TENANT-ESCAPE-NONMEMBER-01, PUB-TENANT-ESCAPE-CURRENT-01.
 */
final class PublicationTenantIsolationTest extends TestCase
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
     * @return array{0: int, 1: int} [workspaceId, menuId]
     */
    private function workspaceWithReadyMenu(User $owner, string $slugSeed): array
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

        return [$workspaceId, $menuId];
    }

    // --- PUB-TENANT-ESCAPE-FOREIGN-MENU-01 ---------------------------------

    public function test_publishing_a_foreign_workspace_menu_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        [$workspaceIdA] = $this->workspaceWithReadyMenu($ownerA, 'tenant-a-pub');

        $ownerB = $this->verifiedUser();
        [, $menuIdB] = $this->workspaceWithReadyMenu($ownerB, 'tenant-b-pub');

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceIdA}/menu/{$menuIdB}/publications"
        );

        $response->assertStatus(404, 'PUB-TENANT-ESCAPE-FOREIGN-MENU-01: workspaceA, workspaceB\'ye ait menü için publish edememeli — 404, 403 değil.');
    }

    public function test_reading_current_publication_of_a_foreign_workspace_menu_is_enumeration_safe_404(): void
    {
        $ownerA = $this->verifiedUser();
        [$workspaceIdA] = $this->workspaceWithReadyMenu($ownerA, 'tenant-a-pub-2');

        $ownerB = $this->verifiedUser();
        [$workspaceIdB, $menuIdB] = $this->workspaceWithReadyMenu($ownerB, 'tenant-b-pub-2');

        $this->actingAs($ownerB)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceIdB}/menu/{$menuIdB}/publications"
        )->assertStatus(201);

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceIdA}/menu/{$menuIdB}/publications/current"
        );

        $response->assertStatus(404, 'PUB-TENANT-ESCAPE-CURRENT-01: workspaceA, workspaceB\'nin publication snapshot\'ını okuyamamalı — 404, 403 değil.');
        self::assertStringNotContainsString('Kahve', (string) $response->getContent(), 'PUB-TENANT-ESCAPE-CURRENT-01: cross-tenant snapshot içeriği sızmamalı.');
    }

    // --- PUB-TENANT-ESCAPE-NONMEMBER-01 -------------------------------------

    public function test_a_workspace_nonmember_gets_enumeration_safe_404_on_publish_and_current_even_with_real_ids(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $menuId] = $this->workspaceWithReadyMenu($owner, 'tenant-nonmember-pub');

        $stranger = $this->verifiedUser();

        $publish = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        );
        $publish->assertStatus(404, 'PUB-TENANT-ESCAPE-NONMEMBER-01: workspace üyesi olmayan kullanıcı publish için 404 almalı, 403 değil.');

        $current = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current"
        );
        $current->assertStatus(404, 'PUB-TENANT-ESCAPE-NONMEMBER-01: workspace üyesi olmayan kullanıcı current için 404 almalı, 403 değil.');
    }
}
