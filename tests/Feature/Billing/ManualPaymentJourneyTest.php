<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Blind RED test candidate for Stage 1 workspace-discovery, current-
 * subscription, and manual-payment API (S1-WP01A). At authoring time grep
 * of routes/api.php finds zero "admin/workspaces", "manual-payment", or
 * "subscription" routes, and grep of database/migrations finds no
 * subscriptions/manual_payments table. Every request below is expected to
 * fail RED from the framework's router (unmatched route -> 404) or from a
 * missing production table, not from malformed fixtures or a guessed
 * production response shape.
 *
 * Frozen contract:
 * - GET /api/admin/workspaces?query=, GET /api/admin/plans (existing),
 *   GET /api/admin/workspaces/{workspace}/subscription, and
 *   POST /api/admin/workspaces/{workspace}/manual-payments require the
 *   existing platform super_admin role (platform_role_assignments), inside
 *   the existing auth:sanctum + verified group. A verified tenant workspace
 *   Owner without that role is denied enumeration-safely (404).
 * - GET /api/workspaces/{workspace}/subscription requires
 *   Permission::BillingView on that workspace; owner can read own
 *   workspace; member/editor/foreign/nonexistent workspace all receive the
 *   same enumeration-safe 404 signature.
 * - Platform role and tenant MembershipRole never substitute for each
 *   other: a platform super_admin who is not also a tenant member is still
 *   404 on the tenant endpoint, and a tenant Owner is still 404 on platform
 *   endpoints.
 * - Workspace discovery returns exact real workspace id/name/slug/state,
 *   filters case-insensitively by name or slug substring, orders
 *   deterministically by id, and never leaks user/password/membership/
 *   secret fields. Empty query returns real rows only; empty DB returns [].
 * - Current subscription (both admin and tenant GET) returns exactly
 *   {state: 'none'} when no subscription row exists for the workspace, and
 *   otherwise returns state/plan_id/plan_code/plan_name/plan_version/
 *   ends_at (ISO 8601) pinned to the stored row and the stored plan
 *   version — never fabricated defaults, never silently presenting a
 *   malformed/orphaned row as valid.
 * - POST manual-payments accepts exactly plan_id, ends_at, payment_note,
 *   document_reference, idempotency_key. plan_id must reference an
 *   existing active exact plan version; ends_at must be today or future;
 *   payment_note/document_reference must be nonblank and bounded;
 *   idempotency_key must be a UUID. Unknown/inactive plan or malformed
 *   payload is 422 and writes nothing.
 * - A successful POST creates exactly one current-subscription row (pinned
 *   to the exact plan row) and one immutable manual-payment history row
 *   carrying actor user id, workspace id, plan id, date/note/reference/
 *   idempotency key. No card or float-money fields are ever stored.
 * - A second distinct payment for the same workspace atomically replaces
 *   the single current-subscription row but both manual-payment history
 *   rows remain, immutable.
 * - Replaying the identical workspace + idempotency_key + payload is
 *   idempotent: no duplicate history/subscription row, and an OK
 *   authoritative response. Reusing the same key with a different payload
 *   is 409 and mutates nothing.
 * - Nonexistent workspace is 404. A tenant-only Owner cannot POST even in
 *   their own workspace (platform-only endpoint).
 * - Database-enforced uniqueness prevents duplicate workspace_id +
 *   idempotency_key history rows and enforces at most one current
 *   subscription row per workspace.
 *
 * Requirement IDs: MANUAL-PAY-DISCOVERY-AUTHZ-01, MANUAL-PAY-DISCOVERY-01,
 * MANUAL-PAY-DISCOVERY-EMPTY-01, MANUAL-PAY-SUB-NONE-01,
 * MANUAL-PAY-SUB-ACTIVE-01, MANUAL-PAY-SUB-MALFORMED-01,
 * MANUAL-PAY-SUB-TENANT-AUTHZ-01, MANUAL-PAY-SUB-TENANT-ESCAPE-01,
 * MANUAL-PAY-ROLE-SEPARATION-01, MANUAL-PAY-CREATE-VALIDATION-01,
 * MANUAL-PAY-CREATE-01, MANUAL-PAY-REPLACE-01, MANUAL-PAY-IDEMPOTENT-01,
 * MANUAL-PAY-IDEMPOTENT-CONFLICT-01, MANUAL-PAY-MISSING-WORKSPACE-01,
 * MANUAL-PAY-TENANT-FORBIDDEN-01, MANUAL-PAY-DB-UNIQUENESS-01,
 * MANUAL-PAY-AUTH-01.
 */
