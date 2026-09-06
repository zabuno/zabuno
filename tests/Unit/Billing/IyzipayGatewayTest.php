<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Application\Billing\Dto\BillingProfile;
use App\Application\Billing\Exception\PaymentGatewayUnavailableException;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\ResolvedCredential;
use App\Infrastructure\Billing\Provider\IyzipayGateway;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * IYZ-LIVE-GATEWAY — üretim geçidi.
 *
 * Sandbox geçidiyle aynı düzenek: SDK'nın ağa çıkan statik giriş noktaları
 * (`CheckoutFormInitialize::create`, `CheckoutForm::retrieve`,
 * `Refund::create`) ayrı süreçte alias-mock'lanır; geçidin kendi mantığı
 * gerçekten çalışır, ağ çağrısı hiç olmaz.
 *
 * Dondurulan sözleşme:
 *  - Taban adres TAM OLARAK https://api.iyzipay.com; başka bir şey yok.
 *  - Kimlik bilgisi KASADAN gelir. `.env`'deki sandbox anahtarı kasanın
 *    yedeği olarak çözülürse canlı geçit SDK'ya dokunmadan "kullanılamaz"
 *    der — sandbox anahtarı üretim adresine hiç gönderilmez.
 *  - Alıcı bilgisi verilen fatura profilinden BİREBİR kopyalanır; profil
 *    yoksa SDK çağrılmaz.
 *  - retrieve, ilk sepet kaleminin paymentTransactionId'sini döner (iade
 *    bunu ister).
 *  - refund, Iyzipay Refund isteğini paymentTransactionId + tutar + para
 *    birimi + sebep ile kurar; sonucun status'unu ham döner.
 */
final class IyzipayGatewayTest extends TestCase
{
    private const VAULT_API_KEY = 'vault-live-api-key';

    private const VAULT_SECRET = 'vault-live-secret-key';

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    /** @param array<string, string> $values */
    private function resolver(array $values): CredentialResolverPort
    {
        return new class($values) implements CredentialResolverPort
        {
            /** @param array<string, string> $values */
            public function __construct(private array $values) {}

            public function resolve(CredentialProvider $provider): array
            {
                return $provider === CredentialProvider::Iyzico ? $this->values : [];
            }

            public function resolveFor(int $workspaceId, CredentialProvider $provider, string $purpose = 'interactive'): ResolvedCredential
            {
                return new ResolvedCredential($this->resolve($provider), null);
            }

            public function isConfigured(CredentialProvider $provider): bool
            {
                return $this->resolve($provider) !== [];
            }
        };
    }

    private function envSandboxKeys(): void
    {
        config()->set('services.iyzico.sandbox.api_key', 'env-sandbox-api-key');
        config()->set('services.iyzico.sandbox.secret_key', 'env-sandbox-secret');
        config()->set('services.iyzico.sandbox.base_url', 'https://sandbox-api.iyzipay.com');
        config()->set('app.url', 'https://app.zabuno.test');
    }

    private function profile(): BillingProfile
    {
        return new BillingProfile(
            legalName: 'Kadıköy Kebap Gıda Ltd. Şti.',
            taxNumber: '1234567890',
            taxOffice: 'Kadıköy',
            address: 'Moda Cad. No:1',
            city: 'Istanbul',
            country: 'TR',
            email: 'muhasebe@kadikoykebap.test',
            phone: '+905551112233',
        );
    }

    /** Alıcı IP'si isteğin kendisinden gelir; uydurulmaz. */
    private function gateway(array $vault): IyzipayGateway
    {
        return new IyzipayGateway(
            $this->resolver($vault),
            config(),
            Request::create('/api/workspaces/7/checkout', 'POST', server: ['REMOTE_ADDR' => '203.0.113.9']),
        );
    }

    #[RunInSeparateProcess]
    public function test_initialize_uses_vault_credentials_the_exact_live_base_url_and_copies_the_billing_profile(): void
    {
        $this->envSandboxKeys();
        $alias = Mockery::mock('alias:Iyzipay\Model\CheckoutFormInitialize');
        $captured = [];
        $signature = hash_hmac('sha256', 'conv-live-01:tok-live-01', self::VAULT_SECRET);

        $alias->shouldReceive('create')->once()
            ->withArgs(function ($request, $options) use (&$captured): bool {
                $captured = ['request' => $request, 'options' => $options];

                return true;
            })
            ->andReturnUsing(function () use ($signature): object {
                $result = Mockery::mock();
                $result->shouldReceive('getToken')->andReturn('tok-live-01');
                $result->shouldReceive('getConversationId')->andReturn('conv-live-01');
                $result->shouldReceive('getSignature')->andReturn($signature);
                $result->shouldReceive('getPaymentPageUrl')->andReturn('https://cpp.iyzipay.com/?token=tok-live-01');

                return $result;
            });

        $result = $this->gateway(['api_key' => self::VAULT_API_KEY, 'secret_key' => self::VAULT_SECRET])
            ->initializeCheckout('conv-live-01', 7, 3, 149900, 'TRY', $this->profile());

        self::assertTrue($result['signature_valid']);
        self::assertSame('https://api.iyzipay.com', $captured['options']->getBaseUrl(), 'Canlı geçit yalnız üretim adresine konuşur.');
        self::assertSame(self::VAULT_API_KEY, $captured['options']->getApiKey(), 'Anahtar KASADAN, env\'den değil.');
        self::assertSame(self::VAULT_SECRET, $captured['options']->getSecretKey());

        $request = $captured['request'];
        self::assertSame('https://app.zabuno.test/api/billing/iyzico/callback', $request->getCallbackUrl());
        self::assertSame('1499.00', $request->getPrice());
        self::assertSame('Kadıköy Kebap Gıda Ltd. Şti.', $request->getBuyer()->getName());
        self::assertSame('muhasebe@kadikoykebap.test', $request->getBuyer()->getEmail());
        self::assertSame('1234567890', $request->getBuyer()->getIdentityNumber());
        self::assertSame('203.0.113.9', $request->getBuyer()->getIp(), 'IP isteğin kendisinden; sabit bir değer değil.');
        self::assertSame('Moda Cad. No:1', $request->getBillingAddress()->getAddress());
        self::assertSame('Istanbul', $request->getBillingAddress()->getCity());
        self::assertSame('Kadıköy Kebap Gıda Ltd. Şti.', $request->getBillingAddress()->getContactName());
    }

