<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Provider;

use App\Application\Billing\Dto\BillingProfile;
use App\Application\Billing\Exception\PaymentGatewayBadGatewayException;
use App\Application\Billing\Exception\PaymentGatewayUnavailableException;
use App\Application\Billing\Port\SandboxPaymentGatewayPort;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Iyzipay\Options;

/**
 * Yeni akışın SANDBOX geçidi — para hareket etmez.
 *
 * `IyzipaySandboxGateway` (eski "aktif planı ücretlendir" yüzeyi) OLDUĞU
 * GİBİ durur; bu sınıf kendi kendine abonelik yolunun sandbox karşılığıdır
 * ve iade ile `payment_transaction_id`'yi de bilir.
 *
 * Alıcı `.env`'deki sandbox personasıdır — verilen fatura profili BİLEREK
 * yok sayılır: sandbox Iyzico'nun test kişisiyle konuşur, kiracının
 * gerçek unvanı test ortamına taşınmaz. Profil yine de ZORUNLU (kullanım
 * örneğinde): sandbox, gerçek yolculuğun provası olmalı.
 */
final class IyzipaySandboxModeGateway implements SandboxPaymentGatewayPort
{
    public const EXACT_BASE_URL = 'https://sandbox-api.iyzipay.com';

    private const CALLBACK_PATH = '/api/billing/iyzico/callback';

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly IyzipayCheckoutFormClient $client = new IyzipayCheckoutFormClient,
    ) {}

    public function initializeCheckout(
        string $conversationId,
        int $workspaceId,
        int $actorUserId,
        int $amountMinor,
        string $currency,
        ?BillingProfile $buyer = null,
    ): array {
        $options = $this->sandboxOptions();
        $profile = $this->requireSandboxProfile();

        return $this->client->initialize(
            $options,
            rtrim((string) $this->config->get('app.url'), '/').self::CALLBACK_PATH,
            $conversationId,
            $amountMinor,
            $currency,
            [
                'id' => $profile['buyer']['id'],
                'name' => $profile['buyer']['name'],
                'surname' => $profile['buyer']['surname'],
                'identity_number' => $profile['buyer']['identity_number'],
                'email' => $profile['buyer']['email'],
                'registration_address' => $profile['buyer']['registration_address'],
                'city' => $profile['buyer']['city'],
                'country' => $profile['buyer']['country'],
                'zip_code' => $profile['buyer']['zip_code'],
                'gsm' => '',
                'ip' => $profile['buyer']['ip'],
                'contact_name' => $profile['billing_address']['contact_name'],
                'address' => $profile['billing_address']['address'],
            ],
        );
    }

    public function retrieveCheckout(string $token, string $conversationId): array
    {
        return $this->client->retrieve($this->sandboxOptions(), $token, $conversationId);
    }

    public function refund(
        string $conversationId,
        string $paymentTransactionId,
        int $amountMinor,
        string $currency,
        string $reason,
    ): array {
        return $this->client->refund(
            $this->sandboxOptions(),
            $conversationId,
            $paymentTransactionId,
            $amountMinor,
            $currency,
            $reason,
            $this->requireSandboxProfile()['buyer']['ip'],
        );
    }

    private function sandboxOptions(): Options
    {
        $apiKey = $this->config->get('services.iyzico.sandbox.api_key');
        $secretKey = $this->config->get('services.iyzico.sandbox.secret_key');
        $baseUrl = $this->config->get('services.iyzico.sandbox.base_url');

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($secretKey) || $secretKey === '' || ! is_string($baseUrl) || $baseUrl === '') {
            throw new PaymentGatewayUnavailableException('Iyzico sandbox provider is not configured.');
        }

        if ($baseUrl !== self::EXACT_BASE_URL) {
            throw new PaymentGatewayBadGatewayException('Iyzico sandbox base_url must be exactly '.self::EXACT_BASE_URL.'.');
        }

        $options = new Options;
        $options->setApiKey($apiKey);
        $options->setSecretKey($secretKey);
        $options->setBaseUrl($baseUrl);

        return $options;
    }

    /**
     * @return array{buyer: array<string, string>, billing_address: array<string, string>}
     */
    private function requireSandboxProfile(): array
    {
        $profile = $this->config->get('services.iyzico.sandbox.profile');

        if (! is_array($profile)) {
            throw new PaymentGatewayUnavailableException('Iyzico sandbox buyer profile is not configured.');
        }

        $required = [
            'buyer' => ['id', 'name', 'surname', 'email', 'identity_number', 'registration_address', 'city', 'country', 'zip_code', 'ip'],
            'billing_address' => ['contact_name', 'city', 'country', 'address', 'zip_code'],
        ];

        foreach ($required as $section => $fields) {
            if (! isset($profile[$section]) || ! is_array($profile[$section])) {
                throw new PaymentGatewayUnavailableException('Iyzico sandbox buyer profile is not configured.');
            }

            foreach ($fields as $field) {
                if (! is_string($profile[$section][$field] ?? null) || $profile[$section][$field] === '') {
                    throw new PaymentGatewayUnavailableException('Iyzico sandbox buyer profile is not configured.');
                }
            }
        }

        return $profile;
    }
}
