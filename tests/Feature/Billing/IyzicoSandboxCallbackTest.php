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
 * RED test candidate for the hosted-browser Iyzico Checkout Form callback,
 * an independent-reviewer/MASTER-verified gap distinct from
 * IyzicoSandboxWebhookTest.php.
 *
 * At authoring time, grep of routes/api.php finds zero route whose handler
 * is dedicated to a hosted-browser callback: the only two Iyzico-related
 * entries are the JSON session endpoints
 * (/api/workspaces/{workspace}/iyzico-sandbox/session) and the async V3
 * server-to-server webhook (/api/webhooks/iyzico-sandbox, handled by
 * ReceiveIyzicoSandboxWebhookController). Reading
 * app/Infrastructure/Billing/Provider/IyzipaySandboxGateway.php confirms the
 * gap concretely: initializeCheckout() sets the SDK request's callbackUrl to
 * config('app.url').'/api/webhooks/iyzico-sandbox' — i.e. production
 * currently points the buyer's browser redirect at the SAME URI as the
 * async webhook, with no distinct hosted-browser callback route/controller
 * at all. Every request below is expected to fail RED from the framework's
 * router (unmatched route -> 404), never from a syntax error, a malformed
 * fixture, or a real network call.
 *
 * Frozen contract (MASTER correction instruction, official iyzipay-php
 * Checkout Form hosted flow):
 *  - The hosted browser callback is a SEPARATE concern from the V3
 *    server-to-server webhook: after the buyer completes/cancels payment on
 *    iyzico's hosted page, iyzico's Checkout Form redirects the buyer's own
 *    browser back with a POST carrying only a provider "token" form field
 *    (application/x-www-form-urlencoded, not JSON, and NOT the
 *    X-IYZ-SIGNATURE-V3 header the async webhook carries).
 *  - The callback route accepts that POST unauthenticated (the buyer's
 *    browser holds no API session for this workspace), resolves the stored
 *    transaction/token, calls the same signature-verified
 *    IyzicoSandboxGatewayPort::retrieveCheckout(token, conversationId) used
 *    by the webhook, transitions the transaction safely, and then redirects
 *    (3xx) the browser to a safe, same-origin local billing route — never
 *    to a provider URL or any attacker/client-influenced target (no open
 *    redirect).
 *  - A missing/unknown token never retrieves and never crashes; it still
 *    redirects safely to the same local billing destination (no enumeration
 *    leak, no dead end for the buyer's browser).
 *  - Duplicate/out-of-order/terminal callbacks (a transaction the webhook or
 *    a prior callback has already resolved to succeeded/failed) never
 *    overwrite that terminal result — the callback path shares the same
 *    terminal-state immutability guarantee frozen for the webhook in
 *    IyzicoSandboxWebhookTest.php.
 *
 * Requirement IDs: IYZ-CALLBACK-ROUTE-01, IYZ-CALLBACK-SUCCESS-01,
 * IYZ-CALLBACK-SAFE-REDIRECT-01, IYZ-CALLBACK-UNKNOWN-TOKEN-01,
 * IYZ-CALLBACK-TERMINAL-IMMUTABLE-01.
 */
final class IyzicoSandboxCallbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Frozen by this RED package, sibling to the existing
     * /api/webhooks/iyzico-sandbox and
     * /api/workspaces/{workspace}/iyzico-sandbox/session routes: a
     * dedicated, workspace-agnostic hosted-browser callback endpoint that
     * resolves the transaction from the provider-supplied token itself
     * (the buyer's browser carries no workspace-scoped API session).
     */
    private const CALLBACK_URI = '/api/billing/iyzico-sandbox/callback';

    private const SANDBOX_SECRET = 'test-sandbox-secret-key-callback';

    private const GATEWAY_PORT = 'App\Application\Billing\Port\IyzicoSandboxGatewayPort';

    private const INIT_TOKEN = 'sandbox-token-callback-init-01';

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
            self::fail('IYZ-CALLBACK: production "plans" table does not exist yet — RED must come from the missing production table, not a malformed fixture.');
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
            self::fail('IYZ-CALLBACK: production "subscriptions" table does not exist yet — RED must come from the missing production table, not a malformed fixture.');
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
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

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

    private function configureSandboxCredentials(): void
    {
        config()->set('services.iyzico.sandbox.api_key', 'sandbox-api-key');
        config()->set('services.iyzico.sandbox.secret_key', self::SANDBOX_SECRET);
        config()->set('services.iyzico.sandbox.base_url', 'https://sandbox-api.iyzipay.com');
    }

    /**
     * Opens an initiated Iyzico sandbox checkout session for the workspace
     * via the sibling backend endpoint frozen in
     * IyzicoSandboxCheckoutJourneyTest, pinning self::INIT_TOKEN as the
     * transaction's stored token — never a real adapter reached with dummy
     * credentials, so this stays a pure, network-free setup step.
     */
    private function initiateSandboxCheckout(User $owner, int $workspaceId): array
    {
        $this->configureSandboxCredentials();

        $fake = $this->bindGatewayFake();
        $fake->shouldReceive('initializeCheckout')
            ->once()
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => true,
                'token' => self::INIT_TOKEN,
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/callback-setup-01',
            ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/iyzico-sandbox/session", [], ['Idempotency-Key' => $this->uuid()]);

        if ($response->getStatusCode() !== 202) {
            self::fail('IYZ-CALLBACK: sandbox checkout session açılamadı (production POST endpoint henüz yok veya farklı davranıyor) — callback senaryosu gerçek bir conversation_id/token gerektirir.');
        }

        return $response->json();
    }

    private function sessionUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/iyzico-sandbox/session";
    }

    // --- IYZ-CALLBACK-ROUTE-01 -----------------------------------------------

    public function test_hosted_browser_callback_route_exists_and_is_reachable_without_authentication(): void
    {
        $response = $this->post(self::CALLBACK_URI, ['token' => 'sandbox-token-'.bin2hex(random_bytes(4))]);

        self::assertNotSame(
            404,
            $response->getStatusCode(),
            'IYZ-CALLBACK-ROUTE-01: hosted-browser Iyzico Checkout Form callback rotası kimliksiz olarak erişilebilir olmalı ve async V3 webhook rotasından ayrı, kendi rotasına sahip olmalı (bkz. IyzipaySandboxGateway::initializeCheckout() şu an callbackUrl\'i webhook URI\'siyle aynı ayarlıyor).'
        );
    }

    // --- IYZ-CALLBACK-SUCCESS-01 ---------------------------------------------

    public function test_callback_with_the_stored_token_retrieves_and_transitions_the_transaction(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-cb-success-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-cb-success-01');
        $planId = $this->insertPlanRow(['code' => 'growth-cb-success-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);

        $this->gatewayFake()->shouldReceive('retrieveCheckout')
            ->once()
            ->with(self::INIT_TOKEN, $session['conversation_id'])
            ->andReturn([
                'signature_valid' => true,
                'status' => 'SUCCESS',
                'conversation_id' => $session['conversation_id'],
                'amount_minor' => $session['amount_minor'],
                'currency' => $session['currency'],
                'payment_id' => 'callback-payment-01',
            ]);

        $response = $this->post(self::CALLBACK_URI, ['token' => self::INIT_TOKEN]);

        self::assertContains(
            $response->getStatusCode(),
            [301, 302, 303, 307, 308],
            'IYZ-CALLBACK-SUCCESS-01: geçerli token ile callback, tarayıcıyı güvenli yerel billing rotasına yönlendirmeli (3xx).'
        );

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        self::assertSame('succeeded', $followUp->json()['state'] ?? null, 'IYZ-CALLBACK-SUCCESS-01: callback, retrieveCheckout() ile doğrulanan SUCCESS sonucuna göre işlemi succeeded durumuna geçirmeli.');
    }

    // --- IYZ-CALLBACK-SAFE-REDIRECT-01 ---------------------------------------

    public function test_callback_redirect_target_is_always_a_safe_local_billing_path_never_client_influenced(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-cb-saferedirect-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-cb-saferedirect-01');
        $planId = $this->insertPlanRow(['code' => 'growth-cb-saferedirect-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);

        $this->gatewayFake()->shouldReceive('retrieveCheckout')
            ->once()
            ->with(self::INIT_TOKEN, $session['conversation_id'])
            ->andReturn([
                'signature_valid' => true,
                'status' => 'FAILURE',
                'conversation_id' => $session['conversation_id'],
                'amount_minor' => $session['amount_minor'],
                'currency' => $session['currency'],
                'payment_id' => 'callback-payment-saferedirect-01',
            ]);

        // A client-controlled "next"/"return_to"-style field, if the
        // controller naively echoes any request input into the redirect,
        // must never influence the Location header — open-redirect guard.
        $response = $this->post(self::CALLBACK_URI, [
            'token' => self::INIT_TOKEN,
            'next' => 'https://attacker.invalid/phish',
            'return_to' => '//attacker.invalid/phish',
        ]);

        $location = $response->headers->get('Location');

        self::assertNotNull($location, 'IYZ-CALLBACK-SAFE-REDIRECT-01: callback bir Location başlığı içeren yönlendirme dönmeli.');
        self::assertStringNotContainsString('attacker.invalid', (string) $location, 'IYZ-CALLBACK-SAFE-REDIRECT-01: yönlendirme hedefi asla client tarafından etkilenen bir open-redirect olmamalı.');
        self::assertMatchesRegularExpression('#^(/|'.preg_quote(config('app.url'), '#').')#', (string) $location, 'IYZ-CALLBACK-SAFE-REDIRECT-01: yönlendirme hedefi yalnız yerel (same-origin) bir billing rotası olmalı.');
    }

    // --- IYZ-CALLBACK-UNKNOWN-TOKEN-01 ----------------------------------------

    public function test_callback_with_unknown_or_missing_token_never_retrieves_and_still_redirects_safely(): void
    {
        $this->bindGatewayFake()->shouldNotReceive('retrieveCheckout');

        $response = $this->post(self::CALLBACK_URI, ['token' => 'unknown-token-'.bin2hex(random_bytes(4))]);

        self::assertContains(
            $response->getStatusCode(),
            [301, 302, 303, 307, 308],
            'IYZ-CALLBACK-UNKNOWN-TOKEN-01: bilinmeyen/eksik token, retrieveCheckout() çağırmadan tarayıcıyı yine de güvenli yerel bir rotaya yönlendirmeli (buyer\'ın tarayıcısı çıkmaza girmemeli).'
        );
        // Mockery::close() (tearDown) enforces shouldNotReceive('retrieveCheckout')
        // above: an unresolvable token must never reach the provider.
    }

    // --- IYZ-CALLBACK-TERMINAL-IMMUTABLE-01 -----------------------------------

    public function test_callback_for_an_already_terminal_transaction_never_overwrites_the_terminal_result(): void
    {
        $owner = $this->verifiedUser('Ayşe Yılmaz', 'ayse-iyz-cb-terminal-01@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-iyz-cb-terminal-01');
        $planId = $this->insertPlanRow(['code' => 'growth-cb-terminal-01']);
        $this->activeSubscription($workspaceId, $planId);
        $session = $this->initiateSandboxCheckout($owner, $workspaceId);

        // The V3 webhook already resolved this transaction to succeeded,
        // exactly as frozen in IyzicoSandboxWebhookTest.
        $referenceCode = 'ref-cb-terminal-01';
        $webhookPayload = [
            'iyziEventType' => 'CHECKOUT_FORM_AUTH',
            'iyziPaymentId' => 'webhook-payment-terminal-01',
            'token' => self::INIT_TOKEN,
            'paymentConversationId' => $session['conversation_id'],
            'status' => 'SUCCESS',
            'iyziReferenceCode' => $referenceCode,
        ];
        $this->gatewayFake()->shouldReceive('retrieveCheckout')
            ->once()
            ->with(self::INIT_TOKEN, $session['conversation_id'])
            ->andReturn([
                'signature_valid' => true,
                'status' => 'SUCCESS',
                'conversation_id' => $session['conversation_id'],
                'amount_minor' => $session['amount_minor'],
                'currency' => $session['currency'],
                'payment_id' => 'webhook-payment-terminal-01',
            ]);
        $webhookSignature = hash_hmac(
            'sha256',
            self::SANDBOX_SECRET.$webhookPayload['iyziEventType'].$webhookPayload['iyziPaymentId'].$webhookPayload['token'].$webhookPayload['paymentConversationId'].$webhookPayload['status'],
            self::SANDBOX_SECRET,
        );
        $webhookResponse = $this->withHeaders(array_merge($this->jsonHeaders(), ['X-IYZ-SIGNATURE-V3' => $webhookSignature]))
            ->postJson('/api/webhooks/iyzico-sandbox', $webhookPayload);
        $webhookResponse->assertStatus(200);

        $terminalCheck = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        self::assertSame('succeeded', $terminalCheck->json()['state'] ?? null, 'IYZ-CALLBACK-TERMINAL-IMMUTABLE-01: kurulum adımı işlemi succeeded durumuna getirmeli.');

        // The buyer's browser now also lands on the callback (out-of-order
        // delivery vs. the already-processed webhook), and — pathologically
        // — a fresh retrieve would report FAILURE. The terminal succeeded
        // result must never be overwritten.
        $callbackResponse = $this->post(self::CALLBACK_URI, ['token' => self::INIT_TOKEN]);

        self::assertContains(
            $callbackResponse->getStatusCode(),
            [301, 302, 303, 307, 308],
            'IYZ-CALLBACK-TERMINAL-IMMUTABLE-01: zaten terminal bir işlem için callback yine de tarayıcıyı güvenli yerel rotaya yönlendirmeli.'
        );

        $followUp = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->sessionUri($workspaceId));
        self::assertSame('succeeded', $followUp->json()['state'] ?? null, 'IYZ-CALLBACK-TERMINAL-IMMUTABLE-01: zaten terminal (succeeded) bir işlem, yinelenen/sırasız bir callback tarafından asla ezilmemeli.');
    }
}
