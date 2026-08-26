<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlement;

use App\Application\Entitlement\Exception\EntitlementDeniedException;
use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Domain\Entitlement\Entitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CORE-04 Entitlements — çözümleme ve zorlama sözleşmesi.
 *
 * `plans.entitlements` sütunu vardı ve YALNIZ gösterim için kullanılıyordu:
 * bir plan satın alınabiliyor, hiçbir yetenek kapanmıyordu. Bu testler
 * mekanizmanın sözleşmesini dondurur.
 *
 * Kapsam kuralı: entitlement EK yetki verir, temel yolculuğu kapatmaz.
 * Hangi özelliğin ücretli olduğu bir FİYATLANDIRMA kararıdır ve owner'a
 * aittir; bu katman yalnız kararı uygulanabilir kılar.
 *
 * Requirement ID'leri: ENT-RESOLVE-01, ENT-EXPIRED-02, ENT-UNKNOWN-03,
 * ENT-REQUIRE-04, ENT-QUERY-05.
 */
final class EntitlementResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** @param list<string> $entitlements */
    private function workspaceOnPlan(
        User $owner,
        array $entitlements,
        string $state = 'active',
        string $endsAt = '+30 days',
        bool $planActive = true,
    ): int {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'zeytin-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $planId = (int) DB::table('plans')->insertGetId([
            'name' => 'Pro', 'code' => 'pro-'.uniqid(), 'version' => 1,
            'is_active' => $planActive, 'sort_order' => 0,
            'entitlements' => json_encode($entitlements),
            'amount_minor' => 10000, 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId, 'plan_id' => $planId, 'state' => $state,
            'ends_at' => date('Y-m-d H:i:s', strtotime($endsAt)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $workspaceId;
    }

    private function resolver(): EntitlementRepositoryPort
    {
        return app(EntitlementRepositoryPort::class);
    }

    // --- ENT-RESOLVE-01 ---------------------------------------------------

    public function test_an_active_plan_grants_exactly_the_capabilities_it_lists(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOnPlan($owner, [Entitlement::QrBulkGeneration->value]);

        $set = $this->resolver()->forWorkspace($workspaceId);

        self::assertTrue($set->grants(Entitlement::QrBulkGeneration), 'ENT-RESOLVE-01: listelenen yetenek verilmeli.');
        self::assertFalse($set->grants(Entitlement::TeamInvitations), 'ENT-RESOLVE-01: listelenmeyen yetenek verilmemeli.');
    }

    public function test_a_workspace_without_a_subscription_grants_nothing_but_is_not_an_error(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Plansız', 'slug' => 'plansiz-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $set = $this->resolver()->forWorkspace($workspaceId);

        self::assertSame([], $set->keys(), 'ENT-RESOLVE-01: aboneliği olmayan workspace boş küme almalı, hata değil.');
    }

    // --- ENT-EXPIRED-02 ---------------------------------------------------

    public function test_an_expired_or_inactive_subscription_grants_nothing(): void
    {
        $owner = $this->verifiedUser();

        $expired = $this->workspaceOnPlan($owner, [Entitlement::QrBulkGeneration->value], 'active', '-1 day');
        self::assertFalse(
            $this->resolver()->forWorkspace($expired)->grants(Entitlement::QrBulkGeneration),
            'ENT-EXPIRED-02: süresi dolmuş abonelik yetenek vermemeli — durum alanı geç güncellenmiş olabilir, tarih daha güvenilir kanıttır.'
        );

        $cancelled = $this->workspaceOnPlan($owner, [Entitlement::QrBulkGeneration->value], 'cancelled');
        self::assertFalse(
            $this->resolver()->forWorkspace($cancelled)->grants(Entitlement::QrBulkGeneration),
            'ENT-EXPIRED-02: iptal edilmiş abonelik yetenek vermemeli.'
        );

        $inactivePlan = $this->workspaceOnPlan($owner, [Entitlement::QrBulkGeneration->value], 'active', '+30 days', false);
        self::assertFalse(
            $this->resolver()->forWorkspace($inactivePlan)->grants(Entitlement::QrBulkGeneration),
            'ENT-EXPIRED-02: pasif plan yetenek vermemeli.'
        );
    }

    // --- ENT-UNKNOWN-03 ---------------------------------------------------

    public function test_an_unknown_entitlement_key_grants_nothing_and_does_not_break_resolution(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOnPlan($owner, ['uydurma.anahtar', Entitlement::TeamInvitations->value]);

        $set = $this->resolver()->forWorkspace($workspaceId);

        self::assertSame(
            [Entitlement::TeamInvitations->value],
            $set->keys(),
            'ENT-UNKNOWN-03: tanınmayan anahtar düşürülmeli — bilinmeyeni yok saymak, ona güvenmekten güvenlidir.'
        );
    }

    // --- ENT-REQUIRE-04 ---------------------------------------------------

    public function test_require_entitlement_throws_with_a_message_that_names_the_capability(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOnPlan($owner, []);
        $require = app(RequireEntitlement::class);

        self::assertFalse($require->allows($workspaceId, Entitlement::AnalyticsReporting));

        try {
            $require->handle($workspaceId, Entitlement::AnalyticsReporting);
            self::fail('ENT-REQUIRE-04: eksik yetenek fırlatmalı.');
        } catch (EntitlementDeniedException $e) {
            self::assertSame(Entitlement::AnalyticsReporting, $e->entitlement);
            self::assertStringContainsString(
                Entitlement::AnalyticsReporting->label(),
                $e->getMessage(),
                'ENT-REQUIRE-04: mesaj hangi yeteneğin eksik olduğunu SÖYLEMELİ; söylemeyen bir ret kullanıcıyı çıkışsız bırakır.'
            );
        }
    }

    // --- ENT-QUERY-05 -----------------------------------------------------

    public function test_the_query_endpoint_reports_every_known_capability_not_only_the_granted_ones(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOnPlan($owner, [Entitlement::TeamInvitations->value]);

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/entitlements");

        $response->assertStatus(200);

        $capabilities = collect($response->json('capabilities'));

        self::assertCount(
            count(Entitlement::cases()),
            $capabilities,
            'ENT-QUERY-05: arayüz "planınızda yok" diyebilmek için verilmeyeni de bilmeli.'
        );
        self::assertTrue($capabilities->firstWhere('key', Entitlement::TeamInvitations->value)['granted']);
        self::assertFalse($capabilities->firstWhere('key', Entitlement::QrBulkGeneration->value)['granted']);
    }

    public function test_a_stranger_cannot_learn_that_the_workspace_exists(): void
    {
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();
        $workspaceId = $this->workspaceOnPlan($owner, []);

        $this->actingAs($stranger)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/entitlements")
            ->assertStatus(404);
    }
}
