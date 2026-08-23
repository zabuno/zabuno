<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Blind RED test candidate for standalone Platform Superadmin Plan
 * Management (S1-WP01A follow-up). At authoring time there is no platform
 * role table (grep of database/migrations finds none) and no
 * /api/admin/plans routes registered in routes/api.php. Every request below
 * is expected to fail RED from the framework's router (unmatched route ->
 * 404) or from a missing platform-role table, not from malformed fixtures
 * or a guessed production response shape.
 *
 * Frozen contract:
 * - Platform authority is distinct from workspace membership. A verified
 *   workspace Owner without a platform role is denied enumeration-safely
 *   (404). Platform role storage is a real production table with one
 *   unique role assignment per user; initial supported role string is
 *   super_admin. Tenant MembershipRole is untouched.
 * - Routes live under the existing auth:sanctum + verified middleware
 *   group: GET /api/admin/plans, POST /api/admin/plans,
 *   POST /api/admin/plans/{plan}/activate.
 * - Guest is 401/403 but never a route-missing 404; unverified is 403;
 *   verified non-platform-admin is 404.
 * - GET for super_admin returns a direct ordered array of every plan
 *   version, active and inactive, sorted sort_order then id. Exact item
 *   keys in order: id, name, code, version, entitlements, amount_minor,
 *   currency, sort_order, is_active. No timestamps/provider IDs/secrets/
 *   workspace_id.
 * - POST accepts only name, code, version (>0 int), entitlements (list of
 *   non-empty strings), amount_minor/currency paired (amount non-negative
 *   int; currency exactly uppercase 3 letters), sort_order (non-negative
 *   int). Creates a real plan as inactive only, returns exact item with
 *   201. Duplicate code+version and invalid payloads are 422 and do not
 *   write.
 * - Activate: target must exist; in one transaction it deactivates every
 *   sibling version with the same stable code and activates the target;
 *   plans of other codes remain unchanged. Response 200 exact item. After
 *   activation exactly one active row exists for that code. Re-activation
 *   is idempotent. Missing target 404.
 * - Existing tenant GET /api/workspaces/{workspace}/plans continues to
 *   expose only the newly active version to a workspace Owner with its
 *   existing public six-field shape.
 *
 * Requirement IDs: PLATFORM-PLAN-AUTHZ-01, PLATFORM-PLAN-AUTH-01,
 * PLATFORM-PLAN-LIST-SHAPE-01, PLATFORM-PLAN-CREATE-01,
 * PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01,
 * PLATFORM-PLAN-CREATE-VALIDATION-01, PLATFORM-PLAN-CREATE-DUPLICATE-01,
 * PLATFORM-PLAN-ACTIVATE-01, PLATFORM-PLAN-ACTIVATE-IDEMPOTENT-01,
 * PLATFORM-PLAN-ACTIVATE-MISSING-01, PLATFORM-PLAN-PUBLIC-PROJECTION-01.
 */
