<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Exception\PaymentGatewayUnavailableException;
use App\Application\Billing\Port\BillingModePort;
use App\Domain\Billing\BillingMode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SELF-SERVE — kiracının kendi kendine plan seçip abone olduğu yol (docs/107
 * Faz 1.1 + 1.3, docs/110 P1-02 (a)).
 *
 * Dondurulan sözleşme:
 *  - POST /api/workspaces/{w}/checkout gövdesi YALNIZ plan_id + idempotency_key
 *    taşır; tutar SUNUCUDAN (plan kataloğu) okunur, istemciden gelen tutar/
 *    para birimi/kart alanı 422 ile reddedilir.
 *  - Fatura profili yoksa ödeme BAŞLAMAZ ve red ADIYLA söylenir
 *    (reason: billing_profile_missing) — sağlayıcı hiç çağrılmaz.
 *  - Kip anahtarı kapalıyken (services.iyzico.mode=sandbox) canlı geçit
 *    HİÇ çağrılmaz; sandbox geçidi kullanılır.
 *  - SUCCESS: işlem succeeded, abonelik active, ends_at bir dönem
 *    (config billing.subscription.period_days) ileri — varsa uzatır, yoksa
 *    oluşturur; defterde TEK satır; aynı olayın webhook tekrarı hiçbir şey
 *    eklemez.
 *  - FAILURE: işlem failed + sebep; abonelik ve defter değişmez; yeni bir
 *    anahtarla tekrar denenebilir.
 *
 * Geçit portları (Mockery FQCN dizesiyle bağlanır — henüz yokken de RED
 * anlamlı kalsın diye):
 *   App\Application\Billing\Port\SandboxPaymentGatewayPort
 *   App\Application\Billing\Port\LivePaymentGatewayPort
 *   ikisi de App\Application\Billing\Port\PaymentGatewayPort'u genişletir:
 *     initializeCheckout(conversationId, workspaceId, actorUserId, amountMinor, currency, ?BillingProfile)
 *       -> [signature_valid, token, conversation_id, redirect_url]
 *     retrieveCheckout(token, conversationId)
 *       -> [signature_valid, status, conversation_id, amount_minor, currency, payment_id, payment_transaction_id, error_message]
 *     refund(conversationId, paymentTransactionId, amountMinor, currency, reason)
 *       -> [status, payment_id, conversation_id, amount_minor, currency, error_message]
 */