final class ManualPaymentJourneyTest extends TestCase
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
     * platform_role_assignments already exists in production (S1-WP01A
     * plan management). Grant only test-owned rows.
     */
    private function grantSuperAdmin(User $user): void
    {
        if (! Schema::hasTable('platform_role_assignments')) {
            self::fail('MANUAL-PAY: production "platform_role_assignments" table does not exist yet — RED must come from the missing production table, not a test-only substitute.');
        }

        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id,
            'role' => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    private function addMember(int $workspaceId, User $user, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * plans already exists in production (S1-WP01A plan management).
     */
    private function insertPlanRow(array $overrides = []): int
    {
        if (! Schema::hasTable('plans')) {
            self::fail('MANUAL-PAY: production "plans" table does not exist yet — RED must come from the missing production table, not a malformed fixture.');
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

    /**
     * There is no production "subscriptions" table under
     * database/migrations at authoring time. Fail RED explicitly rather
     * than fabricating a schema so the caller can seed a malformed row.
     */
    private function requireSubscriptionsTable(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            self::fail('MANUAL-PAY: production "subscriptions" table does not exist yet — RED must come from the missing route/table, not a malformed fixture.');
        }
    }

    private function discoveryUri(string $query = ''): string
    {
        return '/api/admin/workspaces'.($query !== '' ? '?query='.urlencode($query) : '');
    }

    private function adminSubscriptionUri(int $workspaceId): string
    {
        return "/api/admin/workspaces/{$workspaceId}/subscription";
    }

    private function manualPaymentsUri(int $workspaceId): string
    {
        return "/api/admin/workspaces/{$workspaceId}/manual-payments";
    }

    private function tenantSubscriptionUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/subscription";
    }

    private function validManualPaymentPayload(int $planId, array $overrides = []): array
    {
        return array_merge([
            'plan_id' => $planId,
            'ends_at' => now()->addYear()->toDateString(),
            'payment_note' => 'Havale ile ödendi.',
            'document_reference' => 'DEKONT-'.bin2hex(random_bytes(3)),
            'idempotency_key' => $this->uuid(),
        ], $overrides);
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // --- MANUAL-PAY-DISCOVERY-AUTHZ-01 --------------------------------------

    public function test_verified_workspace_owner_without_platform_role_is_denied_enumeration_safely_on_discovery(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-disc-authz-01@example.test');
        $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-disc-authz-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->discoveryUri());

        $response->assertStatus(404, 'MANUAL-PAY-DISCOVERY-AUTHZ-01: workspace Owner platform rolü taşımaz — enumeration-safe 404 dönmeli.');
    }

    // --- MANUAL-PAY-DISCOVERY-01 --------------------------------------------

    public function test_super_admin_discovers_workspaces_filtered_case_insensitively_with_exact_fields(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-disc-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-disc-01@example.test');
        $matchId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-disc-01');
        $this->workspaceOwnedBy($owner, 'Vişne Kafe', 'visne-mp-disc-01');

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson($this->discoveryUri('ZEYTIN'));

        $response->assertStatus(200, 'MANUAL-PAY-DISCOVERY-01: super_admin workspace arayabilmeli.');
        $body = $response->json();

        self::assertIsArray($body, 'MANUAL-PAY-DISCOVERY-01: yanıt gövdesi doğrudan bir dizi olmalı — zarf değil.');
        self::assertCount(1, $body, 'MANUAL-PAY-DISCOVERY-01: query büyük/küçük harf duyarsız olmalı ve yalnız eşleşeni döndürmeli.');

        $item = $body[0];
        self::assertSame(
            ['id', 'name', 'slug', 'state'],
            array_keys($item),
            'MANUAL-PAY-DISCOVERY-01: her kayıt yalnız id/name/slug/state alanlarını taşımalı.'
        );
        self::assertSame($matchId, $item['id']);
        self::assertSame('Zeytin Restoranları', $item['name']);
        self::assertSame('zeytin-mp-disc-01', $item['slug']);
        self::assertSame('active', $item['state']);
        self::assertArrayNotHasKey('created_by', $item);
        self::assertArrayNotHasKey('user_id', $item);
        self::assertArrayNotHasKey('password', $item);
        self::assertArrayNotHasKey('membership', $item);
    }

    // --- MANUAL-PAY-DISCOVERY-EMPTY-01 --------------------------------------

    public function test_empty_query_returns_real_rows_only_and_empty_database_returns_empty_array(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-disc-empty-01@example.test');
        $this->grantSuperAdmin($admin);

        $emptyDbResponse = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson($this->discoveryUri());
        $emptyDbResponse->assertStatus(200);
        self::assertSame([], $emptyDbResponse->json(), 'MANUAL-PAY-DISCOVERY-EMPTY-01: boş veritabanı için sahte satır eklenmemeli.');

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-disc-empty-01@example.test');
        $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-disc-empty-01');

        $withRowsResponse = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson($this->discoveryUri());
        $withRowsResponse->assertStatus(200);
        self::assertCount(1, $withRowsResponse->json(), 'MANUAL-PAY-DISCOVERY-EMPTY-01: boş query gerçek satırları döndürmeli.');
    }

    // --- MANUAL-PAY-SUB-NONE-01 ---------------------------------------------

    public function test_admin_and_tenant_current_subscription_return_exact_none_state_when_no_row_exists(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-sub-none-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-sub-none-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-sub-none-01');

        $adminResponse = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson($this->adminSubscriptionUri($workspaceId));
        $adminResponse->assertStatus(200, 'MANUAL-PAY-SUB-NONE-01: abonelik yokken admin görünümü 200 dönmeli.');
        self::assertSame(['state' => 'none'], $adminResponse->json(), 'MANUAL-PAY-SUB-NONE-01: abonelik yokken yanıt tam olarak {state: none} olmalı.');

        $tenantResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->tenantSubscriptionUri($workspaceId));
        $tenantResponse->assertStatus(200, 'MANUAL-PAY-SUB-NONE-01: abonelik yokken tenant görünümü 200 dönmeli.');
        self::assertSame(['state' => 'none'], $tenantResponse->json());
    }

    // --- MANUAL-PAY-SUB-ACTIVE-01 -------------------------------------------

    public function test_active_subscription_returns_pinned_plan_and_iso_ends_at_from_stored_row(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-sub-active-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-sub-active-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-sub-active-01');

        $planId = $this->insertPlanRow(['name' => 'Pro', 'code' => 'pro-sub-active-01', 'version' => 3]);

        $this->requireSubscriptionsTable();
        $endsAt = now()->addMonths(6);
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_id' => $planId,
            'state' => 'active',
            'ends_at' => $endsAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminResponse = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson($this->adminSubscriptionUri($workspaceId));
        $adminResponse->assertStatus(200);
        $body = $adminResponse->json();

        self::assertSame('active', $body['state'] ?? null);
        self::assertSame($planId, $body['plan_id'] ?? null, 'MANUAL-PAY-SUB-ACTIVE-01: plan_id kaydedilen satırdan sabitlenmeli.');
        self::assertSame('pro-sub-active-01', $body['plan_code'] ?? null);
        self::assertSame('Pro', $body['plan_name'] ?? null);
        self::assertSame(3, $body['plan_version'] ?? null);
        self::assertSame($endsAt->toIso8601String(), $body['ends_at'] ?? null, 'MANUAL-PAY-SUB-ACTIVE-01: ends_at kaydedilen satırdan ISO 8601 biçiminde dönmeli.');

        $tenantResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->tenantSubscriptionUri($workspaceId));
        $tenantResponse->assertStatus(200);
        self::assertSame($body, $tenantResponse->json(), 'MANUAL-PAY-SUB-ACTIVE-01: admin ve tenant görünümleri aynı otoriter içeriği taşımalı.');
    }

    // --- MANUAL-PAY-SUB-MALFORMED-01 ----------------------------------------

    public function test_orphaned_subscription_row_pointing_to_missing_plan_is_not_silently_presented_as_valid(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-sub-malformed-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-sub-malformed-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-sub-malformed-01');

        $this->requireSubscriptionsTable();

        try {
            DB::table('subscriptions')->insert([
                'workspace_id' => $workspaceId,
                'plan_id' => 999999999,
                'state' => 'active',
                'ends_at' => now()->addMonth(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            self::assertTrue(true, 'MANUAL-PAY-SUB-MALFORMED-01: veritabanı yetim (orphaned) plan referansını FK ile reddetti — bu da geçerli bütünlük uygulamasıdır.');

            return;
        }

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson($this->adminSubscriptionUri($workspaceId));

        self::assertNotSame(200, $response->getStatusCode(), 'MANUAL-PAY-SUB-MALFORMED-01: veritabanı yetim satırın eklenmesine izin verdiyse, API bunu 200 ile geçerliymiş gibi sunamaz.');
    }

    // --- MANUAL-PAY-SUB-TENANT-AUTHZ-01 -------------------------------------

    public function test_tenant_subscription_requires_billing_view_member_and_editor_get_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-sub-tenauthz-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-sub-tenauthz-01');

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-mp-sub-tenauthz-01@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-mp-sub-tenauthz-01@example.test');
        $this->addMember($workspaceId, $editor, 'editor');

        $ownerResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->tenantSubscriptionUri($workspaceId));
        $ownerResponse->assertStatus(200, 'MANUAL-PAY-SUB-TENANT-AUTHZ-01: owner BillingView taşır — 200 dönmeli.');

        $memberResponse = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->getJson($this->tenantSubscriptionUri($workspaceId));
        $memberResponse->assertStatus(404, 'MANUAL-PAY-SUB-TENANT-AUTHZ-01: member BillingView taşımaz — 404 ile reddedilmeli.');

        $editorResponse = $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->getJson($this->tenantSubscriptionUri($workspaceId));
        $editorResponse->assertStatus(404, 'MANUAL-PAY-SUB-TENANT-AUTHZ-01: editor BillingView taşımaz — 404 ile reddedilmeli, 403 değil.');
    }

    // --- MANUAL-PAY-SUB-TENANT-ESCAPE-01 ------------------------------------

    public function test_tenant_subscription_foreign_and_nonexistent_workspace_share_identical_404_signature(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-sub-escape-01@example.test');
        $stranger = $this->verifiedUser('Can Öztürk', 'can-mp-sub-escape-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-sub-escape-01');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->tenantSubscriptionUri($workspaceId));
        $strangerResponse->assertStatus(404);

        $nonexistentResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->tenantSubscriptionUri(999999999));

        self::assertSame(
            $strangerResponse->getStatusCode(),
            $nonexistentResponse->getStatusCode(),
            'MANUAL-PAY-SUB-TENANT-ESCAPE-01: yabancı ve var olmayan workspace aynı imzayı taşımalı (enumeration-safe).'
        );
    }

    // --- MANUAL-PAY-ROLE-SEPARATION-01 --------------------------------------

    public function test_platform_role_and_tenant_role_never_substitute_for_each_other(): void
    {
        $platformOnlyAdmin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-rolesep-01@example.test');
        $this->grantSuperAdmin($platformOnlyAdmin);

        $tenantOwner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-rolesep-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($tenantOwner, 'Zeytin Restoranları', 'zeytin-mp-rolesep-01');

        $platformAdminOnTenantRoute = $this->actingAs($platformOnlyAdmin)->withHeaders($this->jsonHeaders())
            ->getJson($this->tenantSubscriptionUri($workspaceId));
        $platformAdminOnTenantRoute->assertStatus(404, 'MANUAL-PAY-ROLE-SEPARATION-01: platform super_admin tenant üyesi değilse tenant rotasında 404 almalı.');

        $tenantOwnerOnPlatformRoute = $this->actingAs($tenantOwner)->withHeaders($this->jsonHeaders())
            ->getJson($this->adminSubscriptionUri($workspaceId));
        $tenantOwnerOnPlatformRoute->assertStatus(404, 'MANUAL-PAY-ROLE-SEPARATION-01: tenant Owner platform rolü taşımaz — platform rotasında 404 almalı.');
    }

    // --- MANUAL-PAY-CREATE-VALIDATION-01 ------------------------------------

    public function test_manual_payment_rejects_invalid_payloads_with_422_and_writes_nothing(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-create-val-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-create-val-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-create-val-01');

        $planId = $this->insertPlanRow(['code' => 'pro-create-val-01']);
        $inactivePlanId = $this->insertPlanRow(['code' => 'pro-create-val-inactive-01', 'is_active' => false]);

        $baselineSubCount = Schema::hasTable('subscriptions') ? DB::table('subscriptions')->count() : null;
        $baselineHistoryCount = Schema::hasTable('manual_payments') ? DB::table('manual_payments')->count() : null;

        $invalidPayloads = [
            'unknown_plan' => $this->validManualPaymentPayload(999999999),
            'inactive_plan' => $this->validManualPaymentPayload($inactivePlanId),
            'past_ends_at' => $this->validManualPaymentPayload($planId, ['ends_at' => now()->subDay()->toDateString()]),
            'blank_note' => $this->validManualPaymentPayload($planId, ['payment_note' => '   ']),
            'blank_reference' => $this->validManualPaymentPayload($planId, ['document_reference' => '']),
            'non_uuid_key' => $this->validManualPaymentPayload($planId, ['idempotency_key' => 'not-a-uuid']),
            'missing_plan_id' => ['ends_at' => now()->addMonth()->toDateString(), 'payment_note' => 'x', 'document_reference' => 'y', 'idempotency_key' => $this->uuid()],
        ];

        foreach ($invalidPayloads as $label => $payload) {
            RateLimiter::clear(sha1((string) $admin->getAuthIdentifier()));

            $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
                ->postJson($this->manualPaymentsUri($workspaceId), $payload);

            $response->assertStatus(422, "MANUAL-PAY-CREATE-VALIDATION-01[{$label}]: geçersiz payload 422 ile reddedilmeli.");
        }

        if ($baselineSubCount !== null) {
            self::assertSame($baselineSubCount, DB::table('subscriptions')->count(), 'MANUAL-PAY-CREATE-VALIDATION-01: 422 reddedilen istekler abonelik satırı yazmamalı.');
        }
        if ($baselineHistoryCount !== null) {
            self::assertSame($baselineHistoryCount, DB::table('manual_payments')->count(), 'MANUAL-PAY-CREATE-VALIDATION-01: 422 reddedilen istekler ödeme geçmişi satırı yazmamalı.');
        }
    }

    // --- MANUAL-PAY-CREATE-01 -----------------------------------------------

    public function test_super_admin_manual_payment_creates_subscription_and_immutable_history_row(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-create-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-create-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-create-01');

        $planId = $this->insertPlanRow(['name' => 'Pro', 'code' => 'pro-create-01', 'version' => 2]);
        $payload = $this->validManualPaymentPayload($planId);

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->manualPaymentsUri($workspaceId), $payload);

        $response->assertStatus(201, 'MANUAL-PAY-CREATE-01: geçerli manuel ödeme 201 ile kabul edilmeli.');
        $body = $response->json();
        self::assertSame('active', $body['state'] ?? null);
        self::assertSame($planId, $body['plan_id'] ?? null);

        $this->requireSubscriptionsTable();
        self::assertSame(1, DB::table('subscriptions')->where('workspace_id', $workspaceId)->count(), 'MANUAL-PAY-CREATE-01: tam olarak bir current subscription satırı oluşmalı.');
        $subRow = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();
        self::assertSame($planId, (int) $subRow->plan_id);

        if (! Schema::hasTable('manual_payments')) {
            self::fail('MANUAL-PAY-CREATE-01: production "manual_payments" table does not exist yet — RED must come from the missing table.');
        }

        $historyRows = DB::table('manual_payments')->where('workspace_id', $workspaceId)->get();
        self::assertCount(1, $historyRows, 'MANUAL-PAY-CREATE-01: tam olarak bir manuel ödeme geçmiş satırı oluşmalı.');
        $history = $historyRows->first();
        self::assertSame($admin->id, (int) $history->actor_user_id ?? null);
        self::assertSame($workspaceId, (int) $history->workspace_id);
        self::assertSame($planId, (int) $history->plan_id);
        self::assertSame($payload['idempotency_key'], $history->idempotency_key);

        foreach ((array) $history as $column => $value) {
            self::assertStringNotContainsStringIgnoringCase('card', (string) $column, 'MANUAL-PAY-CREATE-01: kart verisi asla saklanmamalı.');
            self::assertStringNotContainsStringIgnoringCase('cvv', (string) $column);
        }
    }

    // --- MANUAL-PAY-REPLACE-01 ----------------------------------------------

    public function test_second_manual_payment_replaces_current_subscription_but_preserves_both_history_rows(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-replace-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-replace-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-replace-01');

        $firstPlanId = $this->insertPlanRow(['name' => 'Starter', 'code' => 'starter-replace-01']);
        $secondPlanId = $this->insertPlanRow(['name' => 'Pro', 'code' => 'pro-replace-01']);

        $first = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->manualPaymentsUri($workspaceId), $this->validManualPaymentPayload($firstPlanId));
        $first->assertStatus(201);

        $second = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->manualPaymentsUri($workspaceId), $this->validManualPaymentPayload($secondPlanId));
        $second->assertStatus(201, 'MANUAL-PAY-REPLACE-01: aynı workspace için ikinci ödeme kabul edilmeli.');

        $this->requireSubscriptionsTable();
        self::assertSame(1, DB::table('subscriptions')->where('workspace_id', $workspaceId)->count(), 'MANUAL-PAY-REPLACE-01: her zaman yalnız bir current subscription satırı kalmalı.');
        $subRow = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();
        self::assertSame($secondPlanId, (int) $subRow->plan_id, 'MANUAL-PAY-REPLACE-01: current subscription en son ödemenin planına güncellenmeli.');

        if (! Schema::hasTable('manual_payments')) {
            self::fail('MANUAL-PAY-REPLACE-01: production "manual_payments" table does not exist yet — RED must come from the missing table.');
        }
        self::assertSame(2, DB::table('manual_payments')->where('workspace_id', $workspaceId)->count(), 'MANUAL-PAY-REPLACE-01: iki ödeme geçmişi satırı da değişmeden kalmalı.');
    }

    // --- MANUAL-PAY-IDEMPOTENT-01 -------------------------------------------

    public function test_replaying_identical_payload_and_idempotency_key_is_idempotent(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-idem-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-idem-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-idem-01');

        $planId = $this->insertPlanRow(['code' => 'pro-idem-01']);
        $payload = $this->validManualPaymentPayload($planId);

        $first = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->manualPaymentsUri($workspaceId), $payload);
        $first->assertStatus(201);

        $replay = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->manualPaymentsUri($workspaceId), $payload);

        self::assertContains($replay->getStatusCode(), [200, 201], 'MANUAL-PAY-IDEMPOTENT-01: aynı idempotency_key ile aynı payload tekrarı başarılı ve otoriter yanıt dönmeli.');

        $this->requireSubscriptionsTable();
        self::assertSame(1, DB::table('subscriptions')->where('workspace_id', $workspaceId)->count());

        if (! Schema::hasTable('manual_payments')) {
            self::fail('MANUAL-PAY-IDEMPOTENT-01: production "manual_payments" table does not exist yet — RED must come from the missing table.');
        }
        self::assertSame(1, DB::table('manual_payments')->where('workspace_id', $workspaceId)->where('idempotency_key', $payload['idempotency_key'])->count(), 'MANUAL-PAY-IDEMPOTENT-01: tekrarlanan istek yinelenen geçmiş satırı oluşturmamalı.');
    }

    // --- MANUAL-PAY-IDEMPOTENT-CONFLICT-01 ----------------------------------

    public function test_reusing_idempotency_key_with_different_payload_is_409_and_mutates_nothing(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-idemconf-01@example.test');
        $this->grantSuperAdmin($admin);

        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-idemconf-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-idemconf-01');

        $planId = $this->insertPlanRow(['code' => 'pro-idemconf-01']);
        $otherPlanId = $this->insertPlanRow(['code' => 'starter-idemconf-01']);
        $payload = $this->validManualPaymentPayload($planId);

        $first = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->manualPaymentsUri($workspaceId), $payload);
        $first->assertStatus(201);

        $conflicting = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->manualPaymentsUri($workspaceId), array_merge($payload, ['plan_id' => $otherPlanId]));

        $conflicting->assertStatus(409, 'MANUAL-PAY-IDEMPOTENT-CONFLICT-01: aynı idempotency_key farklı payload ile 409 dönmeli.');

        $this->requireSubscriptionsTable();
        $subRow = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();
        self::assertSame($planId, (int) $subRow->plan_id, 'MANUAL-PAY-IDEMPOTENT-CONFLICT-01: çakışan istek hiçbir değişiklik yapmamalı.');

        if (! Schema::hasTable('manual_payments')) {
            self::fail('MANUAL-PAY-IDEMPOTENT-CONFLICT-01: production "manual_payments" table does not exist yet — RED must come from the missing table.');
        }
        self::assertSame(1, DB::table('manual_payments')->where('workspace_id', $workspaceId)->count(), 'MANUAL-PAY-IDEMPOTENT-CONFLICT-01: çakışan istek geçmişe satır eklememeli.');
    }

    // --- MANUAL-PAY-MISSING-WORKSPACE-01 ------------------------------------

    public function test_manual_payment_for_nonexistent_workspace_is_404(): void
    {
        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-missing-ws-01@example.test');
        $this->grantSuperAdmin($admin);

        $planId = $this->insertPlanRow(['code' => 'pro-missing-ws-01']);

        $response = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->manualPaymentsUri(999999999), $this->validManualPaymentPayload($planId));

        $response->assertStatus(404, 'MANUAL-PAY-MISSING-WORKSPACE-01: var olmayan workspace 404 dönmeli.');
    }

    // --- MANUAL-PAY-TENANT-FORBIDDEN-01 --------------------------------------

    public function test_tenant_only_owner_cannot_post_manual_payment_even_in_own_workspace(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-tenforbid-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-tenforbid-01');

        $planId = $this->insertPlanRow(['code' => 'pro-tenforbid-01']);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->manualPaymentsUri($workspaceId), $this->validManualPaymentPayload($planId));

        $response->assertStatus(404, 'MANUAL-PAY-TENANT-FORBIDDEN-01: tenant Owner platform-only manuel ödeme rotasında enumeration-safe 404 almalı.');
    }

    // --- MANUAL-PAY-DB-UNIQUENESS-01 ----------------------------------------

    public function test_database_enforces_uniqueness_on_workspace_and_idempotency_key_and_single_current_subscription(): void
    {
        if (! Schema::hasTable('manual_payments')) {
            self::fail('MANUAL-PAY-DB-UNIQUENESS-01: production "manual_payments" table does not exist yet — RED must come from the missing table.');
        }
        $this->requireSubscriptionsTable();

        $admin = $this->verifiedUser('Deniz Aksoy', 'deniz-mp-dbuniq-01@example.test');
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-mp-dbuniq-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-mp-dbuniq-01');
        $planId = $this->insertPlanRow(['code' => 'pro-dbuniq-01']);
        $idempotencyKey = $this->uuid();

        DB::table('manual_payments')->insert([
            'workspace_id' => $workspaceId,
            'actor_user_id' => $admin->id,
            'plan_id' => $planId,
            'ends_at' => now()->addYear(),
            'payment_note' => 'İlk kayıt',
            'document_reference' => 'DEKONT-1',
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $historyDuplicateThrew = false;
        try {
            DB::table('manual_payments')->insert([
                'workspace_id' => $workspaceId,
                'actor_user_id' => $admin->id,
                'plan_id' => $planId,
                'ends_at' => now()->addYear(),
                'payment_note' => 'Yinelenen kayıt',
                'document_reference' => 'DEKONT-2',
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $historyDuplicateThrew = true;
        }
        self::assertTrue($historyDuplicateThrew, 'MANUAL-PAY-DB-UNIQUENESS-01: unique(workspace_id, idempotency_key) yinelenen manual_payments satırını reddetmeli.');

        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_id' => $planId,
            'state' => 'active',
            'ends_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscriptionDuplicateThrew = false;
        try {
            DB::table('subscriptions')->insert([
                'workspace_id' => $workspaceId,
                'plan_id' => $planId,
                'state' => 'active',
                'ends_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $subscriptionDuplicateThrew = true;
        }
        self::assertTrue($subscriptionDuplicateThrew, 'MANUAL-PAY-DB-UNIQUENESS-01: unique(workspace_id) her workspace için tek current subscription satırı sağlamalı.');
    }

    // --- MANUAL-PAY-AUTH-01 ---------------------------------------------------

    public function test_discovery_and_manual_payment_endpoints_require_authenticated_and_verified_user(): void
    {
        $unverified = User::factory()->unverified()->create();

        $guestResponse = $this->withHeaders($this->jsonHeaders())->getJson($this->discoveryUri());
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'MANUAL-PAY-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez — route var olmalı).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->getJson($this->discoveryUri());
        $unverifiedResponse->assertStatus(403, 'MANUAL-PAY-AUTH-01: doğrulanmamış kullanıcı 403 ile reddedilmeli.');
    }

    // --- MANUAL-PAY-NOTE-COLUMN-TYPE-01 (reviewer correction) ---------------

    /**
     * Reviewer finding: payment_note is declared VARCHAR/string in the
     * production migration while the validation contract allows up to 2000
     * characters. On common database defaults a plain "string" column
     * caps well below 2000, so the column type itself must be TEXT to
     * honor the existing max:2000 validation contract.
     */
    public function test_manual_payments_payment_note_column_is_text_not_string(): void
    {
        if (! Schema::hasTable('manual_payments')) {
            self::fail('MANUAL-PAY-NOTE-COLUMN-TYPE-01: production "manual_payments" table does not exist yet — RED must come from the missing table, not a substitute.');
        }

        $columnType = Schema::getColumnType('manual_payments', 'payment_note');

        self::assertSame(
            'text',
            $columnType,
            "MANUAL-PAY-NOTE-COLUMN-TYPE-01: manual_payments.payment_note {$columnType} olarak tanımlı — max:2000 doğrulama sözleşmesini onurlandırmak için TEXT olmalı, VARCHAR/string değil."
        );
    }

    // --- MANUAL-PAY-THROTTLE-01 (reviewer correction) -----------------------

    /**
     * Reviewer finding: the manual-payments POST route carries only the
     * existing auth/role middleware group and no throttle, unlike sibling
     * mutating admin/tenant endpoints in this same route file (all of which
     * carry throttle:5,1). This is a source-structure contract on the
     * registered route's middleware list, not a request-timing/HTTP-429
     * probe, so it stays stable regardless of test execution speed.
     */
    public function test_manual_payments_route_carries_throttle_middleware(): void
    {
        $route = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->first(function ($route) {
                return $route->methods()[0] === 'POST'
                    && $route->uri() === 'api/admin/workspaces/{workspace}/manual-payments';
            });

        self::assertNotNull($route, 'MANUAL-PAY-THROTTLE-01: POST manual-payments rotası bulunamadı.');

        $hasThrottle = collect($route->gatherMiddleware())
            ->contains(fn (string $middleware) => str_starts_with($middleware, 'throttle:5,1'));

        self::assertTrue(
            $hasThrottle,
            'MANUAL-PAY-THROTTLE-01: manual-payments rotası mevcut auth/role kapılarına ek olarak throttle:5,1 taşımalı — bu repodaki diğer mutasyon uçlarıyla (workspaces, team invitations, bulk qr) tutarlı biçimde.'
        );
    }

    // --- MANUAL-PAY-LOCK-BEFORE-READS-01 (reviewer correction) --------------

    /**
     * Reviewer finding: EloquentSubscriptionRepository::recordManualPayment
     * does not acquire a per-workspace lock before its idempotency/plan/
     * subscription reads and writes, and its catch(QueryException) branch
     * re-queries inside the same transaction after a unique-violation —
     * which on PostgreSQL leaves the transaction already aborted, so the
     * "raced" re-query itself throws (an InFailedSqlTransaction error),
     * defeating the intended idempotent-replay path.
     *
     * Local CI runs on SQLite, which does not abort the whole transaction on
     * a caught constraint violation the way PostgreSQL does, so this defect
     * cannot be reproduced via HTTP behavior in this environment. This is
     * therefore a narrow, explicitly-documented source-structure contract
     * tied directly to the reviewer finding: it inspects intent (a
     * workspace-scoped lock statement executed before the first
     * idempotency/plan/subscription statement, and no catch-then-requery-
     * in-the-same-transaction shape) rather than asserting on exact
     * whitespace/tokens, so it does not regress on harmless refactors that
     * preserve the same ordering and structure.
     */
    public function test_record_manual_payment_locks_workspace_before_reads_and_never_requeries_after_catching_unique_violation_in_same_transaction(): void
    {
        $path = app_path('Infrastructure/Billing/Persistence/EloquentSubscriptionRepository.php');
        self::assertFileExists($path, 'MANUAL-PAY-LOCK-BEFORE-READS-01: EloquentSubscriptionRepository.php bulunamadı.');

        $source = file_get_contents($path);
        self::assertIsString($source);

        $methodStart = strpos($source, 'function recordManualPayment');
        self::assertIsInt($methodStart, 'MANUAL-PAY-LOCK-BEFORE-READS-01: recordManualPayment metodu bulunamadı.');

        $transactionCallbackStart = strpos($source, 'DB::transaction(function', $methodStart);
        self::assertIsInt($transactionCallbackStart, 'MANUAL-PAY-LOCK-BEFORE-READS-01: recordManualPayment DB::transaction() kullanmalı.');

        $nextMethodStart = strpos($source, "\n    private function", $transactionCallbackStart);
        $transactionBody = $nextMethodStart !== false
            ? substr($source, $transactionCallbackStart, $nextMethodStart - $transactionCallbackStart)
            : substr($source, $transactionCallbackStart);

        $lockPosition = strpos($transactionBody, 'lockForWorkspace');
        self::assertIsInt(
            $lockPosition,
            'MANUAL-PAY-LOCK-BEFORE-READS-01: transaction gövdesi PostgreSQL-güvenli, workspace bazlı bir kilit (ör. pg_advisory_xact_lock/lockForWorkspace) edinmeli; idempotency/plan/subscription okuma-yazmalarından önce.'
        );

        $firstTableAccess = null;
        foreach (["DB::table('manual_payments')", 'DB::table(\'plans\')', 'DB::table(\'subscriptions\')'] as $needle) {
            $pos = strpos($transactionBody, $needle);
            if ($pos !== false && ($firstTableAccess === null || $pos < $firstTableAccess)) {
                $firstTableAccess = $pos;
            }
        }

        self::assertIsInt($firstTableAccess, 'MANUAL-PAY-LOCK-BEFORE-READS-01: transaction içinde beklenen tablo erişimleri bulunamadı.');
        self::assertLessThan(
            $firstTableAccess,
            $lockPosition,
            'MANUAL-PAY-LOCK-BEFORE-READS-01: workspace kilidi, idempotency/plan/subscription okuma-yazmalarından ÖNCE edinilmeli — aksi halde PostgreSQL altında yarış durumu kilitle önlenmez.'
        );

        self::assertDoesNotMatchRegularExpression(
            '/catch\s*\(\s*QueryException[^}]*?DB::table\(/s',
            $transactionBody,
            'MANUAL-PAY-LOCK-BEFORE-READS-01: catch(QueryException) bloğu, zaten iptal edilmiş (aborted) PostgreSQL transaction\'ı içinde tekrar sorgu (DB::table(...)) çalıştırmamalı — bunun yerine önceden edinilen workspace kilidi, unique violation ihtimalini transaction başlamadan ortadan kaldırmalı.'
        );
    }
}
