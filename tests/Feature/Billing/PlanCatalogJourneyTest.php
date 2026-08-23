<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Blind RED test candidate for GET /api/workspaces/{workspace}/plans
 * (real plan-catalog backend, S1-WP05). Grep of routes/api.php confirms
 * zero "plans" routes are registered and there is no plans migration under
 * database/migrations, so every request below is expected to fail RED
 * from the framework's router (unmatched route → 404), not from
 * malformed fixtures or a guessed production response shape.
 *
 * Frozen contract:
 * - route lives inside the existing auth:sanctum + verified group.
 * - workspace Account/Owner can view; Editor/member and strangers receive
 *   an enumeration-safe 404 via a new billing.view permission intended to
 *   be owner-only; guest must be 401/403 but never 404; unverified 403.
 * - owner gets a direct JSON array, not an envelope.
 * - only active plan versions from the real plans table are listed,
 *   deterministically sorted by sort_order then id.
 * - each item exposes exactly: id (positive int), name, code,
 *   entitlements (array of strings), amount_minor (non-negative int or
 *   null), currency (uppercase 3-letter ISO-style string or null). No
 *   version, timestamps, internal state, provider IDs, or secrets.
 * - amount_minor/currency are paired: both set or both null.
 * - empty real table returns [].
 * - plan rows are versionable: an inactive/superseded version must not be
 *   projected, without changing the frontend response schema.
 * - the workspace is used solely for owner/tenant authorization; plans
 *   are platform catalog records, not per-workspace fabricated data.
 *
 * Requirement IDs: PLAN-CATALOG-LIST-01, PLAN-CATALOG-LIST-EMPTY-01,
 * PLAN-CATALOG-VERSION-01, PLAN-CATALOG-OWNER-ONLY-01,
 * PLAN-CATALOG-TENANT-ESCAPE-01, PLAN-CATALOG-AUTH-01,
 * PLAN-CATALOG-SHAPE-01, PLAN-CATALOG-PRICE-PAIR-01.
 */
