<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Provider;

use App\Application\Billing\Exception\IyzicoSandboxBadGatewayException;
use App\Application\Billing\Exception\IyzicoSandboxUnavailableException;
use App\Application\Billing\Port\IyzicoSandboxGatewayPort;
use Brick\Money\Money;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Currency;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\RetrieveCheckoutFormRequest;

final class IyzipaySandboxGateway implements IyzicoSandboxGatewayPort
{
    private const EXACT_BASE_URL = 'https://sandbox-api.iyzipay.com';

    public function initializeCheckout(
        string $conversationId,
        int $workspaceId,
        int $actorUserId,
        int $amountMinor,
        string $currency,
    ): array {
        $options = $this->sandboxOptions();
        $profile = $this->requireSandboxProfile();
        $price = $this->formatMinorAsPrice($amountMinor, $currency);

        $request = new CreateCheckoutFormInitializeRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($conversationId);
        $request->setPrice($price);
        $request->setPaidPrice($price);
        $request->setCurrency($this->mapCurrency($currency));
        $request->setBasketId($conversationId);
        $request->setPaymentGroup(PaymentGroup::PRODUCT);
        $request->setCallbackUrl(config('app.url').'/api/billing/iyzico-sandbox/callback');
        $request->setEnabledInstallments([1]);

        $buyer = new Buyer;
        $buyer->setId((string) $profile['buyer']['id']);
        $buyer->setName((string) $profile['buyer']['name']);
        $buyer->setSurname((string) $profile['buyer']['surname']);
        $buyer->setIdentityNumber((string) $profile['buyer']['identity_number']);
        $buyer->setEmail((string) $profile['buyer']['email']);
        $buyer->setRegistrationAddress((string) $profile['buyer']['registration_address']);
        $buyer->setCity((string) $profile['buyer']['city']);
        $buyer->setCountry((string) $profile['buyer']['country']);
        $buyer->setZipCode((string) $profile['buyer']['zip_code']);
        $buyer->setIp((string) $profile['buyer']['ip']);
        $request->setBuyer($buyer);

        $billingAddress = new Address;
        $billingAddress->setContactName((string) $profile['billing_address']['contact_name']);
        $billingAddress->setCity((string) $profile['billing_address']['city']);
        $billingAddress->setCountry((string) $profile['billing_address']['country']);
        $billingAddress->setAddress((string) $profile['billing_address']['address']);
        $billingAddress->setZipCode((string) $profile['billing_address']['zip_code']);
        $request->setBillingAddress($billingAddress);

        $shippingAddress = new Address;
        $shippingAddress->setContactName((string) $profile['shipping_address']['contact_name']);
        $shippingAddress->setCity((string) $profile['shipping_address']['city']);
        $shippingAddress->setCountry((string) $profile['shipping_address']['country']);
        $shippingAddress->setAddress((string) $profile['shipping_address']['address']);
        $shippingAddress->setZipCode((string) $profile['shipping_address']['zip_code']);
        $request->setShippingAddress($shippingAddress);

        $basketItem = new BasketItem;
        $basketItem->setId('subscription-plan');
        $basketItem->setName('Zabuno subscription');
        $basketItem->setCategory1('Subscription');
        $basketItem->setItemType(BasketItemType::VIRTUAL);
        $basketItem->setPrice($price);
        $request->setBasketItems([$basketItem]);

        $result = CheckoutFormInitialize::create($request, $options);

        $token = (string) $result->getToken();
        $resultConversationId = (string) $result->getConversationId();
        $signature = (string) $result->getSignature();

        $expectedSignature = hash_hmac('sha256', $resultConversationId.':'.$token, $options->getSecretKey());
        $signatureValid = hash_equals($expectedSignature, strtolower($signature));

        return [
            'signature_valid' => $signatureValid,
            'token' => $token,
            'conversation_id' => $resultConversationId,
            'redirect_url' => (string) $result->getPaymentPageUrl(),
        ];
    }

