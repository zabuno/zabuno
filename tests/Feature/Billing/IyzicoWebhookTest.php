<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * IYZ-WEBHOOK — üretim webhook ucu `/api/webhooks/iyzico`.
 *
 * Sandbox ucuyla AYNI imza doğrulaması (HMAC-SHA256, gizli anahtar +
 * iyziEventType + iyziPaymentId + token + paymentConversationId + status)
 * ve aynı tekrar koruması. Fark tek: gizli anahtar işlemin KİPİNE göre
 * seçilir — sandbox işlemi için `.env` sandbox anahtarı, canlı işlem için
 * KASADAKİ üretim anahtarı. Canlı bir işlemi sandbox anahtarıyla imzalamak
 * geçersizdir.
 */
final class IyzicoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/webhooks/iyzico';

    private const SANDBOX_GATEWAY = 'App\Application\Billing\Port\SandboxPaymentGatewayPort';

    private const LIVE_GATEWAY = 'App\Application\Billing\Port\LivePaymentGatewayPort';

    private const SANDBOX_SECRET = 'webhook-sandbox-secret-01';

    private const LIVE_SECRET = 'webhook-live-secret-from-vault-01';

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    private function workspace(): int
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kadıköy Kebap',
            'slug' => 'webhook-'.bin2hex(random_bytes(3)),
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

    private function planId(): int
    {
        return (int) DB::table('plans')->insertGetId([
            'name' => 'Pro',
            'code' => 'pro-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => json_encode(['menu.publish']),
            'amount_minor' => 149900,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** İşlem satırı DOĞRUDAN yazılır: bu test ödeme başlatmayı değil webhook'u dener. */
    private function initiatedTransaction(int $workspaceId, string $mode, string $token, string $conversationId): int
    {
        $userId = (int) DB::table('workspace_memberships')->where('workspace_id', $workspaceId)->value('user_id');

        return (int) DB::table('payment_transactions')->insertGetId([
            'workspace_id' => $workspaceId,
            'actor_user_id' => $userId,
            'plan_id' => $this->planId(),
            'mode' => $mode,
            'idempotency_key' => $conversationId,
            'conversation_id' => $conversationId,
            'token' => $token,
            'redirect_url' => 'https://cf.iyzipay.com/'.$token,
            'amount_minor' => 149900,
            'currency' => 'TRY',
            'period_days' => 30,
            'state' => 'initiated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configure(): void
    {
        config()->set('services.iyzico.mode', 'sandbox');
        config()->set('services.iyzico.sandbox.api_key', 'sandbox-api-key');
        config()->set('services.iyzico.sandbox.secret_key', self::SANDBOX_SECRET);
        config()->set('services.iyzico.sandbox.base_url', 'https://sandbox-api.iyzipay.com');
        config()->set('billing.subscription.period_days', 30);
    }

    private function vaultWithLiveKeys(): void
    {
        $this->app->make(PlatformCredentialAdminPort::class)->put(CredentialProvider::Iyzico, [
            'api_key' => 'live-api-key-01',
            'secret_key' => self::LIVE_SECRET,
        ], byUserId: null);
    }

    private function bindFake(string $port): MockInterface
    {
        $fake = Mockery::mock($port);
        app()->instance($port, $fake);

        return $fake;
    }

    private function payload(string $conversationId, string $token, string $status = 'SUCCESS', string $reference = 'ref-01'): array
    {
        return [
            'iyziEventType' => 'CHECKOUT_FORM_AUTH',
            'iyziPaymentId' => 'pay-01',
            'token' => $token,
            'paymentConversationId' => $conversationId,
            'status' => $status,
            'iyziReferenceCode' => $reference,
        ];
    }

    private function sign(array $payload, string $secret): array
    {
        return ['X-IYZ-SIGNATURE-V3' => hash_hmac(
            'sha256',
            $secret.$payload['iyziEventType'].$payload['iyziPaymentId'].$payload['token'].$payload['paymentConversationId'].$payload['status'],
            $secret,
        )];
    }

    private function retrieved(string $conversationId, string $status = 'SUCCESS'): array
    {
        return [
            'signature_valid' => true,
            'status' => $status,
            'conversation_id' => $conversationId,
            'amount_minor' => 149900,
            'currency' => 'TRY',
            'payment_id' => 'pay-01',
            'payment_transaction_id' => 'pay-01-tx',
            'error_message' => null,
        ];
    }

    #[Test]
    public function the_endpoint_is_public_and_rejects_missing_fields_and_bad_signatures_without_touching_anything(): void
    {
        $this->configure();
        $workspaceId = $this->workspace();
        $conversationId = '11111111-1111-4111-8111-111111111111';
        $this->initiatedTransaction($workspaceId, 'sandbox', 'tok-bad', $conversationId);
        $this->bindFake(self::SANDBOX_GATEWAY)->shouldNotReceive('retrieveCheckout');

        $this->postJson(self::URI, ['iyziEventType' => 'CHECKOUT_FORM_AUTH'])->assertStatus(400);

        $payload = $this->payload($conversationId, 'tok-bad');
        $this->postJson(self::URI, $payload)->assertStatus(400);
        $this->postJson(self::URI, $payload, ['X-IYZ-SIGNATURE-V3' => 'deadbeef'])->assertStatus(400);
        $this->postJson(self::URI, $payload, $this->sign($payload, 'wrong-secret'))->assertStatus(400);

        self::assertSame('initiated', DB::table('payment_transactions')->value('state'));
        self::assertSame(0, DB::table('ledger_entries')->count());
    }

    #[Test]
    public function an_unknown_conversation_is_404_and_a_token_mismatch_is_422(): void
    {
        $this->configure();
        $workspaceId = $this->workspace();
        $conversationId = '22222222-2222-4222-8222-222222222222';
        $this->initiatedTransaction($workspaceId, 'sandbox', 'tok-real', $conversationId);
        $this->bindFake(self::SANDBOX_GATEWAY)->shouldNotReceive('retrieveCheckout');

        $unknown = $this->payload('33333333-3333-4333-8333-333333333333', 'tok-x');
        $this->postJson(self::URI, $unknown, $this->sign($unknown, self::SANDBOX_SECRET))->assertStatus(404);

        $mismatch = $this->payload($conversationId, 'tok-forged');
        $this->postJson(self::URI, $mismatch, $this->sign($mismatch, self::SANDBOX_SECRET))->assertStatus(422);
    }

    #[Test]
    public function a_sandbox_transaction_settles_once_and_the_same_reference_replayed_is_a_no_op(): void
    {
        $this->configure();
        $workspaceId = $this->workspace();
        $conversationId = '44444444-4444-4444-8444-444444444444';
        $id = $this->initiatedTransaction($workspaceId, 'sandbox', 'tok-ok', $conversationId);
        $this->bindFake(self::SANDBOX_GATEWAY)->shouldReceive('retrieveCheckout')->once()
            ->with('tok-ok', $conversationId)->andReturn($this->retrieved($conversationId));

        $payload = $this->payload($conversationId, 'tok-ok');
        $headers = $this->sign($payload, self::SANDBOX_SECRET);

        $this->postJson(self::URI, $payload, $headers)->assertOk();
        $this->postJson(self::URI, $payload, $headers)->assertOk();

        self::assertSame('succeeded', DB::table('payment_transactions')->where('id', $id)->value('state'));
        self::assertSame(1, DB::table('ledger_entries')->where('workspace_id', $workspaceId)->count());
        self::assertSame(1, DB::table('subscriptions')->where('workspace_id', $workspaceId)->count());
    }

    #[Test]
    public function a_live_transaction_is_verified_with_the_vault_secret_and_settled_through_the_live_gateway(): void
    {
        $this->configure();
        $this->vaultWithLiveKeys();
        $workspaceId = $this->workspace();
        $conversationId = '55555555-5555-4555-8555-555555555555';
        $id = $this->initiatedTransaction($workspaceId, 'live', 'tok-live', $conversationId);
        $this->bindFake(self::SANDBOX_GATEWAY)->shouldNotReceive('retrieveCheckout');
        $this->bindFake(self::LIVE_GATEWAY)->shouldReceive('retrieveCheckout')->once()
            ->with('tok-live', $conversationId)->andReturn($this->retrieved($conversationId));

        $payload = $this->payload($conversationId, 'tok-live');

        // Sandbox anahtarıyla imzalanmış "canlı" bildirim geçersizdir.
        $this->postJson(self::URI, $payload, $this->sign($payload, self::SANDBOX_SECRET))->assertStatus(400);
        self::assertSame('initiated', DB::table('payment_transactions')->where('id', $id)->value('state'));

        $this->postJson(self::URI, $payload, $this->sign($payload, self::LIVE_SECRET))->assertOk();
        self::assertSame('succeeded', DB::table('payment_transactions')->where('id', $id)->value('state'));
        self::assertSame(1, DB::table('ledger_entries')->where('workspace_id', $workspaceId)->count());
    }
}
