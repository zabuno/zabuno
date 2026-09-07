<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Port\SubscriptionRepositoryPort;
use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\Publication\UseCase\ApplyGuestRichMedia;
use App\Domain\Entitlement\Entitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ABONELİĞİN EKSİK YARISI — iptal, düşürme, ödemesiz süre, geri dönüş
 * (`docs/107` Faz 1.3, `docs/134`).
 *
 * Dondurulan sözleşme:
 *
 *  1. **İPTAL ÖDENMİŞ DÖNEMİ KESMEZ.** İptal eden sahip `ends_at`'e kadar
 *     yeteneklerini KORUR; duran tek şey yenilemedir. `subscriptions.state`
 *     değişmez — değişseydi yetenekler o saniye kapanırdı.
 *  2. **İPTALDEN CAYMA YENİDEN ÖDEME İSTEMEZ.** Dönem bitmeden fikir
 *     değiştiren sahip tek bir istekle geri döner; dönem bittikten sonra bu
 *     yol kapanır (422 `period_over`) çünkü oradaki geri dönüş ödemedir.
 *  3. **DÜŞÜRME DÖNEM SONUNDA, İADESİZ.** Ödenmiş dönem boyunca hiçbir
 *     yetenek eksilmez; fark iade edilmez (yayınlanmış İptal ve İade
 *     Politikası: "başlamış bir dönemin ücreti iade edilmez"). Ödenmiş
 *     dönemin ORTASINDA ucuz planı satın almak da reddedilir
 *     (422 `downgrade_requires_schedule`) — o yol sessiz bir düşürmeydi.
 *  4. **ÖDEMESİZ SÜRE.** Dönem bittiğinde yetenekler
 *     `billing.subscription.grace_days` gün daha açık kalır; sonra askı.
 *     Başarılı bir ödeme askıyı ELLE MÜDAHALE OLMADAN kaldırır.
 *  5. **MİSAFİR ETKİLENMEZ.** Askı da düşürme de yayınlanmış menünün DONMUŞ
 *     haklarına dokunmaz: masadaki basılı karekod aynı sayfayı göstermeye
 *     devam eder. Bu deponun bir kez yaşadığı kusur ailesi budur
 *     (`EloquentPublicationRepository::current()`).
 *  6. **DENETİM.** Dördü de `platform_audits`'e düşer (scope
 *     `billing.subscription`); hiçbiri deftere ya da faturaya yazmaz, çünkü
 *     hiçbiri para hareketi değildir.
 */
