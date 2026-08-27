<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AbortOnInsertFixture;
use Tests\TestCase;

/**
 * S1-WP04b1 RED — real QR Destination create/list/disable HTTP-delivery
 * contract for the published-menu vertical slice (MASTER-synthesized scope:
 * Admin Owner with a current publication creates one QR code that is
 * immediately active and atomically pointed to destinationType=
 * published_menu; disable is one-way; no repoint/export/analytics yet).
 *
 * Frozen route + JSON contract (this file's own invented contract):
 *
 *   POST /api/workspaces/{workspace}/brand/locations/{location}/qr-codes
 *        body {"menuId": int}
 *     -> 201 {"id": int, "workspaceId": int, "locationId": int,
 *             "menuId": int, "token": string(43), "resolverUrl": string,
 *             "destinationType": "published_menu", "state": "active"}
 *     -> 422 when the target menu has no current publication.
 *
 *   GET  /api/workspaces/{workspace}/brand/locations/{location}/qr-codes
 *     -> 200 [{...same shape...}, ...]
 *
 *   PUT  /api/workspaces/{workspace}/qr-codes/{qrCode}/disable
 *     -> 200 {"id": int, "state": "disabled"}
 *
 * None of these routes nor any App\Http\Controllers\QrDestination\*
 * controller exists yet (grep of routes/api.php confirms no "qr-codes"
 * route), so every request below is expected to fail RED with a 404
 * route-not-found response, not a logic assertion failure.
 *
 * Requirement IDs: QR-JOURNEY-CREATE-01, QR-JOURNEY-LIST-01,
 * QR-JOURNEY-NOCURRENT-01, QR-JOURNEY-ATOMIC-01, QR-JOURNEY-AUTHZ-01,
 * QR-JOURNEY-DISABLE-01.
 */
