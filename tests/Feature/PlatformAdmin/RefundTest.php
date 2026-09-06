<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * REFUND — iade yolu (docs/107 Faz 1.1: "başarısız ödeme ve iade yolu").
 *
 * Yalnız süperadmin, yalnız sebeple. İade edilen ödeme sağlayıcıya
 * Iyzipay Refund isteği olarak gider (paymentTransactionId + tutar +
 * para birimi); sağlayıcı reddederse hiçbir şey değişmez. Kabul ederse:
 * işlem `refunded`, defterde TERS satır (revenue borç / cash alacak),
 * abonelik bitişi iade edilen DÖNEM kadar geri çekilir, denetim satırı.
 *
 * Politika — "iade edilen dönem düşülür": iade, o ödemenin satın aldığı
 * süreyi geri alır; abonelik daha sonra başka bir ödemeyle uzatılmışsa o
 * süre korunur. Bitişi "ödeme öncesine geri sar" demek, aradaki başka bir
 * ödemeyi de sessizce silmek olurdu.
 */
final class RefundTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_GATEWAY = 'App\Application\Billing\Port\SandboxPaymentGatewayPort';

    private const AMOUNT = 149900;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin-refund-'.bin2hex(random_bytes(2)).'@example.test', 'email_verified_at' => now()]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id,
            'role' => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** @return array{workspaceId: int, ownerId: int, planId: int} */
    private function workspaceWithPlan(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kadıköy Kebap',
            'slug' => 'refund-'.bin2hex(random_bytes(3)),
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
        $planId = (int) DB::table('plans')->insertGetId([
            'name' => 'Pro',
            'code' => 'pro-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => json_encode(['menu.publish']),
            'amount_minor' => self::AMOUNT,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['workspaceId' => $workspaceId, 'ownerId' => $owner->id, 'planId' => $planId];
    }

    /** Başarılı bir ödemenin bıraktığı iz: işlem + abonelik + defter satırı. */
    private function succeededPayment(array $ws, string $state = 'succeeded'): int
    {
        $conversationId = '66666666-6666-4666-8666-'.bin2hex(random_bytes(6));
        $id = (int) DB::table('payment_transactions')->insertGetId([
            'workspace_id' => $ws['workspaceId'],
            'actor_user_id' => $ws['ownerId'],
            'plan_id' => $ws['planId'],
            'mode' => 'sandbox',
            'idempotency_key' => $conversationId,
            'conversation_id' => $conversationId,
            'token' => 'tok-'.$conversationId,
            'redirect_url' => 'https://cf.iyzipay.com/x',
            'amount_minor' => self::AMOUNT,
            'currency' => 'TRY',
            'period_days' => 30,
            'state' => $state,
            'payment_id' => 'pay-'.$conversationId,
            'payment_transaction_id' => 'paytx-'.$conversationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriptions')->updateOrInsert(['workspace_id' => $ws['workspaceId']], [
            'plan_id' => $ws['planId'],
            'state' => 'active',
            'ends_at' => Carbon::parse('2026-11-05 10:00:00'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($state === 'succeeded') {
            DB::table('ledger_entries')->insert([
                'workspace_id' => $ws['workspaceId'],
                'reference' => 'payment:'.$id,
                'debit_account' => 'cash',
                'credit_account' => 'revenue',
                'amount_minor' => self::AMOUNT,
                'currency_code' => 'TRY',
                'description' => 'Subscription payment',
                'occurred_at' => now()->toDateTimeString(),
                'created_at' => now(),
            ]);
        }

        return $id;
    }

    private function uri(int $workspaceId, int $transactionId): string
    {
        return "/api/admin/workspaces/{$workspaceId}/transactions/{$transactionId}/refund";
    }

    private function bindGateway(): MockInterface
    {
        config()->set('services.iyzico.mode', 'sandbox');
        config()->set('billing.subscription.period_days', 30);
        $fake = Mockery::mock(self::SANDBOX_GATEWAY);
        app()->instance(self::SANDBOX_GATEWAY, $fake);

        return $fake;
    }

    #[Test]
    public function refund_is_superadmin_only_needs_a_reason_and_is_enumeration_safe(): void
    {
        Carbon::setTestNow('2026-10-06 10:00:00');
        $ws = $this->workspaceWithPlan();
        $id = $this->succeededPayment($ws);
        $this->bindGateway()->shouldNotReceive('refund');
        $owner = User::query()->findOrFail($ws['ownerId']);
        $admin = $this->admin();

        $this->withHeaders($this->jsonHeaders())->postJson($this->uri($ws['workspaceId'], $id), ['reason' => 'x'])->assertStatus(401);
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson($this->uri($ws['workspaceId'], $id), ['reason' => 'x'])->assertStatus(404);

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($ws['workspaceId'], $id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($ws['workspaceId'], 999999), ['reason' => 'Duplicate charge'])
            ->assertStatus(404);

        $other = $this->workspaceWithPlan();
        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($other['workspaceId'], $id), ['reason' => 'Duplicate charge'])
            ->assertStatus(404, 'Başka çalışma alanının işlemi bu adresten görünmez.');

        self::assertSame('succeeded', DB::table('payment_transactions')->where('id', $id)->value('state'));
    }

    #[Test]
    public function only_a_succeeded_payment_can_be_refunded(): void
    {
        $ws = $this->workspaceWithPlan();
        $failed = $this->succeededPayment($ws, 'failed');
        $this->bindGateway()->shouldNotReceive('refund');

        $this->actingAs($this->admin())->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($ws['workspaceId'], $failed), ['reason' => 'Customer asked'])
            ->assertStatus(409);
    }

    #[Test]
    public function a_refund_the_provider_rejects_changes_nothing(): void
    {
        Carbon::setTestNow('2026-10-06 10:00:00');
        $ws = $this->workspaceWithPlan();
        $id = $this->succeededPayment($ws);
        $this->bindGateway()->shouldReceive('refund')->once()->andReturn([
            'status' => 'failure',
            'payment_id' => 'pay-x',
            'conversation_id' => 'x',
            'amount_minor' => self::AMOUNT,
            'currency' => 'TRY',
            'error_message' => 'Refund window closed',
        ]);

        $this->actingAs($this->admin())->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($ws['workspaceId'], $id), ['reason' => 'Customer asked'])
            ->assertStatus(502);

        self::assertSame('succeeded', DB::table('payment_transactions')->where('id', $id)->value('state'));
        self::assertSame(1, DB::table('ledger_entries')->where('workspace_id', $ws['workspaceId'])->count());
        self::assertTrue(Carbon::parse(DB::table('subscriptions')->where('workspace_id', $ws['workspaceId'])->value('ends_at'))->equalTo(Carbon::parse('2026-11-05 10:00:00')));
        self::assertSame(0, DB::table('platform_audits')->where('scope', 'billing.refund')->count());
    }

    #[Test]
    public function a_refund_reverses_the_ledger_shortens_the_subscription_by_the_paid_period_and_is_audited_once(): void
    {
        Carbon::setTestNow('2026-10-06 10:00:00');
        $ws = $this->workspaceWithPlan();
        $id = $this->succeededPayment($ws);
        $row = DB::table('payment_transactions')->where('id', $id)->first();
        $admin = $this->admin();

        $this->bindGateway()->shouldReceive('refund')->once()
            ->withArgs(function (string $conversationId, string $paymentTransactionId, int $amount, string $currency, string $reason) use ($row): bool {
                self::assertSame($row->conversation_id, $conversationId);
                self::assertSame($row->payment_transaction_id, $paymentTransactionId, 'İade sağlayıcının İŞLEM kimliğiyle yapılır.');
                self::assertSame(self::AMOUNT, $amount);
                self::assertSame('TRY', $currency);
                self::assertSame('Customer asked', $reason);

                return true;
            })
            ->andReturn([
                'status' => 'success',
                'payment_id' => $row->payment_id,
                'conversation_id' => $row->conversation_id,
                'amount_minor' => self::AMOUNT,
                'currency' => 'TRY',
                'error_message' => null,
            ]);

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($ws['workspaceId'], $id), ['reason' => 'Customer asked'])
            ->assertOk()
            ->assertJson(['id' => $id, 'state' => 'refunded', 'refund_reason' => 'Customer asked']);

        $after = DB::table('payment_transactions')->where('id', $id)->first();
        self::assertSame('refunded', $after->state);
        self::assertSame($admin->id, (int) $after->refunded_by_user_id);
        self::assertNotNull($after->refunded_at);

        $ledger = DB::table('ledger_entries')->where('workspace_id', $ws['workspaceId'])->orderBy('id')->get();
        self::assertCount(2, $ledger, 'Defter satırı SİLİNMEZ; karşı kayıt yazılır.');
        self::assertSame('revenue', $ledger[1]->debit_account);
        self::assertSame('cash', $ledger[1]->credit_account);
        self::assertSame(self::AMOUNT, (int) $ledger[1]->amount_minor);
        self::assertSame('payment:'.$id.':refund', $ledger[1]->reference);

        $endsAt = Carbon::parse(DB::table('subscriptions')->where('workspace_id', $ws['workspaceId'])->value('ends_at'));
        self::assertTrue($endsAt->equalTo(Carbon::parse('2026-10-06 10:00:00')), 'İade edilen 30 günlük dönem bitişten DÜŞÜLÜR.');

        $audits = DB::table('platform_audits')->where('scope', 'billing.refund')->get();
        self::assertCount(1, $audits);
        self::assertSame($admin->id, (int) $audits[0]->actor_user_id);
        self::assertStringContainsString((string) $id, (string) $audits[0]->subject);

        // İkinci iade: artık başarılı bir ödeme değil.
        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson($this->uri($ws['workspaceId'], $id), ['reason' => 'Again'])
            ->assertStatus(409);
        self::assertSame(2, DB::table('ledger_entries')->where('workspace_id', $ws['workspaceId'])->count());
    }
}