final class SubscriptionLifecycleJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const PRO_AMOUNT = 149900;

    private const STARTER_AMOUNT = 49900;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // --- kurulum yardımcıları --------------------------------------------

    private function verifiedUser(string $email): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    private function workspaceOwnedBy(User $owner): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kadıköy Kebap',
            'slug' => 'kadikoy-'.Str::random(8),
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

    /** @param list<string> $entitlements */
    private function insertPlan(string $name, int $amountMinor, array $entitlements): int
    {
        return (int) DB::table('plans')->insertGetId([
            'name' => $name,
            'code' => strtolower($name).'-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => json_encode($entitlements),
            'amount_minor' => $amountMinor,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function subscribe(int $workspaceId, int $planId, string $endsAt): void
    {
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_id' => $planId,
            'state' => 'active',
            'ends_at' => Carbon::parse($endsAt),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function proPlan(): int
    {
        return $this->insertPlan('Pro', self::PRO_AMOUNT, [
            Entitlement::MenuRichMedia->value,
            Entitlement::AnalyticsReporting->value,
            Entitlement::QrBulkGeneration->value,
        ]);
    }

    private function starterPlan(): int
    {
        return $this->insertPlan('Starter', self::STARTER_AMOUNT, [
            Entitlement::QrBulkGeneration->value,
        ]);
    }

    private function grants(int $workspaceId, Entitlement $entitlement): bool
    {
        return app(EntitlementRepositoryPort::class)->forWorkspace($workspaceId)->grants($entitlement);
    }

    /** @return array<string, mixed> */
    private function auditDetails(string $action): array
    {
        $row = DB::table('platform_audits')
            ->where('scope', 'billing.subscription')
            ->where('action', $action)
            ->orderByDesc('id')
            ->first();

        self::assertNotNull($row, "LIFECYCLE-AUDIT: `{$action}` bir denetim kaydı bırakmalı.");

        return json_decode((string) $row->details, true) ?? [];
    }

    // --- 1. İPTAL ---------------------------------------------------------

    #[Test]
    public function test_cancelling_stops_renewal_but_keeps_the_paid_period_and_its_capabilities(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('cancel-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/subscription/cancellation");

        $response->assertOk();
        $response->assertJsonPath('phase', 'cancelling');
        self::assertSame(
            '2026-09-30T10:00:00+00:00',
            $response->json('ends_at'),
            'LIFECYCLE-CANCEL-01: iptal ÖDENMİŞ DÖNEMİ kısaltmaz; bitiş aynı kalır.',
        );

        self::assertTrue(
            $this->grants($workspaceId, Entitlement::MenuRichMedia),
            'LIFECYCLE-CANCEL-01: iptalden sonra ödenmiş dönem boyunca yetenekler DURUR.',
        );

        self::assertSame(
            'active',
            DB::table('subscriptions')->where('workspace_id', $workspaceId)->value('state'),
            'LIFECYCLE-CANCEL-01: `state` sütunu değişmez — değişseydi hizmet o saniye kesilirdi.',
        );

        $details = $this->auditDetails('cancelled');
        self::assertSame('2026-09-30T10:00:00+00:00', $details['service_until']);
        self::assertSame('active', $details['phase_before']);
        self::assertSame('cancelling', $details['phase_after']);

        self::assertSame(
            0,
            DB::table('ledger_entries')->where('workspace_id', $workspaceId)->count(),
            'LIFECYCLE-CANCEL-01: iptal para hareketi DEĞİLDİR; deftere satır yazılmaz.',
        );
        self::assertSame(
            0,
            DB::table('invoices')->where('workspace_id', $workspaceId)->count(),
            'LIFECYCLE-CANCEL-01: iptal bir tahsilat değildir; belge kesilmez.',
        );
    }

    #[Test]
    public function test_a_cancelled_subscription_ends_without_a_grace_period_once_the_paid_period_is_over(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('ended-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');

        $this->actingAs($owner)->postJson("/api/workspaces/{$workspaceId}/subscription/cancellation")->assertOk();

        Carbon::setTestNow('2026-10-01 10:00:00');

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/subscription")
            ->assertOk()
            ->assertJsonPath('phase', 'ended');

        self::assertFalse(
            $this->grants($workspaceId, Entitlement::MenuRichMedia),
            'LIFECYCLE-CANCEL-02: iptal edilmiş aboneliğe ödemesiz süre VERİLMEZ — beklenen bir ödeme yoktur.',
        );
    }

    #[Test]
    public function test_the_owner_can_withdraw_a_cancellation_without_paying_again(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('withdraw-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');

        $this->actingAs($owner)->postJson("/api/workspaces/{$workspaceId}/subscription/cancellation")->assertOk();

        $this->actingAs($owner)
            ->deleteJson("/api/workspaces/{$workspaceId}/subscription/cancellation")
            ->assertOk()
            ->assertJsonPath('phase', 'active')
            ->assertJsonPath('cancelled_at', null)
            ->assertJsonPath('ends_at', '2026-09-30T10:00:00+00:00');

        self::assertSame(
            0,
            DB::table('payment_transactions')->where('workspace_id', $workspaceId)->count(),
            'LIFECYCLE-RESUME-01: caymak için YENİDEN ÖDEME yapılmaz — hiçbir ödeme işlemi doğmaz.',
        );

        $this->auditDetails('cancellation_withdrawn');
    }

    #[Test]
    public function test_a_cancellation_cannot_be_withdrawn_after_the_paid_period_is_over(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('late-withdraw@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');

        $this->actingAs($owner)->postJson("/api/workspaces/{$workspaceId}/subscription/cancellation")->assertOk();

        Carbon::setTestNow('2026-10-05 10:00:00');

        $this->actingAs($owner)
            ->deleteJson("/api/workspaces/{$workspaceId}/subscription/cancellation")
            ->assertStatus(422)
            ->assertJsonPath('reason', 'period_over');
    }

    #[Test]
    public function test_cancellation_needs_the_billing_manage_permission(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('perm-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');

        $outsider = $this->verifiedUser('outsider@example.test');

        $this->actingAs($outsider)
            ->postJson("/api/workspaces/{$workspaceId}/subscription/cancellation")
            ->assertNotFound();

        self::assertNull(
            DB::table('subscriptions')->where('workspace_id', $workspaceId)->value('cancelled_at'),
            'LIFECYCLE-CANCEL-03: yetkisiz istek hiçbir şey değiştirmez.',
        );
    }

    // --- 2. PLAN DÜŞÜRME VE YÜKSELTME -------------------------------------

    #[Test]
    public function test_a_downgrade_preview_names_the_capability_that_will_be_lost_and_when(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('preview-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');
        $starterId = $this->starterPlan();

        $response = $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/subscription/plan-change?plan_id={$starterId}");

        $response->assertOk();
        $response->assertJsonPath('direction', 'downgrade');
        $response->assertJsonPath('effective_at', '2026-09-30T10:00:00+00:00');
        $response->assertJsonPath('already_scheduled', false);

        $losing = array_column($response->json('losing'), 'key');
        sort($losing);

        self::assertSame(
            [Entitlement::AnalyticsReporting->value, Entitlement::MenuRichMedia->value],
            $losing,
            'LIFECYCLE-DOWN-01: sahip NE KAYBEDECEĞİNİ ödemeden ve karar vermeden ÖNCE adıyla görmeli.',
        );

        self::assertSame(
            'Zengin görsel',
            collect($response->json('losing'))->firstWhere('key', Entitlement::MenuRichMedia->value)['label'],
            'LIFECYCLE-DOWN-01: etiketin tek sahibi `Entitlement` enum\'udur ve sunucudan gelir.',
        );
    }

    #[Test]
    public function test_a_scheduled_downgrade_takes_nothing_away_before_the_paid_period_ends(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('schedule-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $proId = $this->proPlan();
        $this->subscribe($workspaceId, $proId, '2026-09-30 10:00:00');
        $starterId = $this->starterPlan();

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/subscription/plan-change", ['plan_id' => $starterId])
            ->assertOk()
            ->assertJsonPath('phase', 'active')
            ->assertJsonPath('scheduled_plan_id', $starterId)
            ->assertJsonPath('plan_id', $proId);

        self::assertTrue(
            $this->grants($workspaceId, Entitlement::MenuRichMedia),
            'LIFECYCLE-DOWN-02: düşürme ödenmiş dönemden TEK BİR GÜN ya da yetenek eksiltmez — iade de doğmaz.',
        );

        self::assertSame(
            0,
            DB::table('ledger_entries')->where('workspace_id', $workspaceId)->count(),
            'LIFECYCLE-DOWN-02: fark iadesi YOKTUR; deftere hiçbir satır düşmez.',
        );

        $details = $this->auditDetails('downgrade_scheduled');
        self::assertSame('2026-09-30T10:00:00+00:00', $details['effective_at']);
        self::assertContains(Entitlement::MenuRichMedia->value, $details['losing']);
    }

    #[Test]
    public function test_a_scheduled_downgrade_becomes_effective_exactly_at_the_end_of_the_paid_period(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('effective-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');
        $starterId = $this->starterPlan();

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/subscription/plan-change", ['plan_id' => $starterId])
            ->assertOk();

        // Dönemin son saniyesi: hâlâ Pro.
        Carbon::setTestNow('2026-09-30 09:59:59');
        self::assertTrue($this->grants($workspaceId, Entitlement::MenuRichMedia));

        // Dönem bitti, ödemesiz süre içindeyiz: artık Starter'ın hakları.
        Carbon::setTestNow('2026-10-01 10:00:00');
        self::assertFalse(
            $this->grants($workspaceId, Entitlement::MenuRichMedia),
            'LIFECYCLE-DOWN-03: düşürme dönem sonunda yürürlüğe girer.',
        );
        self::assertTrue(
            $this->grants($workspaceId, Entitlement::QrBulkGeneration),
            'LIFECYCLE-DOWN-03: inilen planın hakları ödemesiz süre boyunca DURUR — hepsi birden kapanmaz.',
        );

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/subscription")
            ->assertOk()
            ->assertJsonPath('phase', 'grace')
            ->assertJsonPath('plan_id', $starterId);
    }

    #[Test]
    public function test_a_scheduled_downgrade_can_be_withdrawn(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('unschedule-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');
        $starterId = $this->starterPlan();

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/subscription/plan-change", ['plan_id' => $starterId])
            ->assertOk();

        $this->actingAs($owner)
            ->deleteJson("/api/workspaces/{$workspaceId}/subscription/plan-change")
            ->assertOk()
            ->assertJsonPath('scheduled_plan_id', null);

        $this->auditDetails('downgrade_withdrawn');
    }

    #[Test]
    public function test_an_upgrade_is_refused_by_the_plan_change_endpoint_because_its_path_is_payment(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('upgrade-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $starterId = $this->starterPlan();
        $this->subscribe($workspaceId, $starterId, '2026-09-30 10:00:00');
        $proId = $this->proPlan();

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/subscription/plan-change", ['plan_id' => $proId])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'not_a_downgrade');

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/subscription/plan-change?plan_id={$proId}")
            ->assertOk()
            ->assertJsonPath('direction', 'upgrade');
    }

    #[Test]
    public function test_paying_for_a_cheaper_plan_mid_period_is_refused_instead_of_silently_downgrading(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('silent-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');
        $starterId = $this->starterPlan();

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/checkout", [
                'plan_id' => $starterId,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'downgrade_requires_schedule');

        self::assertSame(
            0,
            DB::table('payment_transactions')->where('workspace_id', $workspaceId)->count(),
            'LIFECYCLE-DOWN-04: red sağlayıcıya gitmeden önce olur; hiçbir işlem satırı ayrılmaz.',
        );

        self::assertTrue(
            $this->grants($workspaceId, Entitlement::MenuRichMedia),
            'LIFECYCLE-DOWN-04: sahip para ödeyip aynı anda yetenek KAYBETMEZ.',
        );
    }

    #[Test]
    public function test_cancelling_clears_a_scheduled_downgrade(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('both-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-30 10:00:00');
        $starterId = $this->starterPlan();

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/subscription/plan-change", ['plan_id' => $starterId])
            ->assertOk();

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspaceId}/subscription/cancellation")
            ->assertOk()
            ->assertJsonPath('scheduled_plan_id', null);
    }

    // --- 3. ÖDEMESİZ SÜRE VE ASKI ----------------------------------------

    #[Test]
    public function test_a_missed_payment_does_not_close_the_account_on_the_same_second(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('grace-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-08 09:00:00');

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/subscription")
            ->assertOk()
            ->assertJsonPath('phase', 'grace')
            ->assertJsonPath('grace_ends_at', '2026-09-15T09:00:00+00:00');

        self::assertTrue(
            $this->grants($workspaceId, Entitlement::MenuRichMedia),
            'LIFECYCLE-GRACE-01: dönem bittiği saniye yetenekler kapanmaz.',
        );
    }

    #[Test]
    public function test_the_grace_window_comes_from_configuration_and_is_not_hard_coded(): void
    {
        config()->set('billing.subscription.grace_days', 3);

        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('config-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-06 10:00:00');

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/subscription")
            ->assertOk()
            ->assertJsonPath('grace_ends_at', '2026-09-09T10:00:00+00:00');

        self::assertTrue($this->grants($workspaceId, Entitlement::MenuRichMedia));

        Carbon::setTestNow('2026-09-10 10:00:00');
        self::assertFalse(
            $this->grants($workspaceId, Entitlement::MenuRichMedia),
            'LIFECYCLE-GRACE-02: süre yapılandırmadan gelir; koda gömülü bir gün sayısı yoktur.',
        );
    }

    #[Test]
    public function test_the_grace_window_can_never_outlast_a_paid_period(): void
    {
        // Bir dönemden uzun bir ödemesiz süre, hiç ödemeyeni ödeyenle eşitlerdi.
        config()->set('billing.subscription.period_days', 30);
        config()->set('billing.subscription.grace_days', 400);

        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('ceiling-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-01 10:00:00');

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/subscription")
            ->assertOk()
            ->assertJsonPath('grace_ends_at', '2026-10-01T10:00:00+00:00');
    }

    #[Test]
    public function test_after_the_grace_window_the_plan_capabilities_close_but_the_workspace_survives(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');

        $owner = $this->verifiedUser('suspended-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-01 10:00:00');

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/subscription")
            ->assertOk()
            ->assertJsonPath('phase', 'suspended');

        self::assertFalse($this->grants($workspaceId, Entitlement::MenuRichMedia));

        self::assertSame(
            'active',
            DB::table('workspaces')->where('id', $workspaceId)->value('state'),
            'LIFECYCLE-SUSPEND-01: askı HESABI KAPATMAZ — veri durur, sahip panelini kullanmaya devam eder.',
        );
    }

    #[Test]
    public function test_a_successful_payment_lifts_the_suspension_without_anyone_touching_the_database(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');

        $owner = $this->verifiedUser('return-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $proId = $this->proPlan();
        $this->subscribe($workspaceId, $proId, '2026-09-01 10:00:00');
        $starterId = $this->starterPlan();

        // Askıdayken sahip önce iptal etmiş de olabilirdi; niyet ne olursa
        // olsun ödeme onu siler.
        DB::table('subscriptions')->where('workspace_id', $workspaceId)->update([
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $owner->id,
            'scheduled_plan_id' => $starterId,
            'scheduled_at' => now(),
        ]);

        self::assertFalse($this->grants($workspaceId, Entitlement::MenuRichMedia));

        app(SubscriptionRepositoryPort::class)->extendFromPayment($workspaceId, $proId, 30);

        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$workspaceId}/subscription")
            ->assertOk()
            ->assertJsonPath('phase', 'active')
            ->assertJsonPath('cancelled_at', null)
            ->assertJsonPath('scheduled_plan_id', null)
            ->assertJsonPath('plan_id', $proId);

        self::assertTrue(
            $this->grants($workspaceId, Entitlement::MenuRichMedia),
            'LIFECYCLE-RETURN-01: başarılı ödeme hesabı ELLE MÜDAHALE OLMADAN eski hâline döndürür.',
        );
    }

    // --- 5. MİSAFİR ------------------------------------------------------

    #[Test]
    public function test_neither_suspension_nor_a_downgrade_changes_what_the_guest_already_sees(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00');

        $owner = $this->verifiedUser('guest-owner@kadikoykebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, $this->proPlan(), '2026-09-09 10:00:00');

        [$menuId, $locationId] = $this->menuAndLocation($workspaceId);

        $snapshot = [
            'categories' => [[
                'name' => 'Kebaplar',
                'menuItems' => [[
                    'productName' => 'Adana',
                    'priceMinorAmount' => 32000,
                    'currencyCode' => 'TRY',
                    'allergens' => [],
                    'imageUrl' => 'https://cdn.example.test/adana.jpg',
                ]],
            ]],
        ];

        $publications = app(PublicationRepositoryPort::class);
        $publications->publish($workspaceId, $menuId, $locationId, $snapshot, (int) $owner->id);

        // Dönem biter, ödemesiz süre de biter: canlı planın hiçbir hakkı yok.
        Carbon::setTestNow('2026-10-20 10:00:00');
        self::assertFalse($this->grants($workspaceId, Entitlement::MenuRichMedia));

        $current = $publications->current($workspaceId, $menuId);
        self::assertNotNull($current);
        self::assertContains(
            Entitlement::MenuRichMedia->value,
            $current->entitlementKeys ?? [],
            'LIFECYCLE-GUEST-01: yayının DONMUŞ hakkı `current()` üzerinden de taşınır — bu kusuru bu depo bir kez yaşadı.',
        );

        $drawn = app(ApplyGuestRichMedia::class)->forPublication($current);

        self::assertSame(
            'https://cdn.example.test/adana.jpg',
            $drawn->snapshot['categories'][0]['menuItems'][0]['imageUrl'] ?? null,
            'LIFECYCLE-GUEST-01: restoranın ödeme sorunu, masadaki basılı karekodun gösterdiği sayfayı DEĞİŞTİRMEZ.',
        );
    }

    /** @return array{0: int, 1: int} */
    private function menuAndLocation(int $workspaceId): array
    {
        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Kadıköy Kebap',
            'slug' => 'kebap-'.Str::lower(Str::random(8)), 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Moda', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Moda Cad. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId, 'location_id' => $locationId,
            'name' => 'Ana menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$menuId, $locationId];
    }
}
