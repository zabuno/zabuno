<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Application\Billing\Exception\IyzicoSandboxBadGatewayException;
use App\Application\Billing\Exception\IyzicoSandboxUnavailableException;
use App\Infrastructure\Billing\Provider\IyzipaySandboxGateway;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * RED test candidate exercising the REAL App\Infrastructure\Billing\Provider\
 * IyzipaySandboxGateway (not source-text assertions) against independent
 * reviewer RED findings and MASTER-verified gaps in
 * app/Infrastructure/Billing/Provider/IyzipaySandboxGateway.php.
 *
 * The vendor iyzico/iyzipay-php SDK reaches the network only through the two
 * static entry points Iyzipay\Model\CheckoutFormInitialize::create() and
 * Iyzipay\Model\CheckoutForm::retrieve(). Each test below runs in its own
 * process (#[RunInSeparateProcess]) and, before the real vendor classes are
 * ever autoloaded, installs a Mockery `alias:` mock of exactly those two
 * static entry points. This deterministically replaces the SDK's HTTP call
 * with a scripted response object — the gateway's own signature/parsing
 * logic still runs for real. No network call is ever made: the vendor
 * Curl/DefaultHttpClient classes are never referenced. `alias:` mocks
 * additionally let a `shouldNotReceive()` expectation prove that the gateway
 * built and validated its options *before* attempting to hand credentials to
 * the SDK — a genuine "left the process" boundary check.
 *
 * Frozen provider contracts under test (per MASTER-issued correction
 * instruction, official iyzico HPP signature spec, iyzipay-php 2.0.61):
 *  - initializeCheckout()'s signature input is exactly
 *    "{conversationId}:{token}" (colon-separated), HMAC-SHA256 keyed by the
 *    configured sandbox secretKey.
 *  - retrieveCheckout()'s signature input is exactly
 *    "{paymentStatus}:{paymentId}:{currency}:{basketId}:{conversationId}:
 *    {paidPrice}:{price}:{token}" (colon-separated), using the provider's
 *    raw string fields verbatim — no trailing-zero rewriting/normalization
 *    of paidPrice/price before hashing.
 *  - amount_minor conversion from the provider's decimal price string is
 *    exact-decimal (e.g. Brick\Money-style scaled parsing), never a
 *    (float) cast/round() pipeline.
 *  - Only the exact base URL https://sandbox-api.iyzipay.com is accepted;
 *    a look-alike host (subdomain/suffix trick) must be rejected with
 *    IyzicoSandboxBadGatewayException *before* Options carrying the
 *    api_key/secret_key is ever constructed and handed to the SDK.
 *  - Sandbox buyer/address/IP values must come only from an explicit,
 *    complete sandbox profile configuration (services.iyzico.sandbox.profile);
 *    when that profile is missing, the gateway must fail
 *    IyzicoSandboxUnavailableException before the SDK is ever invoked — no
 *    hard-coded 'Workspace'/'N/A'/'0.0.0.0'/'actor-N@zabuno.invalid' identity
 *    may reach the provider. Conversely, when the profile IS present and
 *    complete, every explicit profile field must be copied verbatim into the
 *    SDK request's Buyer, billing Address, shipping Address and IP fields —
 *    this is asserted behaviorally against the captured SDK request, not by
 *    source-text inspection.
 *
 * Requirement IDs: IYZ-GATEWAY-INIT-SIGNATURE-COLON-01,
 * IYZ-GATEWAY-RETRIEVE-SIGNATURE-COLON-01, IYZ-GATEWAY-BASEURL-EXACT-01,
 * IYZ-GATEWAY-PROFILE-MISSING-01, IYZ-GATEWAY-PROFILE-COPY-01.
 */
final class IyzipaySandboxGatewayTest extends TestCase
{
    private const SECRET = 'unit-gateway-secret-key';

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    private function configureCredentials(string $baseUrl = 'https://sandbox-api.iyzipay.com'): void
    {
        config()->set('services.iyzico.sandbox.api_key', 'unit-gateway-api-key');
        config()->set('services.iyzico.sandbox.secret_key', self::SECRET);
        config()->set('services.iyzico.sandbox.base_url', $baseUrl);
        config()->set('services.iyzico.sandbox.profile', $this->sandboxProfile());
    }

    /**
     * A complete, explicit sandbox buyer/address/IP profile. Deliberately
     * distinctive per-field values (never the forbidden hard-coded
     * "Workspace"/"actor-N@zabuno.invalid"/"N/A"/"0.0.0.0" identity) so a
     * behavioral SDK-request assertion can prove these exact values — and
     * not a synthetic fallback — reached the vendor Buyer/Address objects.
     */
    private function sandboxProfile(): array
    {
        return [
            'buyer' => [
                'id' => 'sandbox-buyer-profile-01',
                'name' => 'Sandbox',
                'surname' => 'ProfileBuyer',
                'email' => 'sandbox-profile-buyer@example.test',
                'identity_number' => '74300864791',
                'registration_address' => 'Sandbox Profile Registration Address No:1',
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'zip_code' => '34732',
                'ip' => '203.0.113.42',
            ],
            'billing_address' => [
                'contact_name' => 'Sandbox ProfileBuyer',
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'address' => 'Sandbox Profile Billing Address No:2',
                'zip_code' => '34732',
            ],
            'shipping_address' => [
                'contact_name' => 'Sandbox ProfileBuyer',
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'address' => 'Sandbox Profile Shipping Address No:3',
                'zip_code' => '34732',
            ],
        ];
    }

    private const FORBIDDEN_SYNTHETIC_VALUES = ['Workspace', 'N/A', '0.0.0.0'];

    private function assertNotForbiddenSyntheticIdentity(?string $value, string $field): void
    {
        self::assertNotNull($value, "IYZ-GATEWAY-PROFILE-COPY-01: {$field} must be populated from the explicit sandbox profile, not left null.");
        self::assertNotContains($value, self::FORBIDDEN_SYNTHETIC_VALUES, "IYZ-GATEWAY-PROFILE-COPY-01: {$field} must never be a hard-coded synthetic value ({$value}).");
        self::assertDoesNotMatchRegularExpression('/^actor-\d+@zabuno\.invalid$/', $value, "IYZ-GATEWAY-PROFILE-COPY-01: {$field} must never be the hard-coded synthetic actor-N@zabuno.invalid identity ({$value}).");
    }

    /**
     * Aliases the two vendor SDK static entry points before they are ever
     * autoloaded by production code in this process, so no real HTTP call
     * can occur regardless of what the gateway does. Returns the two alias
     * mocks so the caller can set shouldReceive()/shouldNotReceive()
     * expectations directly on them (Mockery alias mocks intercept static
     * calls made against the real class name, but expectations must be
     * registered on the mock instance itself, not called statically on the
     * class name string).
     *
     * @return array{0: MockInterface, 1: MockInterface}
     */
    private function aliasSdkEntryPoints(): array
    {
        return [
            Mockery::mock('alias:Iyzipay\Model\CheckoutFormInitialize'),
            Mockery::mock('alias:Iyzipay\Model\CheckoutForm'),
        ];
    }

    private function fakeInitializeResult(string $conversationId, string $token, string $signature, string $paymentPageUrl = 'https://sandbox-cf.iyzipay.com/checkout/unit-01'): object
    {
        $result = Mockery::mock();
        $result->shouldReceive('getToken')->andReturn($token);
        $result->shouldReceive('getConversationId')->andReturn($conversationId);
        $result->shouldReceive('getSignature')->andReturn($signature);
        $result->shouldReceive('getPaymentPageUrl')->andReturn($paymentPageUrl);

        return $result;
    }

    private function fakeRetrieveResult(
        string $paymentStatus,
        string $paymentId,
        string $token,
        string $conversationId,
        string $currency,
        string $basketId,
        string $paidPrice,
        string $price,
        string $signature,
    ): object {
        $result = Mockery::mock();
        $result->shouldReceive('getPaymentStatus')->andReturn($paymentStatus);
        $result->shouldReceive('getPaymentId')->andReturn($paymentId);
        $result->shouldReceive('getToken')->andReturn($token);
        $result->shouldReceive('getConversationId')->andReturn($conversationId);
        $result->shouldReceive('getCurrency')->andReturn($currency);
        $result->shouldReceive('getBasketId')->andReturn($basketId);
        $result->shouldReceive('getPaidPrice')->andReturn($paidPrice);
        $result->shouldReceive('getPrice')->andReturn($price);
        $result->shouldReceive('getSignature')->andReturn($signature);

        return $result;
    }

    // --- IYZ-GATEWAY-INIT-SIGNATURE-COLON-01 --------------------------------

    #[RunInSeparateProcess]
    public function test_initialize_checkout_validates_provider_signature_using_colon_separated_conversation_id_and_token(): void
    {
        $this->configureCredentials();
        [$checkoutFormInitializeAlias] = $this->aliasSdkEntryPoints();

        $conversationId = 'unit-conv-init-01';
        $token = 'unit-token-init-01';
        $officialSignature = hash_hmac('sha256', $conversationId.':'.$token, self::SECRET);

        $checkoutFormInitializeAlias->shouldReceive('create')
            ->once()
            ->andReturn($this->fakeInitializeResult($conversationId, $token, $officialSignature));

        $gateway = new IyzipaySandboxGateway;
        $result = $gateway->initializeCheckout($conversationId, 1, 2, 149900, 'TRY');

        self::assertTrue(
            $result['signature_valid'],
            'IYZ-GATEWAY-INIT-SIGNATURE-COLON-01: initializeCheckout() must verify the official HMAC over "conversationId:token" (colon-separated) — the gateway currently concatenates the fields without a colon, so a genuine provider signature is rejected as invalid.'
        );
    }

    // --- IYZ-GATEWAY-RETRIEVE-SIGNATURE-COLON-01 ----------------------------

    #[RunInSeparateProcess]
    public function test_retrieve_checkout_validates_provider_signature_using_colon_separated_raw_provider_fields_without_trailing_zero_rewriting(): void
    {
        $this->configureCredentials();
        [, $checkoutFormAlias] = $this->aliasSdkEntryPoints();

        $paymentStatus = 'SUCCESS';
        $paymentId = 'unit-payment-01';
        $token = 'unit-token-retrieve-01';
        $conversationId = 'unit-conv-retrieve-01';
        $currency = 'TRY';
        $basketId = 'unit-conv-retrieve-01';
        $paidPrice = '150.00';
        $price = '150.00';

        $officialSignature = hash_hmac(
            'sha256',
            $paymentStatus.':'.$paymentId.':'.$currency.':'.$basketId.':'.$conversationId.':'.$paidPrice.':'.$price.':'.$token,
            self::SECRET,
        );

        $checkoutFormAlias->shouldReceive('retrieve')
            ->once()
            ->andReturn($this->fakeRetrieveResult(
                $paymentStatus,
                $paymentId,
                $token,
                $conversationId,
                $currency,
                $basketId,
                $paidPrice,
                $price,
                $officialSignature,
            ));

        $gateway = new IyzipaySandboxGateway;
        $result = $gateway->retrieveCheckout($token, $conversationId);

        self::assertTrue(
            $result['signature_valid'],
            'IYZ-GATEWAY-RETRIEVE-SIGNATURE-COLON-01: retrieveCheckout() must verify the official HMAC over the exact colon-separated raw provider fields "paymentStatus:paymentId:currency:basketId:conversationId:paidPrice:price:token" — the gateway currently concatenates without colons AND rewrites paidPrice/price (stripping trailing zeros) before hashing, so a genuine provider signature over the raw "150.00" strings is rejected as invalid.'
        );
    }

    // --- IYZ-GATEWAY-BASEURL-EXACT-01 ---------------------------------------

    #[RunInSeparateProcess]
    public function test_initialize_checkout_rejects_a_look_alike_base_url_before_handing_credentials_to_the_sdk(): void
    {
        $this->configureCredentials('https://sandbox-api.iyzipay.com.attacker-controlled.test');
        [$checkoutFormInitializeAlias] = $this->aliasSdkEntryPoints();

        $checkoutFormInitializeAlias->shouldNotReceive('create');

        $gateway = new IyzipaySandboxGateway;

        $this->expectException(IyzicoSandboxBadGatewayException::class);

        try {
            $gateway->initializeCheckout('unit-conv-baseurl-01', 1, 2, 149900, 'TRY');
        } catch (\Throwable $thrown) {
            self::assertInstanceOf(
                IyzicoSandboxBadGatewayException::class,
                $thrown,
                sprintf(
                    'IYZ-GATEWAY-BASEURL-EXACT-01: only the exact https://sandbox-api.iyzipay.com base URL may be accepted before credentials leave the process — a look-alike suffixed host must be rejected with IyzicoSandboxBadGatewayException without ever calling the SDK. Instead the gateway proceeded to call the (mocked, shouldNotReceive) SDK entry point, raising: %s: %s',
                    $thrown::class,
                    $thrown->getMessage(),
                ),
            );

            throw $thrown;
        }
    }

    // --- IYZ-GATEWAY-PROFILE-MISSING-01 --------------------------------------

    #[RunInSeparateProcess]
    public function test_initialize_checkout_fails_unavailable_before_the_sdk_call_when_the_sandbox_buyer_profile_is_not_configured(): void
    {
        $this->configureCredentials();
        config()->set('services.iyzico.sandbox.profile', null);
        [$checkoutFormInitializeAlias] = $this->aliasSdkEntryPoints();

        $checkoutFormInitializeAlias->shouldNotReceive('create');

        $gateway = new IyzipaySandboxGateway;

        $this->expectException(IyzicoSandboxUnavailableException::class);

        try {
            $gateway->initializeCheckout('unit-conv-profile-01', 1, 2, 149900, 'TRY');
        } catch (\Throwable $thrown) {
            self::assertInstanceOf(
                IyzicoSandboxUnavailableException::class,
                $thrown,
                sprintf(
                    'IYZ-GATEWAY-PROFILE-MISSING-01: buyer/address/IP values must come only from an explicit, complete sandbox profile configuration (services.iyzico.sandbox.profile) — with no profile configured the gateway must fail IyzicoSandboxUnavailableException before ever calling the SDK, never fabricate a synthetic identity (e.g. "Workspace", "actor-N@zabuno.invalid", "N/A", "0.0.0.0"). Instead the gateway proceeded to call the (mocked, shouldNotReceive) SDK entry point using hard-coded synthetic values, raising: %s: %s',
                    $thrown::class,
                    $thrown->getMessage(),
                ),
            );

            throw $thrown;
        }
    }

    // --- IYZ-GATEWAY-PROFILE-COPY-01 -----------------------------------------

    #[RunInSeparateProcess]
    public function test_initialize_checkout_copies_explicit_sandbox_profile_into_buyer_and_addresses_and_ip_and_never_uses_synthetic_identity(): void
    {
        $this->configureCredentials();
        [$checkoutFormInitializeAlias] = $this->aliasSdkEntryPoints();

        $profile = $this->sandboxProfile();
        $capturedRequest = null;

        $checkoutFormInitializeAlias->shouldReceive('create')
            ->once()
            ->withArgs(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return true;
            })
            ->andReturn($this->fakeInitializeResult('unit-conv-profilecopy-01', 'unit-token-profilecopy-01', 'unit-signature-profilecopy-01'));

        $gateway = new IyzipaySandboxGateway;
        $gateway->initializeCheckout('unit-conv-profilecopy-01', 1, 2, 149900, 'TRY');

        self::assertNotNull($capturedRequest, 'IYZ-GATEWAY-PROFILE-COPY-01: the SDK request must actually be built and handed to CheckoutFormInitialize::create().');

        $buyer = $capturedRequest->getBuyer();
        $billingAddress = $capturedRequest->getBillingAddress();
        $shippingAddress = $capturedRequest->getShippingAddress();

        self::assertNotNull($buyer, 'IYZ-GATEWAY-PROFILE-COPY-01: request must carry a Buyer built from the explicit sandbox profile.');
        self::assertNotNull($billingAddress, 'IYZ-GATEWAY-PROFILE-COPY-01: request must carry a billing Address built from the explicit sandbox profile.');
        self::assertNotNull($shippingAddress, 'IYZ-GATEWAY-PROFILE-COPY-01: request must carry a shipping Address built from the explicit sandbox profile.');

        self::assertSame($profile['buyer']['name'], $buyer->getName(), 'IYZ-GATEWAY-PROFILE-COPY-01: Buyer name must come from the explicit sandbox profile.');
        self::assertSame($profile['buyer']['surname'], $buyer->getSurname(), 'IYZ-GATEWAY-PROFILE-COPY-01: Buyer surname must come from the explicit sandbox profile.');
        self::assertSame($profile['buyer']['email'], $buyer->getEmail(), 'IYZ-GATEWAY-PROFILE-COPY-01: Buyer email must come from the explicit sandbox profile.');
        self::assertSame($profile['buyer']['registration_address'], $buyer->getRegistrationAddress(), 'IYZ-GATEWAY-PROFILE-COPY-01: Buyer registration address must come from the explicit sandbox profile.');
        self::assertSame($profile['buyer']['ip'], $buyer->getIp(), 'IYZ-GATEWAY-PROFILE-COPY-01: Buyer IP must come from the explicit sandbox profile.');

        self::assertSame($profile['billing_address']['address'], $billingAddress->getAddress(), 'IYZ-GATEWAY-PROFILE-COPY-01: billing Address must come from the explicit sandbox profile.');
        self::assertSame($profile['billing_address']['contact_name'], $billingAddress->getContactName(), 'IYZ-GATEWAY-PROFILE-COPY-01: billing Address contact name must come from the explicit sandbox profile.');

        self::assertSame($profile['shipping_address']['address'], $shippingAddress->getAddress(), 'IYZ-GATEWAY-PROFILE-COPY-01: shipping Address must come from the explicit sandbox profile.');
        self::assertSame($profile['shipping_address']['contact_name'], $shippingAddress->getContactName(), 'IYZ-GATEWAY-PROFILE-COPY-01: shipping Address contact name must come from the explicit sandbox profile.');

        $this->assertNotForbiddenSyntheticIdentity($buyer->getEmail(), 'Buyer email');
        $this->assertNotForbiddenSyntheticIdentity($buyer->getIp(), 'Buyer IP');
        $this->assertNotForbiddenSyntheticIdentity($billingAddress->getContactName(), 'Billing Address contact name');
        $this->assertNotForbiddenSyntheticIdentity($shippingAddress->getContactName(), 'Shipping Address contact name');
    }
}
