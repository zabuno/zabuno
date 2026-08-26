<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * CORE-12 — tahsilattan deftere, defterden ekrana.
 *
 * Bu test bileşimi kanıtlar: restoran sahibi ödemeyi yapar, sağlayıcı
 * başarıyı bildirir, defter kendiliğinden çift kayıt yazar ve sahip bunu
 * kendi workspace'inin defter uçnoktasında görür. Aradaki hiçbir adım
 * taklit edilmez — yalnız dış ödeme sağlayıcısı sahtelenir.
 *
 * Requirement ID'leri: LEDGER-BILLING-COMPOSED-05, LEDGER-IDEMPOTENT-06,
 * LEDGER-READ-AUTHZ-07, LEDGER-FAILED-PAYMENT-08.
 */
final class BillingLedgerJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URI = '/api/webhooks/iyzico-sandbox';

    private const SANDBOX_SECRET = 'test-sandbox-secret-key';

    private const GATEWAY_PORT = 'App\Application\Billing\Port\IyzicoSandboxGatewayPort';

    private const INIT_TOKEN = 'sandbox-token-ledger-01';

    private const PLAN_AMOUNT_MINOR = 149900;

    protected function tearDown(): void
    {
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

    private function verifiedUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
        ]);
    }

    private function workspaceOwnedBy(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
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

    private function planWithSubscription(int $workspaceId, string $code): void
    {
        $planId = (int) DB::table('plans')->insertGetId([
            'name' => 'Growth',
            'code' => $code,
            'version' => 1,
            'entitlements' => json_encode(['feature.a']),
            'amount_minor' => self::PLAN_AMOUNT_MINOR,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_id' => $planId,
            'state' => 'active',
            'ends_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function bindGatewayFake(): MockInterface
    {
        $fake = Mockery::mock(self::GATEWAY_PORT);
        app()->instance(self::GATEWAY_PORT, $fake);

        return $fake;
    }

    private function gatewayFake(): MockInterface
    {
        return app(self::GATEWAY_PORT);
    }

    private function initiateSandboxCheckout(User $owner, int $workspaceId): array
    {
        config()->set('services.iyzico.sandbox.api_key', 'sandbox-api-key');
        config()->set('services.iyzico.sandbox.secret_key', self::SANDBOX_SECRET);
        config()->set('services.iyzico.sandbox.base_url', 'https://sandbox-api.iyzipay.com');

        $fake = $this->bindGatewayFake();
        $fake->shouldReceive('initializeCheckout')
            ->once()
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => true,
                'token' => self::INIT_TOKEN,
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/ledger-01',
            ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/iyzico-sandbox/session", [], ['Idempotency-Key' => $this->uuid()]);

        $response->assertStatus(202);

        return $response->json();
    }

    private function signedHeaders(array $payload): array
    {
        return array_merge($this->jsonHeaders(), [
            'X-IYZ-SIGNATURE-V3' => hash_hmac(
                'sha256',
                self::SANDBOX_SECRET.$payload['iyziEventType'].$payload['iyziPaymentId']
                    .$payload['token'].$payload['paymentConversationId'].$payload['status'],
                self::SANDBOX_SECRET,
            ),
        ]);
    }

    private function webhookPayload(string $conversationId, string $status, array $overrides = []): array
    {
        return array_merge([
            'iyziEventType' => 'CHECKOUT_FORM_AUTH',
            'iyziPaymentId' => '1234567',
            'token' => self::INIT_TOKEN,
            'paymentConversationId' => $conversationId,
            'status' => $status,
            'iyziReferenceCode' => 'ref-'.bin2hex(random_bytes(6)),
        ], $overrides);
    }

    private function expectRetrieveCheckout(string $conversationId, string $status, int $times = 1): void
    {
        $this->gatewayFake()->shouldReceive('retrieveCheckout')
            ->times($times)
            ->with(self::INIT_TOKEN, $conversationId)
            ->andReturn([
                'signature_valid' => true,
                'status' => $status,
                'conversation_id' => $conversationId,
                'amount_minor' => self::PLAN_AMOUNT_MINOR,
                'currency' => 'TRY',
                'payment_id' => '1234567',
            ]);
    }

    // --- LEDGER-BILLING-COMPOSED-05 ---------------------------------------

    public function test_a_successful_payment_writes_a_double_entry_the_owner_can_see(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-ledger-05@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-ledger-05');
        $this->planWithSubscription($workspaceId, 'growth-ledger-05');
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);
        $this->expectRetrieveCheckout($session['conversation_id'], 'SUCCESS');

        $payload = $this->webhookPayload($session['conversation_id'], 'SUCCESS');
        $this->withHeaders($this->signedHeaders($payload))
            ->postJson(self::WEBHOOK_URI, $payload)
            ->assertStatus(200);

        $ledger = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/ledger")
            ->assertStatus(200);

        $ledger->assertJsonCount(1, 'entries');
        $ledger->assertJsonPath('entries.0.debitAccount', 'cash');
        $ledger->assertJsonPath('entries.0.creditAccount', 'revenue');
        $ledger->assertJsonPath('entries.0.amountMinor', self::PLAN_AMOUNT_MINOR);
        $ledger->assertJsonPath('entries.0.currencyCode', 'TRY');
        $ledger->assertJsonPath('balances.cash', self::PLAN_AMOUNT_MINOR);
        $ledger->assertJsonPath('balances.revenue', -self::PLAN_AMOUNT_MINOR);
    }

    // --- LEDGER-FAILED-PAYMENT-08 -----------------------------------------

    public function test_a_failed_payment_writes_nothing_to_the_ledger(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-ledger-08@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-ledger-08');
        $this->planWithSubscription($workspaceId, 'growth-ledger-08');
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);
        $this->expectRetrieveCheckout($session['conversation_id'], 'FAILURE');

        $payload = $this->webhookPayload($session['conversation_id'], 'FAILURE');
        $this->withHeaders($this->signedHeaders($payload))
            ->postJson(self::WEBHOOK_URI, $payload)
            ->assertStatus(200);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/ledger")
            ->assertStatus(200)
            ->assertJsonCount(0, 'entries');
    }

    // --- LEDGER-IDEMPOTENT-06 ---------------------------------------------

    public function test_the_same_payment_reported_twice_is_recorded_once(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-ledger-06@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-ledger-06');
        $this->planWithSubscription($workspaceId, 'growth-ledger-06');
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);
        $this->expectRetrieveCheckout($session['conversation_id'], 'SUCCESS', times: 2);

        // Sağlayıcı aynı başarıyı iki farklı referansla iki kez bildirir;
        // ikinci bildirim geliri ikinci kez yazmamalı.
        foreach ([1, 2] as $delivery) {
            $payload = $this->webhookPayload($session['conversation_id'], 'SUCCESS');
            $this->withHeaders($this->signedHeaders($payload))
                ->postJson(self::WEBHOOK_URI, $payload);
        }

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/ledger")
            ->assertStatus(200)
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('balances.cash', self::PLAN_AMOUNT_MINOR);
    }

    // --- LEDGER-READ-AUTHZ-07 ---------------------------------------------

    public function test_an_outsider_cannot_read_another_workspace_ledger(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-ledger-07@example.test');
        $outsider = $this->verifiedUser('Mert Demir', 'mert-ledger-07@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-ledger-07');

        $this->actingAs($outsider)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/ledger")
            ->assertStatus(404, 'LEDGER-READ-AUTHZ-07: yabancı için defter var olduğunu bile belli etmemeli.');
    }

    public function test_the_ledger_endpoint_requires_authentication(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-ledger-07b@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'zeytin-ledger-07b');

        $this->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/ledger")
            ->assertStatus(401);
    }
}