final class SelfServeCheckoutJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_GATEWAY = 'App\Application\Billing\Port\SandboxPaymentGatewayPort';

    private const LIVE_GATEWAY = 'App\Application\Billing\Port\LivePaymentGatewayPort';

    private const SANDBOX_SECRET = 'self-serve-sandbox-secret-01';

    private const PLAN_AMOUNT = 149900;

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

    private function verifiedUser(string $email): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    private function workspaceOwnedBy(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kadıköy Kebap',
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
        return (int) DB::table('plans')->insertGetId(array_merge([
            'name' => 'Pro',
            'code' => 'pro-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => json_encode(['menu.publish']),
            'amount_minor' => self::PLAN_AMOUNT,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function completeProfile(int $workspaceId): void
    {
        DB::table('billing_profiles')->insert([
            'workspace_id' => $workspaceId,
            'legal_name' => 'Kadıköy Kebap Gıda Ltd. Şti.',
            'tax_number' => '1234567890',
            'tax_office' => 'Kadıköy',
            'address' => 'Moda Cad. No:1',
            'city' => 'Istanbul',
            'country' => 'TR',
            'email' => 'muhasebe@kadikoykebap.test',
            'phone' => '+905551112233',
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

    private function checkoutUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/checkout";
    }

    /**
     * Ödeme başlatma gövdesi — İKİ ONAY dâhil (FF-216).
     *
     * Onaylar burada, çünkü artık ödeme akışının bir PARÇASILAR: gövdeyi
     * onaysız kurmak, ürünün bugün reddettiği bir istek kurmak olurdu. Her
     * çağrı yeni bir `idempotency_key` alır.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function checkoutBody(int $planId, array $overrides = []): array
    {
        return array_merge([
            'plan_id' => $planId,
            'idempotency_key' => $this->uuid(),
            'agreements_accepted' => true,
            'immediate_performance_accepted' => true,
        ], $overrides);
    }

    private function configureSandbox(): void
    {
        config()->set('services.iyzico.mode', 'sandbox');
        config()->set('services.iyzico.sandbox.api_key', 'sandbox-api-key');
        config()->set('services.iyzico.sandbox.secret_key', self::SANDBOX_SECRET);
        config()->set('services.iyzico.sandbox.base_url', 'https://sandbox-api.iyzipay.com');
        config()->set('billing.subscription.period_days', 30);
    }

    private function bindFake(string $port): MockInterface
    {
        $fake = Mockery::mock($port);
        app()->instance($port, $fake);

        return $fake;
    }

    private function expectInitialize(MockInterface $fake, string $token): void
    {
        $fake->shouldReceive('initializeCheckout')
            ->once()
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => true,
                'token' => $token,
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/'.$token,
            ]);
    }

    private function retrieveResult(string $conversationId, string $status, string $paymentId, ?string $error = null): array
    {
        return [
            'signature_valid' => true,
            'status' => $status,
            'conversation_id' => $conversationId,
            'amount_minor' => self::PLAN_AMOUNT,
            'currency' => 'TRY',
            'payment_id' => $paymentId,
            'payment_transaction_id' => $paymentId.'-tx',
            'error_message' => $error,
        ];
    }

    private function signature(array $payload): string
    {
        return hash_hmac(
            'sha256',
            self::SANDBOX_SECRET.$payload['iyziEventType'].$payload['iyziPaymentId'].$payload['token'].$payload['paymentConversationId'].$payload['status'],
            self::SANDBOX_SECRET,
        );
    }

    private function webhookPayload(string $conversationId, string $token, string $status, string $paymentId, string $reference): array
    {
        return [
            'iyziEventType' => 'CHECKOUT_FORM_AUTH',
            'iyziPaymentId' => $paymentId,
            'token' => $token,
            'paymentConversationId' => $conversationId,
            'status' => $status,
            'iyziReferenceCode' => $reference,
        ];
    }

    /** @return array{owner: User, workspaceId: int, planId: int} */
    private function readyWorkspace(string $slug): array
    {
        $owner = $this->verifiedUser($slug.'@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, $slug);
        $planId = $this->insertPlanRow();
        $this->completeProfile($workspaceId);
        $this->configureSandbox();

        return ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId];
    }

    // --- SELF-SERVE-AUTHZ-01 -----------------------------------------------

    #[Test]
    public function checkout_requires_billing_manage_and_is_enumeration_safe(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('authz');
        $manager = $this->verifiedUser('manager-authz@example.test');
        $this->addMember($workspaceId, $manager, 'manager');
        $outsider = $this->verifiedUser('outsider-authz@example.test');

        $this->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(401);

        // Yönetici planı GÖRÜR ama satın alamaz: 404, 403 değil.
        $this->actingAs($manager)->withHeaders($this->jsonHeaders())
            ->getJson($this->checkoutUri($workspaceId))
            ->assertOk();
        $this->actingAs($manager)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(404);

        $this->actingAs($outsider)->withHeaders($this->jsonHeaders())
            ->getJson($this->checkoutUri($workspaceId))
            ->assertStatus(404);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->checkoutUri($workspaceId))
            ->assertOk()
            ->assertJson(['mode' => 'sandbox', 'period_days' => 30, 'profile_complete' => true, 'latest' => null]);

        self::assertSame(0, DB::table('payment_transactions')->count());
    }

    // --- SELF-SERVE-SERVER-AMOUNT-01 ---------------------------------------

    #[Test]
    public function checkout_refuses_client_supplied_amount_currency_or_card_fields(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('payload');
        $this->bindFake(self::SANDBOX_GATEWAY)->shouldNotReceive('initializeCheckout');

        foreach ([['amount_minor' => 1], ['currency' => 'USD'], ['card_number' => '4111111111111111']] as $extra) {
            $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId, $extra))
                ->assertStatus(422);
        }

        self::assertSame(0, DB::table('payment_transactions')->count());
    }

    // --- SELF-SERVE-CONSENT-01: onaysız sipariş BAŞLAMAZ (FF-216) ----------

    /**
     * Ödeme adımının İKİ onayı sunucuda ayrı ayrı zorunlu.
     *
     * Kutu boşken sağlayıcı HİÇ çağrılmaz: onaysız bir istek için Iyzico'da
     * bir oturum açmak, kurulmamış bir sözleşme için gerçek bir ödeme sayfası
     * üretmek olurdu.
     */
    #[Test]
    public function checkout_is_refused_when_either_consent_box_is_empty(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('consent');
        $this->bindFake(self::SANDBOX_GATEWAY)->shouldNotReceive('initializeCheckout');

        $cases = [
            ['agreements_accepted' => false],
            ['immediate_performance_accepted' => false],
            // Alan hiç GELMEZSE de düşer: sessizlik onay değildir.
            ['agreements_accepted' => null],
            ['immediate_performance_accepted' => null],
        ];

        foreach ($cases as $override) {
            $body = $this->checkoutBody($planId, $override);

            foreach ($override as $field => $value) {
                if ($value === null) {
                    unset($body[$field]);
                }
            }

            $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->postJson($this->checkoutUri($workspaceId), $body)
                ->assertStatus(422);
        }

        self::assertSame(0, DB::table('payment_transactions')->count());
        self::assertSame(0, DB::table('consent_records')->where('kind', 'checkout')->count());
    }

    /**
     * Sipariş başladığında onay DEFTERE düşer — üç satır, iki kip.
     *
     * Kadıköy'deki kebapçı "Pro"yu seçti, iki kutuyu işaretledi ve ödeme
     * sayfasına gitti: defterde ön bilgilendirme formunu ve mesafeli satış
     * sözleşmesini kabul ettiği, ve ifaya derhâl başlanmasını AYRICA
     * istediği yazıyor. Üçüncü satır, cayma hakkının ne zaman sona erdiğini
     * gösteren kanıttır.
     */
    #[Test]
    public function a_started_checkout_writes_the_two_agreements_and_the_immediate_performance_consent(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('ledger');
        $fake = $this->bindFake(self::SANDBOX_GATEWAY);
        $this->expectInitialize($fake, 'tok-consent');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(202);

        $rows = DB::table('consent_records')
            ->where('user_id', $owner->id)
            ->orderBy('kind')->orderBy('document_key')
            ->get(['kind', 'document_key', 'workspace_id', 'granted']);

        self::assertSame(
            [
                ['checkout', 'distance-sales'],
                ['checkout', 'pre-information'],
                ['immediate_performance', 'distance-sales'],
            ],
            $rows->map(static fn ($row): array => [$row->kind, $row->document_key])->all(),
        );

        foreach ($rows as $row) {
            self::assertSame($workspaceId, (int) $row->workspace_id);
            self::assertSame(1, (int) $row->granted);
        }
    }

    // --- SELF-SERVE-SELLER-IDENTITY-01: satıcısı olmayan satış yok --------

    /**
     * Canlı kipte, satıcının yasal kimliği yayınlanmadan tahsilat başlamaz.
     *
     * Alıcının bilgisi zaten zorunluydu; satıcınınki bugüne kadar hiç
     * sorulmuyordu. Sandbox'ta yol AÇIK kalır: prova, sahibin şirketini
     * kurmasını beklemek zorunda değil.
     */
    #[Test]
    public function a_live_checkout_is_refused_by_name_while_the_seller_identity_is_missing(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('seller');
        $this->bindFake(self::LIVE_GATEWAY)->shouldNotReceive('initializeCheckout');

        /*
            ETKİN KİP CANLI. Üç kapının (dağıtım + süperadmin + kasa)
            hepsini bu testte kurmak, ölçmek istediğimiz şeyi ölçmeyi
            zorlaştırırdı: buradaki soru kip anahtarının nasıl açıldığı
            değil, AÇIKKEN satıcısı olmayan bir satışın reddedilmesi.
        */
        $this->mock(BillingModePort::class, function (MockInterface $mode): void {
            $mode->shouldReceive('effective')->andReturn(BillingMode::Live);
        });

        config(['legal.company' => array_fill_keys(
            ['legal_name', 'address', 'mersis', 'tax_office', 'tax_number', 'email', 'phone'],
            null,
        )]);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(409)
            ->assertJson(['reason' => 'seller_identity_missing']);

        self::assertSame(0, DB::table('payment_transactions')->count());
        self::assertSame(0, DB::table('consent_records')->where('kind', 'checkout')->count());
    }

    // --- SELF-SERVE-PROFILE-REQUIRED-01 ------------------------------------

    #[Test]
    public function checkout_is_refused_by_name_when_the_billing_profile_is_missing(): void
    {
        $owner = $this->verifiedUser('noprofile@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'noprofile');
        $planId = $this->insertPlanRow();
        $this->configureSandbox();
        $this->bindFake(self::SANDBOX_GATEWAY)->shouldNotReceive('initializeCheckout');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->checkoutUri($workspaceId))
            ->assertOk()
            ->assertJson(['profile_complete' => false]);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(422)
            ->assertJson(['reason' => 'billing_profile_missing']);

        self::assertSame(0, DB::table('payment_transactions')->count());
    }

    // --- SELF-SERVE-PLAN-PRICED-01 -----------------------------------------

    #[Test]
    public function checkout_refuses_a_plan_without_a_price_or_an_inactive_plan(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId] = $this->readyWorkspace('unpriced');
        $unpriced = $this->insertPlanRow(['amount_minor' => null, 'currency' => null]);
        $inactive = $this->insertPlanRow(['is_active' => false]);
        $this->bindFake(self::SANDBOX_GATEWAY)->shouldNotReceive('initializeCheckout');

        foreach ([$unpriced, $inactive, 999999] as $planId) {
            $this->actingAs($owner)->withHeaders($this->jsonHeaders())
                ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
                ->assertStatus(422);
        }

        self::assertSame(0, DB::table('payment_transactions')->count());
    }

    // --- SELF-SERVE-INITIATE-01 --------------------------------------------

    #[Test]
    public function checkout_charges_the_server_price_and_hands_the_billing_profile_to_the_gateway(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('initiate');
        $fake = $this->bindFake(self::SANDBOX_GATEWAY);
        $fake->shouldReceive('initializeCheckout')
            ->once()
            ->withArgs(function (string $conversationId, int $ws, int $actor, int $amount, string $currency, $buyer) use ($workspaceId, $owner): bool {
                self::assertSame($workspaceId, $ws);
                self::assertSame($owner->id, $actor);
                self::assertSame(self::PLAN_AMOUNT, $amount, 'Tutar SUNUCUDAN gelir.');
                self::assertSame('TRY', $currency);
                self::assertSame('Kadıköy Kebap Gıda Ltd. Şti.', $buyer->legalName, 'Alıcı bilgisi UYDURULMAZ — fatura profilinden gelir.');

                return true;
            })
            ->andReturnUsing(fn (string $conversationId): array => [
                'signature_valid' => true,
                'token' => 'tok-initiate-01',
                'conversation_id' => $conversationId,
                'redirect_url' => 'https://sandbox-cf.iyzipay.com/checkout/initiate-01',
            ]);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId));

        $response->assertStatus(202);
        $body = $response->json();
        self::assertSame('initiated', $body['state']);
        self::assertSame('sandbox', $body['mode']);
        self::assertSame(self::PLAN_AMOUNT, $body['amount_minor']);
        self::assertSame('TRY', $body['currency']);
        self::assertSame($planId, $body['plan_id']);
        self::assertStringStartsWith('https://', $body['redirect_url']);
        self::assertArrayNotHasKey('token', $body, 'Sağlayıcı jetonu istemciye sızmaz.');

        $row = DB::table('payment_transactions')->first();
        self::assertSame('initiated', $row->state);
        self::assertSame('sandbox', $row->mode);
        self::assertSame(30, (int) $row->period_days);
    }

    // --- SELF-SERVE-LIVE-SWITCH-OFF-01 -------------------------------------

    #[Test]
    public function the_live_gateway_is_never_called_while_the_switch_is_off(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('switchoff');
        $this->bindFake(self::LIVE_GATEWAY)->shouldNotReceive('initializeCheckout');
        $this->expectInitialize($this->bindFake(self::SANDBOX_GATEWAY), 'tok-switchoff');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(202)
            ->assertJson(['mode' => 'sandbox']);
    }

    // --- SELF-SERVE-SUCCESS-01 ---------------------------------------------

    #[Test]
    public function a_successful_payment_activates_one_period_writes_one_ledger_row_and_a_replayed_webhook_adds_nothing(): void
    {
        Carbon::setTestNow('2026-09-06 10:00:00');
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('success');
        $fake = $this->bindFake(self::SANDBOX_GATEWAY);
        $this->expectInitialize($fake, 'tok-success');

        $body = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(202)->json();
        $conversationId = $body['conversation_id'];

        $fake->shouldReceive('retrieveCheckout')
            ->once()
            ->with('tok-success', $conversationId)
            ->andReturn($this->retrieveResult($conversationId, 'SUCCESS', 'pay-success-01'));

        // 1. Tarayıcı geri dönüşü — kartı giren sahip panele döner.
        $this->post('/api/billing/iyzico/callback', ['token' => 'tok-success'])
            ->assertStatus(303)
            ->assertHeader('Location', config('app.url').'/app#billing');

        $subscription = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();
        self::assertNotNull($subscription, 'Abonelik yoksa OLUŞUR.');
        self::assertSame('active', $subscription->state);
        self::assertSame($planId, (int) $subscription->plan_id);
        self::assertTrue(Carbon::parse($subscription->ends_at)->equalTo(Carbon::parse('2026-10-06 10:00:00')), 'ends_at bir dönem (30 gün) ileri.');

        $ledger = DB::table('ledger_entries')->where('workspace_id', $workspaceId)->get();
        self::assertCount(1, $ledger);
        self::assertSame('cash', $ledger[0]->debit_account);
        self::assertSame('revenue', $ledger[0]->credit_account);
        self::assertSame(self::PLAN_AMOUNT, (int) $ledger[0]->amount_minor);
        self::assertSame('payment:'.$body['id'], $ledger[0]->reference);

        $row = DB::table('payment_transactions')->where('id', $body['id'])->first();
        self::assertSame('succeeded', $row->state);
        self::assertSame('pay-success-01-tx', $row->payment_transaction_id, 'İade için gereken sağlayıcı işlem kimliği saklanır.');

        // 2. Aynı olayın webhook'u — iki kez.
        $payload = $this->webhookPayload($conversationId, 'tok-success', 'SUCCESS', 'pay-success-01', 'ref-success-01');
        $headers = ['X-IYZ-SIGNATURE-V3' => $this->signature($payload)];

        $this->postJson('/api/webhooks/iyzico', $payload, $headers)->assertOk();
        $this->postJson('/api/webhooks/iyzico', $payload, $headers)->assertOk();

        self::assertSame(1, DB::table('ledger_entries')->where('workspace_id', $workspaceId)->count(), 'Aynı ödeme üç kez bildirildi, defterde TEK satır.');
        $after = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();
        self::assertTrue(Carbon::parse($after->ends_at)->equalTo(Carbon::parse('2026-10-06 10:00:00')), 'Tekrar bildirim aboneliği İKİNCİ kez uzatmaz.');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->checkoutUri($workspaceId))
            ->assertOk()
            ->assertJsonPath('latest.state', 'succeeded');
    }

    // --- SELF-SERVE-EXTEND-01 ----------------------------------------------

    #[Test]
    public function a_payment_on_an_existing_subscription_extends_from_its_end_date_and_switches_to_the_paid_plan(): void
    {
        Carbon::setTestNow('2026-09-06 10:00:00');
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $proPlan] = $this->readyWorkspace('extend');
        $starterPlan = $this->insertPlanRow(['name' => 'Starter', 'amount_minor' => 49900]);
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_id' => $starterPlan,
            'state' => 'active',
            'ends_at' => Carbon::parse('2026-09-16 10:00:00'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fake = $this->bindFake(self::SANDBOX_GATEWAY);
        $this->expectInitialize($fake, 'tok-extend');

        $body = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($proPlan))
            ->assertStatus(202)->json();

        $fake->shouldReceive('retrieveCheckout')->once()
            ->andReturn($this->retrieveResult($body['conversation_id'], 'SUCCESS', 'pay-extend-01'));

        $payload = $this->webhookPayload($body['conversation_id'], 'tok-extend', 'SUCCESS', 'pay-extend-01', 'ref-extend-01');
        $this->postJson('/api/webhooks/iyzico', $payload, ['X-IYZ-SIGNATURE-V3' => $this->signature($payload)])->assertOk();

        $subscription = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();
        self::assertSame($proPlan, (int) $subscription->plan_id, 'Ödenen plan geçerli plan olur.');
        self::assertTrue(Carbon::parse($subscription->ends_at)->equalTo(Carbon::parse('2026-10-16 10:00:00')), 'Kalan 10 gün kaybolmaz: bitişin ÜSTÜNE 30 gün.');
        self::assertSame(1, DB::table('subscriptions')->where('workspace_id', $workspaceId)->count());
    }

    // --- SELF-SERVE-FAILURE-01 ---------------------------------------------

    #[Test]
    public function a_failed_payment_is_recorded_with_its_reason_leaves_subscription_and_ledger_untouched_and_can_be_retried(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('failure');
        $fake = $this->bindFake(self::SANDBOX_GATEWAY);
        $this->expectInitialize($fake, 'tok-failure');

        $body = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(202)->json();

        $fake->shouldReceive('retrieveCheckout')->once()
            ->andReturn($this->retrieveResult($body['conversation_id'], 'FAILURE', 'pay-failure-01', 'Insufficient funds'));

        $payload = $this->webhookPayload($body['conversation_id'], 'tok-failure', 'FAILURE', 'pay-failure-01', 'ref-failure-01');
        $this->postJson('/api/webhooks/iyzico', $payload, ['X-IYZ-SIGNATURE-V3' => $this->signature($payload)])->assertOk();

        $row = DB::table('payment_transactions')->where('id', $body['id'])->first();
        self::assertSame('failed', $row->state);
        self::assertSame('Insufficient funds', $row->failure_reason);
        self::assertSame(0, DB::table('subscriptions')->where('workspace_id', $workspaceId)->count());
        self::assertSame(0, DB::table('ledger_entries')->where('workspace_id', $workspaceId)->count());

        $status = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->checkoutUri($workspaceId))->assertOk()->json();
        self::assertSame('failed', $status['latest']['state']);
        self::assertSame('Insufficient funds', $status['latest']['failure_reason'], 'Panel nedeni ADIYLA gösterir.');

        // Tekrar deneme: yeni anahtar, yeni işlem.
        $this->expectInitialize($fake, 'tok-failure-retry');
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(202);
        self::assertSame(2, DB::table('payment_transactions')->where('workspace_id', $workspaceId)->count());
    }

    // --- SELF-SERVE-IDEMPOTENCY-01 -----------------------------------------

    #[Test]
    public function replaying_the_same_idempotency_key_returns_the_same_transaction_without_a_second_row_or_provider_call(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('replay');
        $this->expectInitialize($this->bindFake(self::SANDBOX_GATEWAY), 'tok-replay');
        $key = $this->uuid();

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId, ['idempotency_key' => $key]))
            ->assertStatus(202)->json();
        $second = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId, ['idempotency_key' => $key]))
            ->assertStatus(202)->json();

        self::assertSame($first['conversation_id'], $second['conversation_id']);
        self::assertSame(1, DB::table('payment_transactions')->count());

        // Aynı anahtar, BAŞKA plan: çatışma.
        $other = $this->insertPlanRow(['name' => 'Other']);
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($other, ['idempotency_key' => $key]))
            ->assertStatus(409);
    }

    // --- SELF-SERVE-UNAVAILABLE-01 -----------------------------------------

    #[Test]
    public function a_provider_that_cannot_be_reached_leaves_a_failed_attempt_and_returns_503(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId, 'planId' => $planId] = $this->readyWorkspace('unavailable');
        $this->bindFake(self::SANDBOX_GATEWAY)->shouldReceive('initializeCheckout')->once()
            ->andThrow(new PaymentGatewayUnavailableException('Iyzico sandbox provider is not configured.'));

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->checkoutUri($workspaceId), $this->checkoutBody($planId))
            ->assertStatus(503);

        $row = DB::table('payment_transactions')->first();
        self::assertSame('failed', $row->state, 'Sağlayıcıya ulaşılamayan bir deneme sonsuza dek "ayrılmış" kalmaz; başarısız yazılır ve yeni anahtarla tekrar denenir.');
        self::assertNotNull($row->failure_reason);
    }
}