    #[RunInSeparateProcess]
    public function test_initialize_refuses_env_sandbox_keys_before_the_sdk_is_touched(): void
    {
        $this->envSandboxKeys();
        Mockery::mock('alias:Iyzipay\Model\CheckoutFormInitialize')->shouldNotReceive('create');

        // Kasa boş: resolver env yedeğine düştü ve sandbox anahtarını döndü.
        $gateway = $this->gateway(['api_key' => 'env-sandbox-api-key', 'secret_key' => 'env-sandbox-secret', 'base_url' => 'https://sandbox-api.iyzipay.com']);

        $this->expectException(PaymentGatewayUnavailableException::class);
        $gateway->initializeCheckout('conv-env-01', 7, 3, 149900, 'TRY', $this->profile());
    }

    #[RunInSeparateProcess]
    public function test_initialize_refuses_to_run_without_a_billing_profile(): void
    {
        $this->envSandboxKeys();
        Mockery::mock('alias:Iyzipay\Model\CheckoutFormInitialize')->shouldNotReceive('create');

        $this->expectException(PaymentGatewayUnavailableException::class);
        $this->gateway(['api_key' => self::VAULT_API_KEY, 'secret_key' => self::VAULT_SECRET])
            ->initializeCheckout('conv-noprofile-01', 7, 3, 149900, 'TRY', null);
    }

    #[RunInSeparateProcess]
    public function test_retrieve_returns_the_payment_transaction_id_of_the_first_item(): void
    {
        $this->envSandboxKeys();
        $alias = Mockery::mock('alias:Iyzipay\Model\CheckoutForm');
        $signature = hash_hmac('sha256', 'SUCCESS:pay-01:TRY:conv-r-01:conv-r-01:1499.00:1499.00:tok-r-01', self::VAULT_SECRET);

        $item = Mockery::mock();
        $item->shouldReceive('getPaymentTransactionId')->andReturn('paytx-01');

        $result = Mockery::mock();
        $result->shouldReceive('getPaymentStatus')->andReturn('SUCCESS');
        $result->shouldReceive('getPaymentId')->andReturn('pay-01');
        $result->shouldReceive('getToken')->andReturn('tok-r-01');
        $result->shouldReceive('getConversationId')->andReturn('conv-r-01');
        $result->shouldReceive('getCurrency')->andReturn('TRY');
        $result->shouldReceive('getBasketId')->andReturn('conv-r-01');
        $result->shouldReceive('getPaidPrice')->andReturn('1499.00');
        $result->shouldReceive('getPrice')->andReturn('1499.00');
        $result->shouldReceive('getSignature')->andReturn($signature);
        $result->shouldReceive('getPaymentItems')->andReturn([$item]);
        $result->shouldReceive('getErrorMessage')->andReturn(null);
        $alias->shouldReceive('retrieve')->once()->andReturn($result);

        $out = $this->gateway(['api_key' => self::VAULT_API_KEY, 'secret_key' => self::VAULT_SECRET])
            ->retrieveCheckout('tok-r-01', 'conv-r-01');

        self::assertTrue($out['signature_valid']);
        self::assertSame(149900, $out['amount_minor']);
        self::assertSame('paytx-01', $out['payment_transaction_id']);
    }

    #[RunInSeparateProcess]
    public function test_refund_builds_the_iyzipay_refund_request_and_returns_the_raw_status(): void
    {
        $this->envSandboxKeys();
        $alias = Mockery::mock('alias:Iyzipay\Model\Refund');
        $captured = null;

        $alias->shouldReceive('create')->once()
            ->withArgs(function ($request, $options) use (&$captured): bool {
                $captured = $request;
                self::assertSame('https://api.iyzipay.com', $options->getBaseUrl());

                return true;
            })
            ->andReturnUsing(function (): object {
                $result = Mockery::mock();
                $result->shouldReceive('getStatus')->andReturn('success');
                $result->shouldReceive('getPaymentId')->andReturn('pay-01');
                $result->shouldReceive('getConversationId')->andReturn('conv-ref-01');
                $result->shouldReceive('getPrice')->andReturn('1499.00');
                $result->shouldReceive('getCurrency')->andReturn('TRY');
                $result->shouldReceive('getErrorMessage')->andReturn(null);

                return $result;
            });

        $out = $this->gateway(['api_key' => self::VAULT_API_KEY, 'secret_key' => self::VAULT_SECRET])
            ->refund('conv-ref-01', 'paytx-01', 149900, 'TRY', 'Customer asked');

        self::assertSame('paytx-01', $captured->getPaymentTransactionId());
        self::assertSame('1499.00', $captured->getPrice());
        self::assertSame('TRY', $captured->getCurrency());
        self::assertSame('conv-ref-01', $captured->getConversationId());
        self::assertSame('Customer asked', $captured->getDescription());
        self::assertSame('success', $out['status']);
        self::assertSame(149900, $out['amount_minor']);
    }
}
