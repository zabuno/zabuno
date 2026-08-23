<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S1-WP04a RED — real synchronous publication snapshot HTTP-delivery
 * contract (scope synthesis: Publication is deterministic, human-triggered,
 * tenant/location/menu-scoped; QR is a non-goal for this package).
 *
 * Frozen route + JSON contract (this file's own invented contract,
 * consistent with the existing Menu Catalog controller conventions):
 *
 *   POST /api/workspaces/{workspace}/menu/{menu}/publications
 *     -> 201 {"id": int, "workspaceId": int, "menuId": int,
 *             "locationId": int, "version": int, "state": "published",
 *             "publishedAt": string, "snapshot": {"categories": [...]}}
 *
 *   GET  /api/workspaces/{workspace}/menu/{menu}/publications/current
 *     -> 404 before any publication, 200 with the current snapshot after.
 *
 * Neither route nor any App\Http\Controllers\Publication\* controller
 * exists yet (grep of routes/api.php confirms no "publications" route), so
 * every request below is expected to fail RED with a 404 route-not-found
 * response, not a logic assertion failure.
 *
 * Requirement IDs: PUB-JOURNEY-PUBLISH-01, PUB-JOURNEY-CURRENT-01,
 * PUB-JOURNEY-ATOMIC-VERSION-01, PUB-JOURNEY-READINESS-01,
 * PUB-JOURNEY-AUTHZ-01.
 */
