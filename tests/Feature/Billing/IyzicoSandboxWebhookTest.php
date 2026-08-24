<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Blind RED test candidate for S1-WP01A Iyzico sandbox webhook backend.
 * At authoring time grep of routes/api.php finds zero "webhooks/iyzico-
 * sandbox" route. Every request below is expected to fail RED from the
 * framework's router (unmatched route -> 404) or the missing
 * App\Application\Billing\Port\IyzicoSandboxGatewayPort interface/binding
 * (Mockery mocks a not-yet-existing class by its FQCN string — this never
 * triggers autoloading of the not-yet-written production port), not from a
 * syntax error, a malformed fixture, or a real network call.
 *
 * Frozen port contract — shared with IyzicoSandboxCheckoutJourneyTest:
 *   App\Application\Billing\Port\IyzicoSandboxGatewayPort
 *     initializeCheckout(string $conversationId, int $workspaceId, int $actorUserId, int $amountMinor, string $currency): array
 *       -> ['signature_valid' => bool, 'token' => string, 'conversation_id' => string, 'redirect_url' => string]
 *     retrieveCheckout(string $token, string $conversationId): array
 *       -> ['signature_valid' => bool, 'status' => string, 'conversation_id' => string, 'amount_minor' => int, 'currency' => string, 'payment_id' => string]
 *   The webhook handler must always confirm a claimed SUCCESS/FAILURE event
 *   against a signed retrieveCheckout() result before mutating transaction
 *   state — the webhook's own unsigned payload fields (e.g. paidPrice,
 *   currency) are never trusted for the amount/currency check.
 *
 * Frozen contract (docs/09 §5-6, modules/iyzico-payment.md, official
 * Iyzico HPP signature spec):
 *  - Unauthenticated POST /api/webhooks/iyzico-sandbox validates the
 *    official HPP X-IYZ-SIGNATURE-V3 header: hex HMAC-SHA256 over
 *    secretKey + iyziEventType + iyziPaymentId + token +
 *    paymentConversationId + status, keyed by the configured sandbox
 *    secretKey. The official HPP event type is CHECKOUT_FORM_AUTH.
 *  - Missing/bad signature -> 400, zero mutation, and retrieveCheckout is
 *    never called (there is nothing signed yet to safely act on).
 *  - A valid, matching event transitions an initiated/processing
 *    transaction to succeeded or failed deterministically, and only after
 *    a single signature-verified retrieveCheckout() call confirms it.
 *  - Duplicate delivery (same iyziReferenceCode) returns 200 no-op and
 *    only one mutation/receipt total, and only one retrieveCheckout() call
 *    total across both deliveries.
 *  - An unknown conversation_id, or a provider-retrieved amount/currency
 *    mismatch against the pinned transaction, is rejected without success.
 *  - An unverified/malformed retrieveCheckout() response (signature_valid
 *    false) is rejected, never trusted.
 *  - No card data, secrets, raw hosted-form HTML, or unrestricted payload
 *    is ever persisted.
 *
 * Requirement IDs: IYZ-WEBHOOK-ROUTE-01, IYZ-WEBHOOK-SIGNATURE-MISSING-01,
 * IYZ-WEBHOOK-SIGNATURE-BAD-01, IYZ-WEBHOOK-SUCCESS-01,
 * IYZ-WEBHOOK-FAILURE-01, IYZ-WEBHOOK-DUPLICATE-01,
 * IYZ-WEBHOOK-UNKNOWN-CONVERSATION-01, IYZ-WEBHOOK-AMOUNT-MISMATCH-01,
 * IYZ-WEBHOOK-MALFORMED-RETRIEVE-SIGNATURE-01,
 * IYZ-WEBHOOK-NO-SECRET-PERSISTED-01, IYZ-WEBHOOK-TOKEN-MISMATCH-01.
 *
 * IYZ-WEBHOOK-TOKEN-MISMATCH-01 is an added MASTER-verified gap: the V3
 * webhook must compare its own "token" field against the token stored on
 * the pinned transaction *before* ever calling retrieveCheckout() — a
 * validly-signed event carrying a token that does not match the
 * transaction's own stored token (e.g. a stale/replayed event for a
 * conversation that has since been re-initiated with a new token) must be
 * rejected without retrieving. Production
 * App\Application\Billing\UseCase\ManageIyzicoSandboxCheckout::receiveWebhook()
 * currently only compares iyziReferenceCode for duplicate-delivery
 * detection and otherwise calls retrieveCheckout() using whatever token the
 * payload itself carries, without ever checking it against the stored
 * transaction token.
 */