    public function retrieveCheckout(string $token, string $conversationId): array
    {
        $options = $this->sandboxOptions();

        $request = new RetrieveCheckoutFormRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($conversationId);
        $request->setToken($token);

        $result = CheckoutForm::retrieve($request, $options);

        $paymentStatus = (string) $result->getPaymentStatus();
        $paymentId = (string) $result->getPaymentId();
        $resultToken = (string) $result->getToken();
        $resultConversationId = (string) $result->getConversationId();
        $currency = (string) $result->getCurrency();
        $basketId = (string) $result->getBasketId();
        $paidPrice = (string) $result->getPaidPrice();
        $price = (string) $result->getPrice();
        $signature = (string) $result->getSignature();

        $expectedSignature = hash_hmac(
            'sha256',
            $paymentStatus.':'.$paymentId.':'.$currency.':'.$basketId.':'.$resultConversationId.':'.$paidPrice.':'.$price.':'.$resultToken,
            $options->getSecretKey(),
        );
        $signatureValid = hash_equals($expectedSignature, strtolower($signature));

        return [
            'signature_valid' => $signatureValid,
            'status' => $paymentStatus,
            'conversation_id' => $resultConversationId,
            'amount_minor' => $this->parsePriceToMinor($paidPrice, $currency),
            'currency' => $currency,
            'payment_id' => $paymentId,
        ];
    }

    private function sandboxOptions(): Options
    {
        $apiKey = config('services.iyzico.sandbox.api_key');
        $secretKey = config('services.iyzico.sandbox.secret_key');
        $baseUrl = config('services.iyzico.sandbox.base_url');

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($secretKey) || $secretKey === '' || ! is_string($baseUrl) || $baseUrl === '') {
            throw new IyzicoSandboxUnavailableException('Iyzico sandbox provider is not configured.');
        }

        if ($baseUrl !== self::EXACT_BASE_URL) {
            throw new IyzicoSandboxBadGatewayException('Iyzico sandbox base_url must be exactly '.self::EXACT_BASE_URL.'.');
        }

        $options = new Options;
        $options->setApiKey($apiKey);
        $options->setSecretKey($secretKey);
        $options->setBaseUrl($baseUrl);

        return $options;
    }

    /**
     * @return array{buyer: array<string, string>, billing_address: array<string, string>, shipping_address: array<string, string>}
     */
    private function requireSandboxProfile(): array
    {
        $profile = config('services.iyzico.sandbox.profile');

        if (! is_array($profile)) {
            throw new IyzicoSandboxUnavailableException('Iyzico sandbox buyer profile is not configured.');
        }

        $requiredFields = [
            'buyer' => ['id', 'name', 'surname', 'email', 'identity_number', 'registration_address', 'city', 'country', 'zip_code', 'ip'],
            'billing_address' => ['contact_name', 'city', 'country', 'address', 'zip_code'],
            'shipping_address' => ['contact_name', 'city', 'country', 'address', 'zip_code'],
        ];

        foreach ($requiredFields as $section => $fields) {
            if (! isset($profile[$section]) || ! is_array($profile[$section])) {
                throw new IyzicoSandboxUnavailableException('Iyzico sandbox buyer profile is not configured.');
            }

            foreach ($fields as $field) {
                $value = $profile[$section][$field] ?? null;

                if (! is_string($value) || $value === '') {
                    throw new IyzicoSandboxUnavailableException('Iyzico sandbox buyer profile is not configured.');
                }
            }
        }

        return $profile;
    }

    private function mapCurrency(string $currency): string
    {
        return match ($currency) {
            'TRY' => Currency::TL,
            'EUR' => Currency::EUR,
            'USD' => Currency::USD,
            'GBP' => Currency::GBP,
            default => $currency,
        };
    }

    private function formatMinorAsPrice(int $amountMinor, string $currency): string
    {
        return Money::ofMinor($amountMinor, $currency)->getAmount()->__toString();
    }

    private function parsePriceToMinor(string $price, string $currency): int
    {
        return Money::of($price, $currency)->getMinorAmount()->toInt();
    }
}
