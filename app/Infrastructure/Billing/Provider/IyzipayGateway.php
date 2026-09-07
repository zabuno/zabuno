<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Provider;

use App\Application\Billing\Dto\BillingProfile;
use App\Application\Billing\Exception\PaymentGatewayUnavailableException;
use App\Application\Billing\Port\LivePaymentGatewayPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Platform\Credential\CredentialProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Iyzipay\Options;

/**
 * ÜRETİM geçidi — gerçek para (docs/107 Faz 1.1).
 *
 * Üç sabit karar:
 *  1. Taban adres TAM OLARAK `https://api.iyzipay.com`. Kasadaki `base_url`
 *     alanı bilgi amaçlıdır; canlı geçit onu OKUMAZ. Kip anahtarı adresi
 *     belirler, bir metin alanı değil — "canlı" diye yanlış bir adrese
 *     konuşmanın yolu kapalı.
 *  2. Kimlik bilgisi KASADAN (`CredentialResolverPort`). Resolver kasa boşken
 *     `.env`'in sandbox anahtarına düşer; o anahtar buraya gelirse geçit
 *     SDK'ya dokunmadan "kullanılamaz" der. Sandbox anahtarı üretim
 *     adresine hiçbir koşulda gönderilmez.
 *  3. Alıcı, kiracının FATURA PROFİLİdir; profil yoksa çağrı yok. Şirket
 *     unvanı Iyzico'nun ad ve soyad alanlarının ikisine de yazılır: tüzel
 *     kişinin soyadı yoktur ve uydurulmuş bir soyad, olmayan bir kişiyi
 *     alıcı gösterirdi.
 */
final class IyzipayGateway implements LivePaymentGatewayPort
{
    public const EXACT_BASE_URL = 'https://api.iyzipay.com';

    private const CALLBACK_PATH = '/api/billing/iyzico/callback';

    public function __construct(
        private readonly CredentialResolverPort $credentials,
        private readonly ConfigRepository $config,
        private readonly ?Request $request = null,
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
        if ($buyer === null) {
            throw new PaymentGatewayUnavailableException('Live checkout requires a billing profile; no buyer is fabricated.');
        }

        $options = $this->liveOptions();

        return $this->client->initialize(
            $options,
            rtrim((string) $this->config->get('app.url'), '/').self::CALLBACK_PATH,
            $conversationId,
            $amountMinor,
            $currency,
            [
                'id' => 'workspace-'.$workspaceId,
                'name' => $buyer->legalName,
                'surname' => $buyer->legalName,
                'identity_number' => $buyer->taxNumber,
                'email' => $buyer->email,
                'registration_address' => $buyer->address,
                'city' => $buyer->city,
                'country' => $buyer->country,
                'zip_code' => '',
                'gsm' => $buyer->phone,
                'ip' => $this->clientIp(),
                'contact_name' => $buyer->legalName,
                'address' => $buyer->address,
            ],
        );
    }

    public function retrieveCheckout(string $token, string $conversationId): array
    {
        return $this->client->retrieve($this->liveOptions(), $token, $conversationId);
    }

    public function refund(
        string $conversationId,
        string $paymentTransactionId,
        int $amountMinor,
        string $currency,
        string $reason,
    ): array {
        return $this->client->refund($this->liveOptions(), $conversationId, $paymentTransactionId, $amountMinor, $currency, $reason, $this->clientIp());
    }

    private function liveOptions(): Options
    {
        $resolved = $this->credentials->resolve(CredentialProvider::Iyzico);
        $apiKey = $resolved['api_key'] ?? '';
        $secretKey = $resolved['secret_key'] ?? '';

        if ($apiKey === '' || $secretKey === '') {
            throw new PaymentGatewayUnavailableException('Iyzico live credentials are not configured in the vault.');
        }

        $sandboxApiKey = $this->config->get('services.iyzico.sandbox.api_key');
        $sandboxSecret = $this->config->get('services.iyzico.sandbox.secret_key');

        if ($apiKey === $sandboxApiKey || $secretKey === $sandboxSecret) {
            throw new PaymentGatewayUnavailableException('Iyzico live credentials must come from the vault; the sandbox keys are not accepted for live payments.');
        }

        $options = new Options;
        $options->setApiKey($apiKey);
        $options->setSecretKey($secretKey);
        $options->setBaseUrl(self::EXACT_BASE_URL);

        return $options;
    }

    private function clientIp(): string
    {
        $ip = $this->request?->ip();

        if (! is_string($ip) || $ip === '') {
            throw new PaymentGatewayUnavailableException('Live checkout requires the buyer IP address; no address is fabricated.');
        }

        return $ip;
    }
}