final class IyzicoSandboxWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URI = '/api/webhooks/iyzico-sandbox';

    private const SANDBOX_SECRET = 'test-sandbox-secret-key';

    private const GATEWAY_PORT = 'App\Application\Billing\Port\IyzicoSandboxGatewayPort';

    private const INIT_TOKEN = 'sandbox-token-webhook-init-01';

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

    private function insertPlanRow(array $overrides = []): int
    {
        if (! Schema::hasTable('plans')) {
            self::fail('IYZ-WEBHOOK: production "plans" table does not exist yet — RED must come from the missing production table, not a malformed fixture.');
        }

        return (int) DB::table('plans')->insertGetId(array_merge([
            'name' => 'Growth',
            'code' => 'growth-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => json_encode(['feature.a']),
            'amount_minor' => 149900,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function activeSubscription(int $workspaceId, int $planId): void
    {
        if (! Schema::hasTable('subscriptions')) {
            self::fail('IYZ-WEBHOOK: production "subscriptions" table does not exist yet — RED must come from the missing production table, not a malformed fixture.');
        }

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

    /**
     * Binds a Mockery fake by the port's FQCN string into the container.
     * Mockery::mock() can create a mock for a class/interface that does not
     * exist yet, and container instance() binding is keyed by string, so
     * this never triggers autoloading of the not-yet-written production
     * port. The same bound instance is returned so a test can layer further
     * shouldReceive() expectations (e.g. retrieveCheckout) onto the same
     * fake that initiateSandboxCheckout() already bound.
     */
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

    /**
     * Opens an initiated Iyzico sandbox checkout session for the workspace
     * via the sibling backend endpoint frozen in
     * IyzicoSandboxCheckoutJourneyTest, so the webhook has a real pending
     * conversation_id to transition. Uses the same deterministic Mockery
     * fake as the checkout journey test — never a real adapter reached with
     * dummy credentials — so this stays a pure, network-free setup step.
     */
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
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/webhook-setup-01',
            ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/iyzico-sandbox/session", [], ['Idempotency-Key' => $this->uuid()]);

        if ($response->getStatusCode() !== 202) {
            self::fail('IYZ-WEBHOOK: sandbox checkout session açılamadı (production POST endpoint henüz yok veya farklı davranıyor) — webhook geçiş senaryosu gerçek bir conversation_id gerektirir.');
        }

        return $response->json();
    }

    private function signature(string $eventType, string $paymentId, string $token, string $conversationId, string $status): string
    {
        return hash_hmac(
            'sha256',
            self::SANDBOX_SECRET.$eventType.$paymentId.$token.$conversationId.$status,
            self::SANDBOX_SECRET
        );
    }

    private function webhookPayload(array $overrides = []): array
    {
        return array_merge([
            'iyziEventType' => 'CHECKOUT_FORM_AUTH',
            'iyziPaymentId' => '1234567',
            'token' => self::INIT_TOKEN,
            'paymentConversationId' => 'unknown-conversation',
            'status' => 'SUCCESS',
            'iyziReferenceCode' => 'ref-'.bin2hex(random_bytes(6)),
        ], $overrides);
    }

    private function signedHeaders(array $payload): array
    {
        return array_merge($this->jsonHeaders(), [
            'X-IYZ-SIGNATURE-V3' => $this->signature(
                $payload['iyziEventType'],
                $payload['iyziPaymentId'],
                $payload['token'],
                $payload['paymentConversationId'],
                $payload['status']
            ),
        ]);
    }

    /**
     * Expects a signature-verified retrieveCheckout() call for the given
     * token/conversation_id, returning a frozen-shape provider result.
     */
    private function expectRetrieveCheckout(
        string $token,
        string $conversationId,
        string $status,
        int $amountMinor = 149900,
        string $currency = 'TRY',
        bool $signatureValid = true,
        int $times = 1
    ): void {
        $this->gatewayFake()->shouldReceive('retrieveCheckout')
            ->times($times)
            ->with($token, $conversationId)
            ->andReturn([
                'signature_valid' => $signatureValid,
                'status' => $status,
                'conversation_id' => $conversationId,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'payment_id' => '1234567',
            ]);
    }

    // --- IYZ-WEBHOOK-ROUTE-01 ------------------------------------------------

    public function test_webhook_route_exists_and_is_reachable_without_authentication(): void
    {
        $response = $this->withHeaders($this->jsonHeaders())
            ->postJson(self::WEBHOOK_URI, $this->webhookPayload());

        self::assertNotSame(
            404,
            $response->getStatusCode(),
            'IYZ-WEBHOOK-ROUTE-01: /api/webhooks/iyzico-sandbox rotası kimliksiz olarak erişilebilir olmalı (auth middleware\'siz kayıtlı).'
        );
    }

    // --- IYZ-WEBHOOK-SIGNATURE-MISSING-01 -------------------------------------

    public function test_missing_signature_header_is_rejected_with_400_and_mutates_nothing(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-wh-missing-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-wh-missing-01');
        $planId = $this->insertPlanRow(['code' => 'growth-wh-missing-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);
        $this->gatewayFake()->shouldNotReceive('retrieveCheckout');

        $response = $this->withHeaders($this->jsonHeaders())
            ->postJson(self::WEBHOOK_URI, $this->webhookPayload(['paymentConversationId' => $session['conversation_id']]));

        $response->assertStatus(400, 'IYZ-WEBHOOK-SIGNATURE-MISSING-01: X-IYZ-SIGNATURE-V3 eksikken 400 dönmeli.');
    }

    // --- IYZ-WEBHOOK-SIGNATURE-BAD-01 -----------------------------------------

    public function test_bad_signature_is_rejected_with_400_and_mutates_nothing(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-wh-bad-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-wh-bad-01');
        $planId = $this->insertPlanRow(['code' => 'growth-wh-bad-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);
        $this->gatewayFake()->shouldNotReceive('retrieveCheckout');

        $payload = $this->webhookPayload(['paymentConversationId' => $session['conversation_id']]);

        $response = $this->withHeaders(array_merge($this->jsonHeaders(), [
            'X-IYZ-SIGNATURE-V3' => 'deadbeef00000000000000000000000000000000000000000000000000000000',
        ]))->postJson(self::WEBHOOK_URI, $payload);

        $response->assertStatus(400, 'IYZ-WEBHOOK-SIGNATURE-BAD-01: geçersiz imza 400 ile reddedilmeli.');

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/iyzico-sandbox/session");
        self::assertSame('initiated', $followUp->json()['state'] ?? null, 'IYZ-WEBHOOK-SIGNATURE-BAD-01: geçersiz imzalı webhook işlem durumunu değiştirmemeli.');
    }

    // --- IYZ-WEBHOOK-SUCCESS-01 -----------------------------------------------

    public function test_valid_signed_success_event_transitions_initiated_transaction_to_succeeded(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-wh-success-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-wh-success-01');
        $planId = $this->insertPlanRow(['code' => 'growth-wh-success-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);

        $payload = $this->webhookPayload([
            'paymentConversationId' => $session['conversation_id'],
            'status' => 'SUCCESS',
        ]);
        $this->expectRetrieveCheckout($payload['token'], $session['conversation_id'], 'SUCCESS');

        $response = $this->withHeaders($this->signedHeaders($payload))
            ->postJson(self::WEBHOOK_URI, $payload);

        $response->assertStatus(200, 'IYZ-WEBHOOK-SUCCESS-01: geçerli imzalı SUCCESS eventi 200 dönmeli.');

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/iyzico-sandbox/session");
        self::assertSame('succeeded', $followUp->json()['state'] ?? null, 'IYZ-WEBHOOK-SUCCESS-01: initiated işlem succeeded durumuna geçmeli.');
    }

    // --- IYZ-WEBHOOK-FAILURE-01 -----------------------------------------------

    public function test_valid_signed_failure_event_transitions_initiated_transaction_to_failed(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-wh-failure-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-wh-failure-01');
        $planId = $this->insertPlanRow(['code' => 'growth-wh-failure-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);

        $payload = $this->webhookPayload([
            'paymentConversationId' => $session['conversation_id'],
            'status' => 'FAILURE',
        ]);
        $this->expectRetrieveCheckout($payload['token'], $session['conversation_id'], 'FAILURE');

        $response = $this->withHeaders($this->signedHeaders($payload))
            ->postJson(self::WEBHOOK_URI, $payload);

        $response->assertStatus(200, 'IYZ-WEBHOOK-FAILURE-01: geçerli imzalı FAILURE eventi 200 dönmeli.');

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/iyzico-sandbox/session");
        self::assertSame('failed', $followUp->json()['state'] ?? null, 'IYZ-WEBHOOK-FAILURE-01: initiated işlem failed durumuna geçmeli.');
    }

    // --- IYZ-WEBHOOK-DUPLICATE-01 ---------------------------------------------

    public function test_duplicate_delivery_of_same_reference_code_is_200_no_op_with_only_one_mutation(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-wh-dup-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-wh-dup-01');
        $planId = $this->insertPlanRow(['code' => 'growth-wh-dup-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);

        $payload = $this->webhookPayload([
            'paymentConversationId' => $session['conversation_id'],
            'status' => 'SUCCESS',
        ]);
        // Duplicate delivery must retrieve/mutate only once total across both deliveries.
        $this->expectRetrieveCheckout($payload['token'], $session['conversation_id'], 'SUCCESS', times: 1);
        $headers = $this->signedHeaders($payload);

        $first = $this->withHeaders($headers)->postJson(self::WEBHOOK_URI, $payload);
        $first->assertStatus(200);

        $duplicate = $this->withHeaders($headers)->postJson(self::WEBHOOK_URI, $payload);
        $duplicate->assertStatus(200, 'IYZ-WEBHOOK-DUPLICATE-01: aynı iyziReferenceCode ile yinelenen teslimat 200 no-op dönmeli.');

        if (Schema::hasTable('iyzico_sandbox_transactions')) {
            $receiptCount = DB::table('iyzico_sandbox_transactions')
                ->where('reference_code', $payload['iyziReferenceCode'])
                ->count();
            self::assertLessThanOrEqual(1, $receiptCount, 'IYZ-WEBHOOK-DUPLICATE-01: aynı reference_code için en fazla bir alındı/satır olmalı.');
        }
        // Mockery::close() (tearDown) enforces the single retrieveCheckout() call
        // above: a second call on duplicate delivery would fail this test.
    }

    // --- IYZ-WEBHOOK-UNKNOWN-CONVERSATION-01 ----------------------------------

    public function test_unknown_conversation_id_is_rejected_without_success(): void
    {
        $this->bindGatewayFake()->shouldNotReceive('retrieveCheckout');

        $payload = $this->webhookPayload(['paymentConversationId' => 'nonexistent-conversation-'.bin2hex(random_bytes(4))]);

        $response = $this->withHeaders($this->signedHeaders($payload))
            ->postJson(self::WEBHOOK_URI, $payload);

        self::assertNotSame(200, $response->getStatusCode(), 'IYZ-WEBHOOK-UNKNOWN-CONVERSATION-01: bilinmeyen conversation_id başarı olarak kabul edilmemeli.');
    }

    // --- IYZ-WEBHOOK-AMOUNT-MISMATCH-01 ----------------------------------------

    public function test_provider_amount_currency_mismatch_against_pinned_transaction_is_rejected_without_success(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-wh-mismatch-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-wh-mismatch-01');
        $planId = $this->insertPlanRow(['code' => 'growth-wh-mismatch-01', 'amount_minor' => 149900, 'currency' => 'TRY']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);

        // Unsigned webhook payload fields must NOT be the mismatch source —
        // the payload here otherwise matches; only the signed retrieveCheckout()
        // result reports a mismatched amount/currency against the pinned plan.
        $payload = $this->webhookPayload([
            'paymentConversationId' => $session['conversation_id'],
            'status' => 'SUCCESS',
        ]);
        $this->expectRetrieveCheckout($payload['token'], $session['conversation_id'], 'SUCCESS', amountMinor: 100, currency: 'USD');

        $response = $this->withHeaders($this->signedHeaders($payload))
            ->postJson(self::WEBHOOK_URI, $payload);

        self::assertNotSame(
            200,
            $response->getStatusCode(),
            'IYZ-WEBHOOK-AMOUNT-MISMATCH-01: retrieveCheckout() sonucundaki tutar/para birimi sabitlenmiş işlemle uyuşmuyorsa başarı olarak kabul edilmemeli.'
        );

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/iyzico-sandbox/session");
        self::assertNotSame('succeeded', $followUp->json()['state'] ?? null, 'IYZ-WEBHOOK-AMOUNT-MISMATCH-01: uyuşmayan tutar/para birimi işlemi succeeded durumuna geçirmemeli.');
    }

    // --- IYZ-WEBHOOK-MALFORMED-RETRIEVE-SIGNATURE-01 ---------------------------

    public function test_malformed_or_unverified_retrieve_checkout_result_is_rejected_without_success(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-wh-badretrieve-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-wh-badretrieve-01');
        $planId = $this->insertPlanRow(['code' => 'growth-wh-badretrieve-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);

        $payload = $this->webhookPayload([
            'paymentConversationId' => $session['conversation_id'],
            'status' => 'SUCCESS',
        ]);
        $this->expectRetrieveCheckout($payload['token'], $session['conversation_id'], 'SUCCESS', signatureValid: false);

        $response = $this->withHeaders($this->signedHeaders($payload))
            ->postJson(self::WEBHOOK_URI, $payload);

        self::assertNotSame(
            200,
            $response->getStatusCode(),
            'IYZ-WEBHOOK-MALFORMED-RETRIEVE-SIGNATURE-01: signature_valid=false/malformed retrieveCheckout() sonucu asla güvenilmemeli.'
        );

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/iyzico-sandbox/session");
        self::assertNotSame('succeeded', $followUp->json()['state'] ?? null, 'IYZ-WEBHOOK-MALFORMED-RETRIEVE-SIGNATURE-01: güvenilmeyen provider sonucu işlemi succeeded durumuna geçirmemeli.');
    }

    // --- IYZ-WEBHOOK-NO-SECRET-PERSISTED-01 -------------------------------------

    public function test_webhook_never_persists_card_data_secret_key_or_raw_hosted_form_html(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-wh-nosecret-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-wh-nosecret-01');
        $planId = $this->insertPlanRow(['code' => 'growth-wh-nosecret-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);

        $payload = $this->webhookPayload([
            'paymentConversationId' => $session['conversation_id'],
            'status' => 'SUCCESS',
        ]);
        $this->expectRetrieveCheckout($payload['token'], $session['conversation_id'], 'SUCCESS');

        $this->withHeaders($this->signedHeaders($payload))
            ->postJson(self::WEBHOOK_URI, $payload);

        if (! Schema::hasTable('iyzico_sandbox_transactions')) {
            self::fail('IYZ-WEBHOOK-NO-SECRET-PERSISTED-01: production "iyzico_sandbox_transactions" table does not exist yet — RED must come from the missing table.');
        }

        $columns = Schema::getColumnListing('iyzico_sandbox_transactions');
        foreach ($columns as $column) {
            self::assertStringNotContainsStringIgnoringCase('card', $column, 'IYZ-WEBHOOK-NO-SECRET-PERSISTED-01: kart verisi asla saklanmamalı.');
            self::assertStringNotContainsStringIgnoringCase('cvv', $column);
            self::assertStringNotContainsStringIgnoringCase('secret', $column, 'IYZ-WEBHOOK-NO-SECRET-PERSISTED-01: sağlayıcı secret\'ı asla saklanmamalı.');
            self::assertStringNotContainsStringIgnoringCase('html', $column, 'IYZ-WEBHOOK-NO-SECRET-PERSISTED-01: ham hosted-form HTML\'i asla saklanmamalı.');
        }

        $row = DB::table('iyzico_sandbox_transactions')
            ->where('conversation_id', $session['conversation_id'])
            ->first();
        self::assertNotNull($row, 'IYZ-WEBHOOK-NO-SECRET-PERSISTED-01: başarılı webhook işlemi bir satır bırakmalı.');
    }

    // --- IYZ-WEBHOOK-TOKEN-MISMATCH-01 -----------------------------------------

    public function test_webhook_token_not_matching_the_stored_transaction_token_is_rejected_without_retrieve_call(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-wh-tokenmismatch-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-wh-tokenmismatch-01');
        $planId = $this->insertPlanRow(['code' => 'growth-wh-tokenmismatch-01']);
        $this->activeSubscription($workspaceId, $planId);
        // initiateSandboxCheckout() pins self::INIT_TOKEN as the transaction's
        // stored token for this conversation_id.
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);
        $this->gatewayFake()->shouldNotReceive('retrieveCheckout');

        // A validly-signed event for the SAME conversation_id but carrying a
        // DIFFERENT token than the one stored on the transaction — the
        // signature is genuine (computed over this exact payload), but the
        // token itself was never the one this transaction was initiated
        // with.
        $payload = $this->webhookPayload([
            'paymentConversationId' => $session['conversation_id'],
            'token' => 'sandbox-token-not-the-stored-one-01',
            'status' => 'SUCCESS',
        ]);

        $response = $this->withHeaders($this->signedHeaders($payload))
            ->postJson(self::WEBHOOK_URI, $payload);

        self::assertNotSame(
            200,
            $response->getStatusCode(),
            'IYZ-WEBHOOK-TOKEN-MISMATCH-01: webhook, retrieveCheckout() çağırmadan ÖNCE kendi token alanını sabitlenmiş işlemin token\'ıyla karşılaştırmalı — eşleşmeyen bir token 200/başarı olarak kabul edilmemeli.'
        );

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/iyzico-sandbox/session");
        self::assertNotSame('succeeded', $followUp->json()['state'] ?? null, 'IYZ-WEBHOOK-TOKEN-MISMATCH-01: token uyuşmayan event işlemi succeeded durumuna geçirmemeli.');
        // Mockery::close() (tearDown) enforces shouldNotReceive('retrieveCheckout')
        // above: retrieve must never be attempted before the stored-token check.
    }
}