final class PlanManagementJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * There is no production platform-role table yet. If a future
     * implementation adds it before this test file is retired, grant only
     * test-owned rows — never a production seeder or demo assignment.
     */
    private function grantSuperAdmin(User $user): void
    {
        $table = null;
        foreach (['platform_role_assignments', 'platform_roles', 'platform_administrators'] as $candidate) {
            if (Schema::hasTable($candidate)) {
                $table = $candidate;
                break;
            }
        }

        if ($table === null) {
            self::fail('PLATFORM-PLAN: no platform-role table exists yet — RED must come from the missing production table, not from a test-only substitute schema.');
        }

        DB::table($table)->insert([
            'user_id' => $user->id,
            'role' => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * There is no production "plans" table under database/migrations at
     * authoring time either. Fail RED explicitly rather than fabricating a
     * schema.
     */
    private function insertPlanRow(array $overrides = []): int
    {
        if (! Schema::hasTable('plans')) {
            self::fail('PLATFORM-PLAN: production "plans" table does not exist yet — RED must come from the missing route/table, not from a malformed fixture.');
        }

        return (int) DB::table('plans')->insertGetId(array_merge([
            'name' => 'Test Plan',
            'code' => 'test-plan-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => json_encode(['feature.a', 'feature.b']),
            'amount_minor' => 1990,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function workspaceOwnedBy(User $owner, string $name, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => $name,
            'slug' => $slug,
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

        return $workspaceId;
    }

    private function adminPlansUri(): string
    {
        return '/api/admin/plans';
    }

    private function activateUri(int $planId): string
    {
        return "/api/admin/plans/{$planId}/activate";
    }

    private function validCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Growth',
            'code' => 'growth-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => ['feature.a', 'feature.b'],
            'amount_minor' => 4990,
            'currency' => 'TRY',
            'sort_order' => 3,
        ], $overrides);
    }

    // --- PLATFORM-PLAN-AUTHZ-01 --------------------------------------------

    public function test_verified_workspace_owner_without_platform_role_is_denied_enumeration_safely(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plat-authz-01@example.test');
        $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plat-authz-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->adminPlansUri());

        $response->assertStatus(404, 'PLATFORM-PLAN-AUTHZ-01: workspace Owner platform rolü taşımaz — enumeration-safe 404 dönmeli.');
    }

    // --- PLATFORM-PLAN-AUTH-01 ---------------------------------------------

    public function test_endpoint_requires_authenticated_and_verified_user(): void
    {
        $unverified = User::factory()->unverified()->create();

        $guestResponse = $this->withHeaders($this->jsonHeaders())->getJson($this->adminPlansUri());
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'PLATFORM-PLAN-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez — route var olmalı).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->getJson($this->adminPlansUri());
        $unverifiedResponse->assertStatus(403, 'PLATFORM-PLAN-AUTH-01: doğrulanmamış kullanıcı 403 ile reddedilmeli.');
    }

    // --- PLATFORM-PLAN-LIST-SHAPE-01 ----------------------------------------

    public function test_super_admin_lists_every_plan_version_ordered_with_exact_keys(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-plat-list-01@example.test');
        $this->grantSuperAdmin($admin);

        $this->insertPlanRow(['name' => 'Pro v1', 'code' => 'pro-list-01', 'version' => 1, 'is_active' => false, 'sort_order' => 2]);
        $this->insertPlanRow(['name' => 'Pro v2', 'code' => 'pro-list-01', 'version' => 2, 'is_active' => true, 'sort_order' => 2]);
        $this->insertPlanRow(['name' => 'Starter', 'code' => 'starter-list-01', 'version' => 1, 'is_active' => true, 'sort_order' => 1]);

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson($this->adminPlansUri());

        $response->assertStatus(200, 'PLATFORM-PLAN-LIST-SHAPE-01: super_admin tüm plan sürümlerini görebilmeli.');
        $body = $response->json();

        self::assertIsArray($body, 'PLATFORM-PLAN-LIST-SHAPE-01: yanıt gövdesi doğrudan bir dizi olmalı — zarf değil.');
        self::assertCount(3, $body, 'PLATFORM-PLAN-LIST-SHAPE-01: inactive sürümler dahil tüm plan versiyonları listelenmeli.');

        self::assertSame('Starter', $body[0]['name'] ?? null, 'PLATFORM-PLAN-LIST-SHAPE-01: sort_order artan, sonra id artan sırayla döner.');
        self::assertSame('Pro v1', $body[1]['name'] ?? null);
        self::assertSame('Pro v2', $body[2]['name'] ?? null);

        foreach ($body as $item) {
            self::assertSame(
                ['id', 'name', 'code', 'version', 'entitlements', 'amount_minor', 'currency', 'sort_order', 'is_active'],
                array_keys($item),
                'PLATFORM-PLAN-LIST-SHAPE-01: her kayıt yalnız bu dokuz alanı, bu sırada taşımalı.'
            );
            self::assertIsInt($item['id']);
            self::assertGreaterThan(0, $item['id']);
            self::assertIsString($item['name']);
            self::assertIsString($item['code']);
            self::assertIsInt($item['version']);
            self::assertIsArray($item['entitlements']);
            foreach ($item['entitlements'] as $entitlement) {
                self::assertIsString($entitlement);
            }
            self::assertIsInt($item['sort_order']);
            self::assertIsBool($item['is_active']);
            self::assertArrayNotHasKey('created_at', $item);
            self::assertArrayNotHasKey('updated_at', $item);
            self::assertArrayNotHasKey('workspace_id', $item);
            self::assertArrayNotHasKey('provider_id', $item);
        }
    }

    // --- PLATFORM-PLAN-CREATE-01 --------------------------------------------

    public function test_super_admin_creates_a_plan_as_inactive_and_receives_exact_item_with_201(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-plat-create-01@example.test');
        $this->grantSuperAdmin($admin);

        $payload = $this->validCreatePayload(['name' => 'Growth', 'sort_order' => 5]);

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->adminPlansUri(), $payload);

        $response->assertStatus(201, 'PLATFORM-PLAN-CREATE-01: geçerli payload ile yeni plan 201 döner.');
        $body = $response->json();

        self::assertSame(
            ['id', 'name', 'code', 'version', 'entitlements', 'amount_minor', 'currency', 'sort_order', 'is_active'],
            array_keys($body)
        );
        self::assertSame('Growth', $body['name']);
        self::assertSame($payload['code'], $body['code']);
        self::assertSame(1, $body['version']);
        self::assertSame(['feature.a', 'feature.b'], $body['entitlements']);
        self::assertSame(4990, $body['amount_minor']);
        self::assertSame('TRY', $body['currency']);
        self::assertSame(5, $body['sort_order']);
        self::assertFalse($body['is_active'], 'PLATFORM-PLAN-CREATE-01: yeni plan asla otomatik aktif oluşturulmaz.');

        if (Schema::hasTable('plans')) {
            self::assertSame(1, DB::table('plans')->where('code', $payload['code'])->count());
        }
    }

    // --- PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01 -------------------------------

    /**
     * The frontend's honest "price unavailable" payload sends both
     * amount_minor and currency explicitly null together. This is a valid
     * paired-null request (distinct from the amount_without_currency /
     * currency_without_amount 422 cases above, which pair one null with one
     * non-null value) and must persist and round-trip as null, never
     * silently coerced to 0 or an empty string.
     */
    public function test_create_accepts_explicit_null_amount_and_currency_pair_and_round_trips_null(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-plat-create-nullpair-01@example.test');
        $this->grantSuperAdmin($admin);

        $payload = $this->validCreatePayload(['amount_minor' => null, 'currency' => null]);

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->adminPlansUri(), $payload);

        $response->assertStatus(201, 'PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01: amount_minor/currency birlikte null geçerli bir paydır — 201 döner.');
        $body = $response->json();

        self::assertSame(
            ['id', 'name', 'code', 'version', 'entitlements', 'amount_minor', 'currency', 'sort_order', 'is_active'],
            array_keys($body)
        );
        self::assertNull($body['amount_minor'], 'PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01: yanıtta amount_minor kesinlikle null olmalı, 0 değil.');
        self::assertNull($body['currency'], 'PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01: yanıtta currency kesinlikle null olmalı, boş string değil.');

        if (Schema::hasTable('plans')) {
            $row = DB::table('plans')->where('code', $payload['code'])->first();
            self::assertNotNull($row, 'PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01: plan gerçekten kalıcı olarak yazılmalı.');
            self::assertNull($row->amount_minor, 'PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01: DB sütunu amount_minor null olarak saklanmalı.');
            self::assertNull($row->currency, 'PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01: DB sütunu currency null olarak saklanmalı.');
        }

        $getResponse = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson($this->adminPlansUri());
        $getResponse->assertStatus(200);

        $listed = null;
        foreach ($getResponse->json() as $item) {
            if (($item['code'] ?? null) === $payload['code']) {
                $listed = $item;
                break;
            }
        }

        self::assertNotNull($listed, 'PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01: yeni plan sonraki admin GET listesinde bulunmalı.');
        self::assertNull($listed['amount_minor'], 'PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01: admin GET round-trip sonrası amount_minor null kalmalı.');
        self::assertNull($listed['currency'], 'PLATFORM-PLAN-CREATE-NULLABLE-PAIR-01: admin GET round-trip sonrası currency null kalmalı.');
    }

    // --- PLATFORM-PLAN-CREATE-VALIDATION-01 ---------------------------------

    public function test_create_rejects_invalid_payloads_with_422_and_writes_nothing(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-plat-create-val-01@example.test');
        $this->grantSuperAdmin($admin);

        $baselineCount = Schema::hasTable('plans') ? DB::table('plans')->count() : null;

        $invalidPayloads = [
            'missing_name' => $this->validCreatePayload(['name' => '']),
            'zero_version' => $this->validCreatePayload(['version' => 0]),
            'empty_entitlement' => $this->validCreatePayload(['entitlements' => ['feature.a', '']]),
            'empty_entitlements_list' => $this->validCreatePayload(['entitlements' => []]),
            'negative_amount' => $this->validCreatePayload(['amount_minor' => -1, 'currency' => 'TRY']),
            'lowercase_currency' => $this->validCreatePayload(['amount_minor' => 100, 'currency' => 'try']),
            'four_letter_currency' => $this->validCreatePayload(['amount_minor' => 100, 'currency' => 'TRYX']),
            'amount_without_currency' => $this->validCreatePayload(['amount_minor' => 100, 'currency' => null]),
            'currency_without_amount' => $this->validCreatePayload(['amount_minor' => null, 'currency' => 'TRY']),
            'negative_sort_order' => $this->validCreatePayload(['sort_order' => -1]),
        ];

        foreach ($invalidPayloads as $label => $payload) {
            $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
                ->postJson($this->adminPlansUri(), $payload);

            $response->assertStatus(422, "PLATFORM-PLAN-CREATE-VALIDATION-01[{$label}]: geçersiz payload 422 ile reddedilmeli.");
        }

        if ($baselineCount !== null) {
            self::assertSame($baselineCount, DB::table('plans')->count(), 'PLATFORM-PLAN-CREATE-VALIDATION-01: 422 reddedilen istekler hiçbir satır yazmamalı.');
        }
    }

    // --- PLATFORM-PLAN-CREATE-DUPLICATE-01 -----------------------------------

    public function test_create_rejects_duplicate_code_and_version_with_422(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-plat-create-dup-01@example.test');
        $this->grantSuperAdmin($admin);

        $payload = $this->validCreatePayload(['code' => 'dup-plan-01', 'version' => 1]);

        $first = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->adminPlansUri(), $payload);
        $first->assertStatus(201);

        $second = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->adminPlansUri(), $payload);
        $second->assertStatus(422, 'PLATFORM-PLAN-CREATE-DUPLICATE-01: aynı code+version tekrar oluşturulamaz.');

        if (Schema::hasTable('plans')) {
            self::assertSame(1, DB::table('plans')->where('code', 'dup-plan-01')->where('version', 1)->count());
        }
    }

    // --- PLATFORM-PLAN-ACTIVATE-01 -------------------------------------------

    public function test_activate_deactivates_sibling_versions_and_leaves_other_codes_unchanged(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-plat-activate-01@example.test');
        $this->grantSuperAdmin($admin);

        $oldVersionId = $this->insertPlanRow(['name' => 'Pro v1', 'code' => 'pro-activate-01', 'version' => 1, 'is_active' => true, 'sort_order' => 2]);
        $newVersionId = $this->insertPlanRow(['name' => 'Pro v2', 'code' => 'pro-activate-01', 'version' => 2, 'is_active' => false, 'sort_order' => 2]);
        $otherCodeId = $this->insertPlanRow(['name' => 'Starter', 'code' => 'starter-activate-01', 'version' => 1, 'is_active' => true, 'sort_order' => 1]);

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->activateUri($newVersionId));

        $response->assertStatus(200, 'PLATFORM-PLAN-ACTIVATE-01: aktivasyon 200 ile hedef planı döner.');
        $body = $response->json();
        self::assertSame($newVersionId, $body['id']);
        self::assertTrue($body['is_active']);
        self::assertSame(
            ['id', 'name', 'code', 'version', 'entitlements', 'amount_minor', 'currency', 'sort_order', 'is_active'],
            array_keys($body)
        );

        if (Schema::hasTable('plans')) {
            self::assertSame(1, DB::table('plans')->where('code', 'pro-activate-01')->where('is_active', true)->count(), 'PLATFORM-PLAN-ACTIVATE-01: aynı code için tam olarak bir aktif satır kalmalı.');
            self::assertFalse((bool) DB::table('plans')->where('id', $oldVersionId)->value('is_active'), 'PLATFORM-PLAN-ACTIVATE-01: eski sürüm deaktive edilmeli.');
            self::assertTrue((bool) DB::table('plans')->where('id', $otherCodeId)->value('is_active'), 'PLATFORM-PLAN-ACTIVATE-01: farklı code taşıyan planlar etkilenmemeli.');
        }
    }

    // --- PLATFORM-PLAN-ACTIVATE-IDEMPOTENT-01 --------------------------------

    public function test_reactivating_the_already_active_plan_is_idempotent(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-plat-activate-idem-01@example.test');
        $this->grantSuperAdmin($admin);

        $activeId = $this->insertPlanRow(['name' => 'Pro v1', 'code' => 'pro-activate-idem-01', 'version' => 1, 'is_active' => true]);

        $first = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->activateUri($activeId));
        $first->assertStatus(200);

        $second = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->activateUri($activeId));
        $second->assertStatus(200, 'PLATFORM-PLAN-ACTIVATE-IDEMPOTENT-01: zaten aktif planı yeniden aktive etmek 200 döner.');
        self::assertTrue($second->json('is_active'));

        if (Schema::hasTable('plans')) {
            self::assertSame(1, DB::table('plans')->where('code', 'pro-activate-idem-01')->where('is_active', true)->count());
        }
    }

    // --- PLATFORM-PLAN-ACTIVATE-MISSING-01 -----------------------------------

    public function test_activate_missing_plan_returns_404(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-plat-activate-missing-01@example.test');
        $this->grantSuperAdmin($admin);

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->activateUri(999999999));

        $response->assertStatus(404, 'PLATFORM-PLAN-ACTIVATE-MISSING-01: var olmayan plan hedefi 404 döner.');
    }

    // --- PLATFORM-PLAN-PUBLIC-PROJECTION-01 -----------------------------------

    public function test_tenant_public_catalog_exposes_only_the_newly_activated_version_with_six_field_shape(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-plat-proj-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plat-proj-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plat-proj-01');

        $oldVersionId = $this->insertPlanRow(['name' => 'Pro v1', 'code' => 'pro-proj-01', 'version' => 1, 'is_active' => true, 'sort_order' => 1]);
        $newVersionId = $this->insertPlanRow(['name' => 'Pro v2', 'code' => 'pro-proj-01', 'version' => 2, 'is_active' => false, 'sort_order' => 1]);

        $activateResponse = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->activateUri($newVersionId));
        $activateResponse->assertStatus(200);

        $tenantResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/plans");

        $tenantResponse->assertStatus(200);
        $body = $tenantResponse->json();

        self::assertIsArray($body);
        self::assertCount(1, $body, 'PLATFORM-PLAN-PUBLIC-PROJECTION-01: yalnız yeni aktif sürüm görünmeli.');
        self::assertSame('Pro v2', $body[0]['name'] ?? null);
        self::assertSame(
            ['id', 'name', 'code', 'entitlements', 'amount_minor', 'currency'],
            array_keys($body[0]),
            'PLATFORM-PLAN-PUBLIC-PROJECTION-01: mevcut public altı alanlı şekil değişmemeli.'
        );

        unset($oldVersionId);
    }
}