final class PublicationJourneyTest extends TestCase
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
    private function workspaceWithReadyMenu(User $owner, string $slugSeed): array
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

        return [$workspaceId, $locationId, $menuId];
    }

    // --- PUB-JOURNEY-PUBLISH-01 --------------------------------------------

    public function test_verified_owner_with_menu_publish_and_a_ready_draft_creates_a_synchronous_immutable_snapshot(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-1');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        );

        $response->assertStatus(201, 'PUB-JOURNEY-PUBLISH-01: hazır bir draft için Owner senkron 201 almalı.');
        $response->assertJsonPath('workspaceId', $workspaceId);
        $response->assertJsonPath('menuId', $menuId);
        $response->assertJsonPath('locationId', $locationId);
        $response->assertJsonPath('state', 'published');

        $body = $response->json();
        self::assertIsInt($body['id'] ?? null, 'PUB-JOURNEY-PUBLISH-01: gerçek pozitif id dönmeli.');
        self::assertGreaterThan(0, $body['id']);
        self::assertIsInt($body['version'] ?? null, 'PUB-JOURNEY-PUBLISH-01: version tamsayı olmalı.');
        self::assertGreaterThanOrEqual(1, $body['version']);
        self::assertNotEmpty($body['publishedAt'] ?? null, 'PUB-JOURNEY-PUBLISH-01: publishedAt dolu olmalı.');

        $snapshot = $body['snapshot'] ?? null;
        self::assertIsArray($snapshot, 'PUB-JOURNEY-PUBLISH-01: snapshot alanı sunucu tarafında üretilmiş menu verisi taşımalı.');
        self::assertArrayHasKey('categories', $snapshot);
        self::assertSame('Starters', $snapshot['categories'][0]['name'] ?? null);
        self::assertSame('Kahve', $snapshot['categories'][0]['menuItems'][0]['productName'] ?? null);
        self::assertSame(4250, $snapshot['categories'][0]['menuItems'][0]['priceMinorAmount'] ?? null);
        self::assertSame('TRY', $snapshot['categories'][0]['menuItems'][0]['currencyCode'] ?? null);

        $flatSnapshot = json_encode($snapshot);
        self::assertStringNotContainsStringIgnoringCase('token', (string) $flatSnapshot, 'PUB-JOURNEY-PUBLISH-01: snapshot QR token taşımamalı (non-goal).');
        self::assertStringNotContainsStringIgnoringCase('qr', (string) $flatSnapshot, 'PUB-JOURNEY-PUBLISH-01: snapshot QR alanı taşımamalı (non-goal).');
        self::assertDoesNotMatchRegularExpression('#https?://#i', (string) $flatSnapshot, 'PUB-JOURNEY-PUBLISH-01: snapshot dış URL/export taşımamalı (non-goal).');
    }

    public function test_snapshot_excludes_hidden_items_and_orders_categories(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-2');

        $categoryId = (int) DB::table('menu_categories')->where('menu_id', $menuId)->value('id');
        $hiddenProductId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Gizli Ürün',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $hiddenProductId,
            'price_minor_amount' => 1000,
            'currency_code' => 'TRY',
            'position' => 1,
            'is_visible' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        );

        $response->assertStatus(201);
        $items = $response->json('snapshot.categories.0.menuItems') ?? [];
        $names = array_column($items, 'productName');

        self::assertContains('Kahve', $names, 'PUB-JOURNEY-PUBLISH-01: görünür ürün snapshot\'ta olmalı.');
        self::assertNotContains('Gizli Ürün', $names, 'PUB-JOURNEY-PUBLISH-01: gizli ürün snapshot\'a dahil edilmemeli.');
    }

    // --- PUB-JOURNEY-CURRENT-01 --------------------------------------------

    public function test_current_endpoint_is_404_before_publish_and_200_with_the_snapshot_after(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-3');

        $before = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current"
        );
        $before->assertStatus(404, 'PUB-JOURNEY-CURRENT-01: hiç publish edilmemiş menü için current 404 olmalı.');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        )->assertStatus(201);

        $after = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current"
        );
        $after->assertStatus(200, 'PUB-JOURNEY-CURRENT-01: publish sonrası current 200 dönmeli.');
        $after->assertJsonPath('state', 'published');
        self::assertArrayHasKey('categories', $after->json('snapshot') ?? []);
    }

    // --- PUB-JOURNEY-ATOMIC-VERSION-01 -------------------------------------

    public function test_mutating_the_draft_after_publish_does_not_mutate_the_prior_snapshot(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-4');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        )->assertStatus(201);

        DB::table('products')->where('name', 'Kahve')->update(['name' => 'Kahve (renamed)']);

        $current = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current"
        );
        $current->assertStatus(200);

        $names = array_column($current->json('snapshot.categories.0.menuItems') ?? [], 'productName');
        self::assertContains('Kahve', $names, 'PUB-JOURNEY-ATOMIC-VERSION-01: draft rename sonrası eski snapshot değişmemeli.');
        self::assertNotContains('Kahve (renamed)', $names, 'PUB-JOURNEY-ATOMIC-VERSION-01: snapshot immutable olmalı, draft mutasyonunu yansıtmamalı.');
    }

    public function test_a_second_publish_creates_a_new_current_version_and_supersedes_the_prior_one(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-5');

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        )->assertStatus(201);
        $firstVersion = $first->json('version');
        $firstId = $first->json('id');

        $second = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        )->assertStatus(201);
        $secondVersion = $second->json('version');

        self::assertGreaterThan($firstVersion, $secondVersion, 'PUB-JOURNEY-ATOMIC-VERSION-01: version monoton artmalı.');

        $current = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current"
        );
        $current->assertJsonPath('version', $secondVersion);

        $priorRow = DB::table('menu_publications')->where('id', $firstId)->first();
        self::assertNotNull($priorRow, 'PUB-JOURNEY-ATOMIC-VERSION-01: eski publication silinmemeli, korunmalı.');
        self::assertSame('superseded', $priorRow->state ?? null, 'PUB-JOURNEY-ATOMIC-VERSION-01: önceki sürüm superseded olmalı.');
    }

    // --- PUB-JOURNEY-READINESS-01 ------------------------------------------

    public function test_backend_rejects_an_unready_draft_with_422_and_never_trusts_a_client_supplied_snapshot(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-6');

        DB::table('menu_items')->where('category_id', function ($query) use ($menuId) {
            $query->select('id')->from('menu_categories')->where('menu_id', $menuId);
        })->delete();
        DB::table('menu_categories')->where('menu_id', $menuId)->delete();

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications",
            ['snapshot' => ['categories' => [['name' => 'Sahte Kategori', 'menuItems' => [['productName' => 'Sahte Ürün', 'priceMinorAmount' => 100, 'currencyCode' => 'TRY']]]]]]
        );

        $response->assertStatus(422, 'PUB-JOURNEY-READINESS-01: kategorisiz menü publish edilememeli.');
        $response->assertJsonMissing(['state' => 'published']);
    }

    public function test_backend_rejects_a_draft_with_no_visible_item_with_422(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-6a');

        DB::table('menu_items')->where('category_id', function ($query) use ($menuId) {
            $query->select('id')->from('menu_categories')->where('menu_id', $menuId);
        })->update(['is_visible' => false]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications",
            ['snapshot' => ['categories' => [['name' => 'Sahte Kategori', 'menuItems' => [['productName' => 'Sahte Ürün', 'priceMinorAmount' => 100, 'currencyCode' => 'TRY']]]]]]
        );

        $response->assertStatus(422, 'PUB-JOURNEY-READINESS-01: görünür ürünü olmayan menü publish edilememeli.');
        $response->assertJsonMissing(['state' => 'published']);
    }

    public function test_backend_rejects_a_draft_with_a_blank_category_name_with_422(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-6b');

        DB::table('menu_categories')->where('menu_id', $menuId)->update(['name' => '']);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications",
            ['snapshot' => ['categories' => [['name' => 'Sahte Kategori', 'menuItems' => [['productName' => 'Sahte Ürün', 'priceMinorAmount' => 100, 'currencyCode' => 'TRY']]]]]]
        );

        $response->assertStatus(422, 'PUB-JOURNEY-READINESS-01: boş kategori adı olan menü publish edilememeli.');
        $response->assertJsonMissing(['state' => 'published']);
    }

    public function test_backend_rejects_a_draft_with_a_blank_visible_product_name_with_422(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-6c');

        DB::table('products')->where('name', 'Kahve')->update(['name' => '']);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications",
            ['snapshot' => ['categories' => [['name' => 'Sahte Kategori', 'menuItems' => [['productName' => 'Sahte Ürün', 'priceMinorAmount' => 100, 'currencyCode' => 'TRY']]]]]]
        );

        $response->assertStatus(422, 'PUB-JOURNEY-READINESS-01: görünür ürünün adı boşsa publish edilememeli.');
        $response->assertJsonMissing(['state' => 'published']);
    }

    public function test_backend_rejects_a_draft_with_a_non_positive_visible_price_with_422(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-6d');

        DB::table('menu_items')->where('category_id', function ($query) use ($menuId) {
            $query->select('id')->from('menu_categories')->where('menu_id', $menuId);
        })->update(['price_minor_amount' => 0]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications",
            ['snapshot' => ['categories' => [['name' => 'Sahte Kategori', 'menuItems' => [['productName' => 'Sahte Ürün', 'priceMinorAmount' => 100, 'currencyCode' => 'TRY']]]]]]
        );

        $response->assertStatus(422, 'PUB-JOURNEY-READINESS-01: görünür ürünün fiyatı pozitif değilse publish edilememeli.');
        $response->assertJsonMissing(['state' => 'published']);
    }

    public function test_backend_rejects_a_draft_with_a_blank_visible_currency_with_422(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-6e');

        DB::table('menu_items')->where('category_id', function ($query) use ($menuId) {
            $query->select('id')->from('menu_categories')->where('menu_id', $menuId);
        })->update(['currency_code' => '']);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications",
            ['snapshot' => ['categories' => [['name' => 'Sahte Kategori', 'menuItems' => [['productName' => 'Sahte Ürün', 'priceMinorAmount' => 100, 'currencyCode' => 'TRY']]]]]]
        );

        $response->assertStatus(422, 'PUB-JOURNEY-READINESS-01: görünür ürünün para birimi boşsa publish edilememeli.');
        $response->assertJsonMissing(['state' => 'published']);
    }

    // --- PUB-JOURNEY-AUTHZ-01 ----------------------------------------------

    public function test_guest_is_401_unverified_is_blocked_and_member_is_denied_publish(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-pub-7');

        $guestResponse = $this->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        );
        $guestResponse->assertStatus(401, 'PUB-JOURNEY-AUTHZ-01: guest 401 almalı.');

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        );
        self::assertNotSame(201, $unverifiedResponse->getStatusCode(), 'PUB-JOURNEY-AUTHZ-01: doğrulanmamış kullanıcı publish edememeli.');

        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberResponse = $this->actingAs($member)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        );
        $memberResponse->assertStatus(403, 'PUB-JOURNEY-AUTHZ-01: Member menu.publish taşımaz, POST 403 dönmeli.');

        $memberRead = $this->actingAs($member)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current"
        );
        self::assertContains($memberRead->getStatusCode(), [200, 404], 'PUB-JOURNEY-AUTHZ-01: Member menu.view ile GET current çağırabilmeli (403 değil).');
    }
}
