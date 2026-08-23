<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Dto\IyzicoSandboxSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Blind RED test candidate for S1-WP01A Iyzico sandbox checkout backend.
 * At authoring time grep of routes/api.php finds zero "iyzico-sandbox"
 * route, grep of app/Domain/Authorization/Permission.php finds no
 * BillingManage case, and grep of config/ + .env.example finds no Iyzico
 * sandbox key/secret/base URL config. Every request below is expected to
 * fail RED from the framework's router (unmatched route -> 404), a missing
 * Permission::BillingManage case, or the missing
 * App\Application\Billing\Port\IyzicoSandboxGatewayPort interface/binding
 * (Mockery mocks a not-yet-existing class by its FQCN string, so binding it
 * into the container does not require the class/interface to exist yet —
 * ::class on an undeclared class is a compile-time string constant in PHP
 * and does not trigger autoloading). RED must never come from a real
 * network call, a syntax error, a missing fixture, or contradictory config.
 *
 * Frozen port contract (production writer implements this exactly):
 *   App\Application\Billing\Port\IyzicoSandboxGatewayPort
 *     initializeCheckout(string $conversationId, int $workspaceId, int $actorUserId, int $amountMinor, string $currency): array
 *       -> ['signature_valid' => bool, 'token' => string, 'conversation_id' => string, 'redirect_url' => string]
 *     retrieveCheckout(string $token, string $conversationId): array
 *       -> ['signature_valid' => bool, 'status' => string, 'conversation_id' => string, 'amount_minor' => int, 'currency' => string, 'payment_id' => string]
 *   A malformed/untrusted initializeCheckout result (signature_valid=false)
 *   is frozen to HTTP 502 (Bad Gateway) — distinct from 503, which is
 *   reserved for the honest "sandbox config missing" case where the
 *   provider was never called at all.
 *
 * Frozen contract (docs/09 §5-6, modules/iyzico-payment.md, official
 * iyzipay-php 2.0.61 Checkout Form hosted page, no card capture/storage):
 *  - Authenticated+verified workspace Owner with Permission::BillingManage
 *    can GET/POST /api/workspaces/{workspace}/iyzico-sandbox/session;
 *    Member/Editor/foreign/nonexistent workspace all receive an identical
 *    enumeration-safe 404.
 *  - GET with no transaction row returns exactly {state: 'none'}.
 *  - POST accepts exactly an empty JSON object body plus a required UUID
 *    Idempotency-Key header. No client plan/amount/currency/card fields.
 *  - POST requires an existing active workspace subscription pinned to an
 *    active plan; otherwise 409.
 *  - Missing/invalid Idempotency-Key or payload fields -> 400/422, with
 *    zero provider/persistence mutation.
 *  - Missing sandbox API key/secret/base URL config -> honest 503, and the
 *    provider port is never invoked.
 *  - A successful POST uses the server-resolved plan amount_minor/currency
 *    (never client-supplied), a mandatory server-generated conversation
 *    UUID, a hosted HTTPS redirect_url, state 'initiated', and returns only
 *    state/conversation_id/amount_minor/currency/redirect_url. The exact
 *    amount_minor/currency/conversation_id reaching the gateway port are
 *    asserted against the fake's received arguments.
 *  - A provider initializeCheckout result with signature_valid=false (or
 *    otherwise malformed) is rejected 502 and no transaction becomes
 *    authoritative.
 *  - Same workspace + same Idempotency-Key + same request replays the
 *    authoritative result via exactly one provider initializeCheckout call.
 *  - The same key reused for a conflicting request is 409 without a second
 *    provider initializeCheckout call.
 *
 * Requirement IDs: IYZ-SESSION-AUTHZ-01, IYZ-SESSION-ESCAPE-01,
 * IYZ-SESSION-GET-NONE-01, IYZ-SESSION-POST-PAYLOAD-01,
 * IYZ-SESSION-POST-NO-SUBSCRIPTION-01, IYZ-SESSION-POST-VALIDATION-01,
 * IYZ-SESSION-POST-CONFIG-MISSING-01, IYZ-SESSION-POST-SUCCESS-01,
 * IYZ-SESSION-POST-MALFORMED-SIGNATURE-01, IYZ-SESSION-POST-REPLAY-01,
 * IYZ-SESSION-POST-CONFLICT-01, IYZ-SESSION-AUTH-01,
 * IYZ-SESSION-ENDS-TODAY-01, IYZ-SESSION-CONCURRENT-CLAIM-01.
 *
 * Two additional MASTER-verified gaps (added by the RED correction pass,
 * production writer's ManageIyzicoSandboxCheckout/Eloquent repository read
 * at authoring time confirms both are currently unmet):
 *  - A subscription whose ends_at falls exactly on "today" must remain
 *    active by date semantics for the whole day, not be treated as already
 *    lapsed by a strict "ends_at > now()" timestamp comparison
 *    (IYZ-SESSION-ENDS-TODAY-01; production currently compares
 *    subscriptions.ends_at > now() in
 *    EloquentIyzicoSandboxTransactionRepository::activeSubscriptionPlan()).
 *  - A concurrent race on the same Idempotency-Key must reserve/claim the
 *    key atomically before calling the provider, so exactly one
 *    initializeCheckout() call happens total. Frozen atomic repository claim
 *    contract (IYZ-SESSION-CONCURRENT-CLAIM-01; production currently calls
 *    gateway->initializeCheckout() first and only discovers the race via a
 *    createInitiated() conflict afterward, in
 *    App\Application\Billing\UseCase\ManageIyzicoSandboxCheckout::checkout()
 *    — an ordering that is impossible to satisfy since it requires provider
 *    output to exist before the provider is ever called):
 *      claimInitialization(workspaceId, actorUserId, planId, idempotencyKey,
 *        conversationId, amountMinor, currency): bool
 *        -> happens BEFORE gateway->initializeCheckout(); true = this
 *           request won the race and must call the provider next; false =
 *           this request lost and must never call the provider.
 *      completeInitialization(idempotencyKey, token, redirectUrl):
 *        IyzicoSandboxSession
 *        -> happens AFTER the provider returns a trusted (signature_valid)
 *           result; only the race winner calls it.
 *    The loser (claimInitialization === false) resolves the winner's
 *    already-committed same-workspace row (e.g. via findByIdempotencyKey())
 *    and never calls the provider. Across both racing requests exactly one
 *    initializeCheckout() call happens total.
 */
final class IyzicoSandboxCheckoutJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY_PORT = 'App\Application\Billing\Port\IyzicoSandboxGatewayPort';

    private const SANDBOX_SECRET = 'test-sandbox-secret-key-checkout';

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

    private function insertPlanRow(array $overrides = []): int
    {
        if (! Schema::hasTable('plans')) {
            self::fail('IYZ-SESSION: production "plans" table does not exist yet — RED must come from the missing production table, not a malformed fixture.');
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
            self::fail('IYZ-SESSION: production "subscriptions" table does not exist yet — RED must come from the missing production table, not a malformed fixture.');
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

    private function sessionUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/iyzico-sandbox/session";
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Binds a Mockery fake by the port's FQCN string into the container.
     * Mockery::mock() can create a mock for a class/interface that does not
     * exist yet, and container instance() binding is keyed by string, so
     * this never triggers autoloading of the not-yet-written production
     * port — it keeps RED meaningful (missing route/port) instead of a
     * fatal class-not-found error.
     */
    private function bindGatewayFake(): MockInterface
    {
        $fake = Mockery::mock(self::GATEWAY_PORT);
        app()->instance(self::GATEWAY_PORT, $fake);

        return $fake;
    }

    private function configureSandboxCredentials(): void
    {
        config()->set('services.iyzico.sandbox.api_key', 'sandbox-api-key');
        config()->set('services.iyzico.sandbox.secret_key', self::SANDBOX_SECRET);
        config()->set('services.iyzico.sandbox.base_url', 'https://sandbox-api.iyzipay.com');
    }

    // --- IYZ-SESSION-AUTHZ-01 -----------------------------------------------

    public function test_get_and_post_session_require_billing_manage_member_and_editor_get_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-authz-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-authz-01');

        $member = $this->verifiedUser('Mehmet Demir', 'mehmet-iyz-authz-01@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $editor = $this->verifiedUser('Elif Kaya', 'elif-iyz-authz-01@example.test');
        $this->addMember($workspaceId, $editor, 'editor');

        $ownerGet = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        $ownerGet->assertStatus(200, 'IYZ-SESSION-AUTHZ-01: owner billing.manage taşır — GET 200 dönmeli.');

        $memberGet = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        $memberGet->assertStatus(404, 'IYZ-SESSION-AUTHZ-01: member billing.manage taşımaz — 404 ile reddedilmeli.');

        $editorGet = $this->actingAs($editor)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        $editorGet->assertStatus(404, 'IYZ-SESSION-AUTHZ-01: editor billing.manage taşımaz — 404 ile reddedilmeli, 403 değil.');

        $memberPost = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $this->uuid()]);
        $memberPost->assertStatus(404, 'IYZ-SESSION-AUTHZ-01: member POST üzerinde de 404 almalı.');
    }

    // --- IYZ-SESSION-ESCAPE-01 ----------------------------------------------

    public function test_foreign_and_nonexistent_workspace_share_identical_404_signature(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-escape-01@example.test');
        $stranger = $this->verifiedUser('Can Öztürk', 'can-iyz-escape-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-escape-01');

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        $strangerResponse->assertStatus(404);

        $nonexistentResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri(999999999));

        self::assertSame(
            $strangerResponse->getStatusCode(),
            $nonexistentResponse->getStatusCode(),
            'IYZ-SESSION-ESCAPE-01: yabancı ve var olmayan workspace aynı imzayı taşımalı (enumeration-safe).'
        );
    }

    // --- IYZ-SESSION-GET-NONE-01 --------------------------------------------

    public function test_get_returns_exact_none_state_when_no_transaction_exists(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-getnone-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-getnone-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));

        $response->assertStatus(200, 'IYZ-SESSION-GET-NONE-01: işlem yokken 200 dönmeli.');
        self::assertSame(['state' => 'none'], $response->json(), 'IYZ-SESSION-GET-NONE-01: işlem yokken yanıt tam olarak {state: none} olmalı.');
    }

    // --- IYZ-SESSION-POST-PAYLOAD-01 ----------------------------------------

    public function test_post_rejects_client_supplied_plan_amount_currency_or_card_fields(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-payload-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-payload-01');
        $planId = $this->insertPlanRow(['code' => 'growth-payload-01']);
        $this->activeSubscription($workspaceId, $planId);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [
                'plan_id' => $planId,
                'amount_minor' => 1,
                'currency' => 'USD',
                'card_number' => '4111111111111111',
            ], ['Idempotency-Key' => $this->uuid()]);

        self::assertContains(
            $response->getStatusCode(),
            [400, 422],
            'IYZ-SESSION-POST-PAYLOAD-01: client plan/amount/currency/card alanları taşıyan gövde 400/422 ile reddedilmeli.'
        );
    }

    // --- IYZ-SESSION-POST-NO-SUBSCRIPTION-01 --------------------------------

    public function test_post_without_active_subscription_returns_409_and_mutates_nothing(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-nosub-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-nosub-01');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $this->uuid()]);

        $response->assertStatus(409, 'IYZ-SESSION-POST-NO-SUBSCRIPTION-01: aktif abonelik yokken 409 dönmeli.');

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        $followUp->assertStatus(200);
        self::assertSame(['state' => 'none'], $followUp->json(), 'IYZ-SESSION-POST-NO-SUBSCRIPTION-01: 409 sonrası hiçbir işlem satırı oluşmamalı.');
    }

    // --- IYZ-SESSION-POST-VALIDATION-01 -------------------------------------

    public function test_post_rejects_missing_or_invalid_idempotency_key_with_400_or_422_and_mutates_nothing(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-validation-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-validation-01');
        $planId = $this->insertPlanRow(['code' => 'growth-validation-01']);
        $this->activeSubscription($workspaceId, $planId);

        $missingKey = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), []);
        self::assertContains($missingKey->getStatusCode(), [400, 422], 'IYZ-SESSION-POST-VALIDATION-01: eksik Idempotency-Key 400/422 ile reddedilmeli.');

        $invalidKey = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => 'not-a-uuid']);
        self::assertContains($invalidKey->getStatusCode(), [400, 422], 'IYZ-SESSION-POST-VALIDATION-01: geçersiz Idempotency-Key 400/422 ile reddedilmeli.');

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        $followUp->assertStatus(200);
        self::assertSame(['state' => 'none'], $followUp->json(), 'IYZ-SESSION-POST-VALIDATION-01: reddedilen istekler hiçbir işlem satırı oluşturmamalı.');
    }

    // --- IYZ-SESSION-POST-CONFIG-MISSING-01 ---------------------------------

    public function test_post_returns_503_when_sandbox_provider_config_is_missing(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-config-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-config-01');
        $planId = $this->insertPlanRow(['code' => 'growth-config-01']);
        $this->activeSubscription($workspaceId, $planId);

        config()->set('services.iyzico.sandbox.api_key', null);
        config()->set('services.iyzico.sandbox.secret_key', null);
        config()->set('services.iyzico.sandbox.base_url', null);

        $fake = $this->bindGatewayFake();
        $fake->shouldNotReceive('initializeCheckout');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $this->uuid()]);

        $response->assertStatus(503, 'IYZ-SESSION-POST-CONFIG-MISSING-01: sandbox API key/secret/base URL eksikken dürüst 503 dönmeli.');
    }

    // --- IYZ-SESSION-POST-SUCCESS-01 ----------------------------------------

    public function test_successful_post_uses_server_plan_amount_and_currency_and_returns_only_the_frozen_fields(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-success-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-success-01');
        $planId = $this->insertPlanRow(['code' => 'growth-success-01', 'amount_minor' => 149900, 'currency' => 'TRY']);
        $this->activeSubscription($workspaceId, $planId);
        $this->configureSandboxCredentials();

        $fake = $this->bindGatewayFake();
        $fake->shouldReceive('initializeCheckout')
            ->once()
            ->withArgs(function (string $conversationId, int $workspaceIdArg, int $actorUserId, int $amountMinor, string $currency) use ($workspaceId, $owner) {
                self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $conversationId, 'IYZ-SESSION-POST-SUCCESS-01: gateway sunucu tarafından üretilen UUID conversation_id almalı.');
                self::assertSame($workspaceId, $workspaceIdArg, 'IYZ-SESSION-POST-SUCCESS-01: gateway doğru workspace_id almalı.');
                self::assertSame($owner->id, $actorUserId, 'IYZ-SESSION-POST-SUCCESS-01: gateway doğru actor user id almalı.');
                self::assertSame(149900, $amountMinor, 'IYZ-SESSION-POST-SUCCESS-01: gateway sunucu plan tutarını almalı, client değeri değil.');
                self::assertSame('TRY', $currency, 'IYZ-SESSION-POST-SUCCESS-01: gateway sunucu plan para birimini almalı, client değeri değil.');

                return true;
            })
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => true,
                'token' => 'sandbox-token-success-01',
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/success-01',
            ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $this->uuid()]);

        $response->assertStatus(202, 'IYZ-SESSION-POST-SUCCESS-01: başarılı POST 202 (initiated) dönmeli.');
        $body = $response->json();

        self::assertSame(
            ['state', 'conversation_id', 'amount_minor', 'currency', 'redirect_url'],
            array_keys($body),
            'IYZ-SESSION-POST-SUCCESS-01: yanıt yalnız state/conversation_id/amount_minor/currency/redirect_url alanlarını taşımalı.'
        );
        self::assertSame('initiated', $body['state']);
        self::assertSame(149900, $body['amount_minor'], 'IYZ-SESSION-POST-SUCCESS-01: amount_minor sunucu plan satırından gelmeli, client asla değer göndermez.');
        self::assertSame('TRY', $body['currency']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $body['conversation_id'], 'IYZ-SESSION-POST-SUCCESS-01: conversation_id sunucu tarafından üretilen UUID olmalı.');
        self::assertStringStartsWith('https://', (string) $body['redirect_url'], 'IYZ-SESSION-POST-SUCCESS-01: redirect_url hosted HTTPS Checkout Form sayfasına işaret etmeli.');
    }

    // --- IYZ-SESSION-POST-MALFORMED-SIGNATURE-01 -----------------------------

    public function test_post_rejects_malformed_or_untrusted_provider_initialization_result(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-malformed-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-malformed-01');
        $planId = $this->insertPlanRow(['code' => 'growth-malformed-01']);
        $this->activeSubscription($workspaceId, $planId);
        $this->configureSandboxCredentials();

        $fake = $this->bindGatewayFake();
        $fake->shouldReceive('initializeCheckout')
            ->once()
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => false,
                'token' => 'sandbox-token-malformed-01',
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/malformed-01',
            ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $this->uuid()]);

        $response->assertStatus(502, 'IYZ-SESSION-POST-MALFORMED-SIGNATURE-01: signature_valid=false/malformed provider sonucu güvenilmeyip 502 ile reddedilmeli (503 yalnız eksik config içindir).');

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        $followUp->assertStatus(200);
        self::assertSame(['state' => 'none'], $followUp->json(), 'IYZ-SESSION-POST-MALFORMED-SIGNATURE-01: güvenilmeyen provider sonucu hiçbir işlemi otoriter kılmamalı.');
    }

    // --- IYZ-SESSION-POST-REPLAY-01 -----------------------------------------

    public function test_replaying_same_workspace_and_idempotency_key_and_request_returns_authoritative_result_without_second_row(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-replay-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-replay-01');
        $planId = $this->insertPlanRow(['code' => 'growth-replay-01']);
        $this->activeSubscription($workspaceId, $planId);
        $this->configureSandboxCredentials();
        $idempotencyKey = $this->uuid();

        $fake = $this->bindGatewayFake();
        $fake->shouldReceive('initializeCheckout')
            ->once()
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => true,
                'token' => 'sandbox-token-replay-01',
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/replay-01',
            ]);

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $idempotencyKey]);
        $first->assertStatus(202);

        $replay = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $idempotencyKey]);

        self::assertContains($replay->getStatusCode(), [200, 202], 'IYZ-SESSION-POST-REPLAY-01: aynı workspace + aynı idempotency_key + aynı istek otoriter yanıtı tekrar dönmeli.');
        self::assertSame($first->json()['conversation_id'], $replay->json()['conversation_id'], 'IYZ-SESSION-POST-REPLAY-01: tekrarlanan istek ikinci bir sağlayıcı çağrısı/satır oluşturmamalı — aynı conversation_id dönmeli.');
        // Mockery::close() (tearDown) enforces the ->once() expectation above: a
        // second initializeCheckout() call on replay would fail this test.
    }

    // --- IYZ-SESSION-POST-CONFLICT-01 ---------------------------------------

    public function test_reusing_idempotency_key_for_a_conflicting_reuse_is_409(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-conflict-01@example.test');
        $firstWorkspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-conflict-01');
        $secondWorkspaceId = $this->workspaceOwnedBy($owner, 'Vişne Kafe', 'visne-iyz-conflict-01');
        $planId = $this->insertPlanRow(['code' => 'growth-conflict-01']);
        $this->activeSubscription($firstWorkspaceId, $planId);
        $this->activeSubscription($secondWorkspaceId, $planId);
        $this->configureSandboxCredentials();
        $idempotencyKey = $this->uuid();

        $fake = $this->bindGatewayFake();
        $fake->shouldReceive('initializeCheckout')
            ->once()
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => true,
                'token' => 'sandbox-token-conflict-01',
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/conflict-01',
            ]);

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($firstWorkspaceId), [], ['Idempotency-Key' => $idempotencyKey]);
        $first->assertStatus(202);

        $conflicting = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($secondWorkspaceId), [], ['Idempotency-Key' => $idempotencyKey]);

        $conflicting->assertStatus(409, 'IYZ-SESSION-POST-CONFLICT-01: aynı idempotency_key farklı bir workspace isteği için çakışmalı ve 409 dönmeli.');
        // Mockery::close() (tearDown) enforces the ->once() expectation above: the
        // conflicting request must never trigger a second initializeCheckout() call.
    }

    // --- IYZ-SESSION-AUTH-01 -------------------------------------------------

    public function test_session_endpoints_require_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-auth-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-auth-01');
        $unverified = User::factory()->unverified()->create();

        $guestResponse = $this->withHeaders($this->jsonHeaders())->getJson($this->sessionUri($workspaceId));
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'IYZ-SESSION-AUTH-01: kimliksiz istek 401/403 ile reddedilmeli (404 kabul edilmez — route var olmalı).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        $unverifiedResponse->assertStatus(403, 'IYZ-SESSION-AUTH-01: doğrulanmamış kullanıcı 403 ile reddedilmeli.');
    }

    // --- IYZ-SESSION-ENDS-TODAY-01 -------------------------------------------

    public function test_subscription_ending_today_remains_active_for_checkout(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-endstoday-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-endstoday-01');
        $planId = $this->insertPlanRow(['code' => 'growth-endstoday-01', 'amount_minor' => 149900, 'currency' => 'TRY']);

        if (! Schema::hasTable('subscriptions')) {
            self::fail('IYZ-SESSION-ENDS-TODAY-01: production "subscriptions" table does not exist yet — RED must come from the missing production table, not a malformed fixture.');
        }

        // "Ends today" by date semantics — a plain calendar date, not a
        // future timestamp. If the current wall-clock time is already past
        // midnight (which it always is, except at exactly 00:00:00), a
        // strict "ends_at > now()" comparison wrongly treats this
        // subscription as already lapsed.
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_id' => $planId,
            'state' => 'active',
            'ends_at' => now()->startOfDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->configureSandboxCredentials();
        $fake = $this->bindGatewayFake();
        $fake->shouldReceive('initializeCheckout')
            ->once()
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => true,
                'token' => 'sandbox-token-endstoday-01',
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/endstoday-01',
            ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $this->uuid()]);

        $response->assertStatus(202, 'IYZ-SESSION-ENDS-TODAY-01: ends_at bugüne ait bir abonelik gün boyunca aktif sayılmalı, "aktif abonelik yok" (409) ile reddedilmemeli.');
    }

    // --- IYZ-SESSION-CONCURRENT-CLAIM-01 -------------------------------------

    /**
     * Simulates two requests racing on the same Idempotency-Key by driving
     * the real HTTP POST endpoint twice against a Mockery fake of
     * IyzicoSandboxTransactionRepositoryPort implementing the frozen atomic
     * claim contract: claimInitialization(...) is called before the
     * provider for BOTH requests and returns true (won) then false (lost);
     * only the winner is allowed to call the provider and
     * completeInitialization() afterward. The loser resolves the winner's
     * already-committed row via findByIdempotencyKey() and never touches the
     * provider. Mockery may permit these not-yet-declared interface methods
     * on the FQCN-string mock — the interface class does not exist yet.
     * Mockery::close() (tearDown) enforces every ->once()/->twice()
     * expectation below regardless of which HTTP status either response
     * returns.
     */
    public function test_concurrent_same_idempotency_key_results_in_exactly_one_provider_initialize_call(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-race-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-race-01');
        $this->configureSandboxCredentials();
        $idempotencyKey = $this->uuid();
        $planId = 909101;

        $repositoryPort = 'App\Application\Billing\Port\IyzicoSandboxTransactionRepositoryPort';
        $repositoryFake = Mockery::mock($repositoryPort);
        app()->instance($repositoryPort, $repositoryFake);

        $repositoryFake->shouldReceive('activeSubscriptionPlan')
            ->with($workspaceId)
            ->andReturn(['plan_id' => $planId, 'amount_minor' => 149900, 'currency' => 'TRY']);

        $winningSession = new IyzicoSandboxSession('initiated', 'unit-race-conversation-01', 149900, 'TRY', 'https://sandbox-cf.iyzipay.com/checkout/race-01');

        // Both racers reach claimInitialization() BEFORE any provider call:
        // the first claim wins (true), the second loses (false).
        $repositoryFake->shouldReceive('claimInitialization')
            ->withArgs(function (int $wsId, int $actorUserId, int $pid, string $key, string $conversationId, int $amountMinor, string $currency) use ($workspaceId, $owner, $planId, $idempotencyKey) {
                return $wsId === $workspaceId
                    && $actorUserId === $owner->id
                    && $pid === $planId
                    && $key === $idempotencyKey
                    && $amountMinor === 149900
                    && $currency === 'TRY'
                    && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $conversationId) === 1;
            })
            ->twice()
            ->ordered()
            ->andReturn(true, false);

        // Only the winner may call the provider and complete afterward.
        $repositoryFake->shouldReceive('completeInitialization')
            ->with($idempotencyKey, 'sandbox-token-race-01', 'https://sandbox-cf.iyzipay.com/checkout/race-01')
            ->once()
            ->andReturn($winningSession);

        // The loser (claim === false) resolves the winner's committed row
        // and never calls the provider.
        $repositoryFake->shouldReceive('findByIdempotencyKey')
            ->with($idempotencyKey)
            ->once()
            ->andReturn([
                'id' => 1,
                'workspace_id' => $workspaceId,
                'conversation_id' => $winningSession->conversationId,
                'amount_minor' => $winningSession->amountMinor,
                'currency' => $winningSession->currency,
                'redirect_url' => $winningSession->redirectUrl,
                'state' => $winningSession->state,
            ]);

        $gatewayFake = $this->bindGatewayFake();
        $gatewayFake->shouldReceive('initializeCheckout')
            ->once()
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => true,
                'token' => 'sandbox-token-race-01',
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/race-01',
            ]);

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $idempotencyKey]);
        $second = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->sessionUri($workspaceId), [], ['Idempotency-Key' => $idempotencyKey]);

        self::assertContains($first->getStatusCode(), [202, 409], 'IYZ-SESSION-CONCURRENT-CLAIM-01: yarışan isteklerden biri otoriter sonucu dönmeli.');
        self::assertContains($second->getStatusCode(), [202, 409], 'IYZ-SESSION-CONCURRENT-CLAIM-01: yarışan isteklerden biri otoriter sonucu dönmeli.');
        // Mockery::close() (tearDown) enforces claimInitialization()->twice(),
        // completeInitialization()->once() and
        // gatewayFake->initializeCheckout()->once(): the key must be claimed
        // atomically before the provider is ever called, so a same-key race
        // reaches the provider exactly once total — never once per racing
        // request, and never with provider output required before the claim.
    }
}