final class QrDestinationJourneyTest extends TestCase
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
     * Builds workspace/brand/location/menu plus a real *current* publication
     * (menu_publications + menu_publication_current_pointers rows), so a QR
     * create request has a legitimate destination to point at.
     *
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

        $snapshot = json_encode([
            'categories' => [
                ['name' => 'Starters', 'menuItems' => [['productName' => 'Kahve', 'priceMinorAmount' => 4250, 'currencyCode' => 'TRY']]],
            ],
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => $snapshot,
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

    /**
     * @return array{0: int, 1: int} [workspaceId, locationId]
     */
    private function workspaceWithDraftOnlyMenu(User $owner, string $slugSeed): array
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

        DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId];
    }

    // --- QR-JOURNEY-CREATE-01 ------------------------------------------------

    public function test_verified_owner_with_a_current_publication_creates_an_immediately_active_qr_code(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-create-1');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        );

        $response->assertStatus(201, 'QR-JOURNEY-CREATE-01: hazır bir current publication için Owner senkron 201 almalı.');
        $response->assertJsonPath('destinationType', 'published_menu');
        $response->assertJsonPath('state', 'active');
        $response->assertJsonPath('workspaceId', $workspaceId);
        $response->assertJsonPath('locationId', $locationId);
        $response->assertJsonPath('menuId', $menuId);

        $body = $response->json();
        self::assertIsInt($body['id'] ?? null, 'QR-JOURNEY-CREATE-01: gerçek pozitif id dönmeli.');
        self::assertGreaterThan(0, $body['id']);

        $token = $body['token'] ?? '';
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', (string) $token, 'QR-JOURNEY-CREATE-01: token 256-bit base64url [A-Za-z0-9_-]{43} olmalı.');

        $resolverUrl = $body['resolverUrl'] ?? '';
        self::assertStringContainsString((string) $token, (string) $resolverUrl, 'QR-JOURNEY-CREATE-01: resolverUrl gerçek token\'ı içermeli.');

        self::assertDatabaseHas('qr_codes', ['id' => $body['id'], 'token' => $token, 'state' => 'active']);
        self::assertDatabaseHas('qr_destinations', ['qr_code_id' => $body['id'], 'destination_type' => 'published_menu', 'menu_id' => $menuId]);
        self::assertDatabaseHas('qr_code_current_destinations', ['qr_code_id' => $body['id']]);
    }

    // --- QR-JOURNEY-LIST-01 ---------------------------------------------------

    public function test_the_created_qr_code_survives_reload_via_the_list_endpoint_with_the_same_stable_token(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-list-1');

        $created = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        )->assertStatus(201);
        $token = $created->json('token');

        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes"
        );

        $list->assertStatus(200, 'QR-JOURNEY-LIST-01: list endpoint 200 dönmeli.');
        $tokens = array_column($list->json() ?? [], 'token');
        self::assertContains($token, $tokens, 'QR-JOURNEY-LIST-01: reload sonrası aynı fiziksel token korunmalı.');
    }

    // --- QR-JOURNEY-NOCURRENT-01 ----------------------------------------------

    public function test_create_without_any_current_publication_returns_422_and_persists_nothing(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithDraftOnlyMenu($owner, 'qr-nocurrent-1');
        $menuId = (int) DB::table('menus')->where('location_id', $locationId)->value('id');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        );

        $response->assertStatus(422, 'QR-JOURNEY-NOCURRENT-01: current publication yoksa QR create 422 dönmeli.');
        self::assertDatabaseCount('qr_codes', 0);
    }

    // --- QR-JOURNEY-ATOMIC-01 -------------------------------------------------

    public function test_a_forced_pointer_persistence_failure_rolls_back_the_entire_create_transaction(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-atomic-1');

        AbortOnInsertFixture::install(
            's1_wp04b1_abort_qr_current_destination',
            'qr_code_current_destinations',
            'S1-WP04b1 controlled persistence failure fixture.',
        );

        try {
            $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
                ['menuId' => $menuId]
            );

            self::assertNotSame(201, $response->getStatusCode(), 'QR-JOURNEY-ATOMIC-01: kontrollü persistence hatasında 201 dönmemeli.');
            self::assertContains($response->getStatusCode(), [500, 503], 'QR-JOURNEY-ATOMIC-01: sunucu hatası 500/503 olmalı, sahte 2xx değil.');
        } finally {
            AbortOnInsertFixture::remove('s1_wp04b1_abort_qr_current_destination', 'qr_code_current_destinations');
        }

        self::assertDatabaseCount('qr_codes', 0);
        self::assertDatabaseCount('qr_destinations', 0);
    }

    // --- QR-JOURNEY-AUTHZ-01 --------------------------------------------------

    public function test_guest_is_401_unverified_is_blocked_and_member_without_qr_create_is_denied(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-authz-1');

        $guestResponse = $this->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        );
        $guestResponse->assertStatus(401, 'QR-JOURNEY-AUTHZ-01: guest 401 almalı.');

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        );
        self::assertNotSame(201, $unverifiedResponse->getStatusCode(), 'QR-JOURNEY-AUTHZ-01: doğrulanmamış kullanıcı QR create edememeli.');

        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberCreate = $this->actingAs($member)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        );
        $memberCreate->assertStatus(403, 'QR-JOURNEY-AUTHZ-01: Member qr.create taşımaz, POST 403 dönmeli.');
    }

    // --- QR-JOURNEY-DISABLE-01 -------------------------------------------------

    public function test_owner_can_disable_a_qr_code_and_disable_is_reflected_immediately(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-disable-1');

        $created = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        )->assertStatus(201);
        $qrCodeId = $created->json('id');

        $disable = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/disable"
        );

        $disable->assertStatus(200, 'QR-JOURNEY-DISABLE-01: Owner qr.disable ile disable edebilmeli.');
        $disable->assertJsonPath('state', 'disabled');

        self::assertDatabaseHas('qr_codes', ['id' => $qrCodeId, 'state' => 'disabled']);
    }

    public function test_a_member_without_qr_disable_is_denied_with_403(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-disable-2');

        $created = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        )->assertStatus(201);
        $qrCodeId = $created->json('id');

        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $disable = $this->actingAs($member)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/disable"
        );

        $disable->assertStatus(403, 'QR-JOURNEY-DISABLE-01: Member qr.disable taşımaz, 403 dönmeli.');
        self::assertDatabaseHas('qr_codes', ['id' => $qrCodeId, 'state' => 'active']);
    }
}