final class PlanCatalogJourneyTest extends TestCase
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

    private function plansUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/plans";
    }

    /**
     * There is no production "plans" table yet (no migration under
     * database/migrations creates one). If a future implementation adds
     * it before this test file is retired, seed only test-owned rows —
     * never a production seeder, demo row, named commercial tier, or
     * canonical price.
     */
    private function insertPlanRow(array $overrides = []): int
    {
        if (! Schema::hasTable('plans')) {
            self::fail('PLAN-CATALOG: production "plans" table does not exist yet — RED must come from the missing route/table, not from a malformed fixture.');
        }

        return (int) DB::table('plans')->insertGetId(array_merge([
            'name' => 'Test Plan',
            'code' => 'test-plan-'.bin2hex(random_bytes(4)),
            'entitlements' => json_encode(['feature.a', 'feature.b']),
            'amount_minor' => 1990,
            'currency' => 'TRY',
            'sort_order' => 1,
            'version' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // --- PLAN-CATALOG-LIST-01 ---------------------------------------------

    public function test_owner_lists_active_plans_ordered_by_sort_order_then_id_as_a_direct_array(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plan-list-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plan-list-01');

        $this->insertPlanRow(['name' => 'Starter', 'code' => 'starter-01', 'sort_order' => 2]);
        $this->insertPlanRow(['name' => 'Pro', 'code' => 'pro-01', 'sort_order' => 1]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri($workspaceId));

        $response->assertStatus(200, 'PLAN-CATALOG-LIST-01: owner kendi platform plan katalogunu 200 ile almalı.');
        $body = $response->json();

        self::assertIsArray($body, 'PLAN-CATALOG-LIST-01: yanıt gövdesi doğrudan bir dizi olmalı — zarf (envelope) değil.');
        self::assertCount(2, $body);
        self::assertSame('Pro', $body[0]['name'] ?? null, 'PLAN-CATALOG-LIST-01: sort_order artan sırayla döner.');
        self::assertSame('Starter', $body[1]['name'] ?? null);
    }

    // --- PLAN-CATALOG-SHAPE-01 ---------------------------------------------

    public function test_each_item_exposes_exactly_the_frozen_fields_and_nothing_else(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plan-shape-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plan-shape-01');

        $this->insertPlanRow(['name' => 'Starter', 'code' => 'starter-shape-01']);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri($workspaceId));

        $response->assertStatus(200);
        $body = $response->json();

        self::assertIsArray($body);
        self::assertNotEmpty($body);

        foreach ($body as $item) {
            self::assertIsArray($item);
            self::assertSame(
                ['id', 'name', 'code', 'entitlements', 'amount_minor', 'currency'],
                array_keys($item),
                'PLAN-CATALOG-SHAPE-01: her kayıt yalnız id/name/code/entitlements/amount_minor/currency alanlarını taşımalı — model doğrudan serialize edilmemeli.'
            );
            self::assertIsInt($item['id']);
            self::assertGreaterThan(0, $item['id']);
            self::assertIsArray($item['entitlements']);
            foreach ($item['entitlements'] as $entitlement) {
                self::assertIsString($entitlement);
            }
            self::assertArrayNotHasKey('version', $item);
            self::assertArrayNotHasKey('created_at', $item);
            self::assertArrayNotHasKey('updated_at', $item);
            self::assertArrayNotHasKey('is_active', $item);
            self::assertArrayNotHasKey('provider_id', $item);
            self::assertArrayNotHasKey('sort_order', $item);
        }
    }

    // --- PLAN-CATALOG-PRICE-PAIR-01 -----------------------------------------

    public function test_amount_minor_and_currency_are_paired_never_floats_and_currency_is_uppercase_iso(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plan-price-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plan-price-01');

        $this->insertPlanRow([
            'name' => 'Free',
            'code' => 'free-price-01',
            'amount_minor' => null,
            'currency' => null,
            'sort_order' => 1,
        ]);
        $this->insertPlanRow([
            'name' => 'Pro',
            'code' => 'pro-price-01',
            'amount_minor' => 4990,
            'currency' => 'TRY',
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri($workspaceId));

        $response->assertStatus(200);
        $body = $response->json();
        self::assertCount(2, $body);

        $free = $body[0];
        self::assertArrayHasKey('amount_minor', $free);
        self::assertArrayHasKey('currency', $free);
        self::assertNull($free['amount_minor'], 'PLAN-CATALOG-PRICE-PAIR-01: amount_minor null ise currency da null olmalı.');
        self::assertNull($free['currency']);

        $pro = $body[1];
        self::assertIsInt($pro['amount_minor'], 'PLAN-CATALOG-PRICE-PAIR-01: fiyat asla float olmamalı, tamsayı (minor unit) olmalı.');
        self::assertGreaterThanOrEqual(0, $pro['amount_minor']);
        self::assertSame('TRY', $pro['currency']);
        self::assertSame(strtoupper((string) $pro['currency']), $pro['currency'], 'PLAN-CATALOG-PRICE-PAIR-01: currency her zaman büyük harf 3 karakterli ISO benzeri kod olmalı.');
        self::assertSame(3, strlen((string) $pro['currency']));
    }

    // --- PLAN-CATALOG-LIST-EMPTY-01 -----------------------------------------

    public function test_empty_real_plans_table_returns_an_empty_array(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plan-empty-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plan-empty-01');

        if (! Schema::hasTable('plans')) {
            self::fail('PLAN-CATALOG-LIST-EMPTY-01: production "plans" table does not exist yet — RED must come from the missing route/table.');
        }

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri($workspaceId));

        $response->assertStatus(200, 'PLAN-CATALOG-LIST-EMPTY-01: boş katalog için sahte/varsayılan satır eklenmemeli, 200 ile boş dizi dönmeli.');
        self::assertSame([], $response->json());
    }

    // --- PLAN-CATALOG-VERSION-01 --------------------------------------------

    public function test_only_active_plan_versions_are_projected_inactive_superseded_rows_are_excluded(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plan-version-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plan-version-01');

        $this->insertPlanRow([
            'name' => 'Pro (eski sürüm)',
            'code' => 'pro-version-01',
            'version' => 1,
            'is_active' => false,
            'sort_order' => 1,
        ]);
        $this->insertPlanRow([
            'name' => 'Pro (aktif sürüm)',
            'code' => 'pro-version-01',
            'version' => 2,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri($workspaceId));

        $response->assertStatus(200);
        $body = $response->json();

        self::assertCount(1, $body, 'PLAN-CATALOG-VERSION-01: yalnız aynı stable code için aktif plan versiyonu projekte edilmeli — eski (inactive) versiyon dışlanır.');
        self::assertSame('Pro (aktif sürüm)', $body[0]['name'] ?? null);
        self::assertArrayNotHasKey('version', $body[0]);
        self::assertArrayNotHasKey('is_active', $body[0]);
    }

    // --- PLAN-CATALOG-OWNER-ONLY-01 -----------------------------------------

    public function test_member_and_editor_are_denied_with_enumeration_safe_404_not_403(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plan-owner-only-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plan-owner-only-01');

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-plan-owner-only-01@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-plan-owner-only-01@example.test');
        $this->addMember($workspaceId, $editor, 'editor');

        $memberResponse = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri($workspaceId));
        $memberResponse->assertStatus(404, 'PLAN-CATALOG-OWNER-ONLY-01: member billing.view taşımaz — 404 ile reddedilmeli.');

        $editorResponse = $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri($workspaceId));
        $editorResponse->assertStatus(404, 'PLAN-CATALOG-OWNER-ONLY-01: editor billing.view taşımaz — 404 ile reddedilmeli, 403 değil.');
    }

    // --- PLAN-CATALOG-TENANT-ESCAPE-01 ---------------------------------------

    public function test_foreign_or_no_membership_user_gets_enumeration_safe_404_matching_nonexistent_workspace(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plan-escape-01@example.test');
        $stranger = $this->verifiedUser('Can Öztürk', 'can-plan-escape-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plan-escape-01');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri($workspaceId));
        $strangerResponse->assertStatus(404, 'PLAN-CATALOG-TENANT-ESCAPE-01: üye olunmayan workspace plan kataloğu okunamaz (404).');

        $nonexistentResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri(999999999));

        self::assertSame(
            $strangerResponse->getStatusCode(),
            $nonexistentResponse->getStatusCode(),
            'PLAN-CATALOG-TENANT-ESCAPE-01: yabancı ve var-olmayan workspace aynı imzayı taşımalı (enumeration-safe).'
        );
    }

    // --- PLAN-CATALOG-AUTH-01 -------------------------------------------------

    public function test_endpoint_requires_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-plan-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-plan-auth-01');
        $unverified = User::factory()->unverified()->create();

        $guestResponse = $this->withHeaders($this->jsonHeaders())->getJson($this->plansUri($workspaceId));
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'PLAN-CATALOG-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->getJson($this->plansUri($workspaceId));
        $unverifiedResponse->assertStatus(403, 'PLAN-CATALOG-AUTH-01: doğrulanmamış (unverified) kullanıcı 403 ile reddedilmeli.');
    }
}
