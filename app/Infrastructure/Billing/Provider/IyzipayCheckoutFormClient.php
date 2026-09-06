<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Provider;

use App\Application\Billing\Exception\PaymentGatewayBadGatewayException;
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
use Iyzipay\Model\Refund;
use Iyzipay\Model\RefundReason;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\CreateRefundRequest;
use Iyzipay\Request\RetrieveCheckoutFormRequest;
use Throwable;

/**
 * Iyzipay Checkout Form + Refund — kipten bağımsız SDK konuşması.
 *
 * Canlı geçit ile yeni akışın sandbox geçidi aynı isteği kurar; farkları
 * yalnız `Options` (adres + anahtar), geri dönüş adresi ve alıcı. O yüzden
 * SDK mantığı tek yerde durur ve iki geçit ona yalnız kimlik verir.
 *
 * Eski `IyzipaySandboxGateway` bu sınıfı KULLANMAZ: o yüzey dondurulmuş
 * (docs/123) ve testleri onun kendi gövdesini kilitler. Buradaki imza
 * formülleri onunla birebir aynıdır — HPP sözleşmesi tek.
 *
 * Alıcı dizisi anahtarları: id, name, surname, identity_number, email,
 * registration_address, city, country, zip_code (boş olabilir), ip,
 * contact_name, address, gsm (boş olabilir).
 */
final class IyzipayCheckoutFormClient
{
    /**
     * @param  array<string, string>  $buyer
     * @return array{signature_valid: bool, token: string, conversation_id: string, redirect_url: string}
     */
    public function initialize(
        Options $options,
        string $callbackUrl,
        string $conversationId,
        int $amountMinor,
        string $currency,
        array $buyer,
    ): array {
        $price = $this->formatMinorAsPrice($amountMinor, $currency);

        $request = new CreateCheckoutFormInitializeRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($conversationId);
        $request->setPrice($price);
        $request->setPaidPrice($price);
        $request->setCurrency($this->mapCurrency($currency));
        $request->setBasketId($conversationId);
        $request->setPaymentGroup(PaymentGroup::SUBSCRIPTION);
        $request->setCallbackUrl($callbackUrl);
        $request->setEnabledInstallments([1]);

        $buyerModel = new Buyer;
        $buyerModel->setId($buyer['id']);
        $buyerModel->setName($buyer['name']);
        $buyerModel->setSurname($buyer['surname']);
        $buyerModel->setIdentityNumber($buyer['identity_number']);
        $buyerModel->setEmail($buyer['email']);
        $buyerModel->setRegistrationAddress($buyer['registration_address']);
        $buyerModel->setCity($buyer['city']);
        $buyerModel->setCountry($buyer['country']);
        if (($buyer['zip_code'] ?? '') !== '') {
            $buyerModel->setZipCode($buyer['zip_code']);
        }
        if (($buyer['gsm'] ?? '') !== '') {
            $buyerModel->setGsmNumber($buyer['gsm']);
        }
        $buyerModel->setIp($buyer['ip']);
        $request->setBuyer($buyerModel);

        // Sanal bir hizmette fatura ve teslimat adresi aynı adrestir.
        foreach (['setBillingAddress', 'setShippingAddress'] as $setter) {
            $address = new Address;
            $address->setContactName($buyer['contact_name']);
            $address->setCity($buyer['city']);
            $address->setCountry($buyer['country']);
            $address->setAddress($buyer['address']);
            if (($buyer['zip_code'] ?? '') !== '') {
                $address->setZipCode($buyer['zip_code']);
            }
            $request->{$setter}($address);
        }

        $basketItem = new BasketItem;
        $basketItem->setId('subscription-plan');
        $basketItem->setName('Zabuno subscription');
        $basketItem->setCategory1('Subscription');
        $basketItem->setItemType(BasketItemType::VIRTUAL);
        $basketItem->setPrice($price);
        $request->setBasketItems([$basketItem]);

        try {
            $result = CheckoutFormInitialize::create($request, $options);
        } catch (Throwable $exception) {
            throw new PaymentGatewayBadGatewayException('Iyzico checkout initialization failed.', 0, $exception);
        }

        $token = (string) $result->getToken();
        $resultConversationId = (string) $result->getConversationId();
        $signature = (string) $result->getSignature();

        $expected = hash_hmac('sha256', $resultConversationId.':'.$token, $options->getSecretKey());

        return [
            'signature_valid' => hash_equals($expected, strtolower($signature)),
            'token' => $token,
            'conversation_id' => $resultConversationId,
            'redirect_url' => (string) $result->getPaymentPageUrl(),
        ];
    }

    /**
     * @return array{signature_valid: bool, status: string, conversation_id: string, amount_minor: int, currency: string, payment_id: string, payment_transaction_id: ?string, error_message: ?string}
     */
    public function retrieve(Options $options, string $token, string $conversationId): array
    {
        $request = new RetrieveCheckoutFormRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($conversationId);
        $request->setToken($token);

        try {
            $result = CheckoutForm::retrieve($request, $options);
        } catch (Throwable $exception) {
            throw new PaymentGatewayBadGatewayException('Iyzico checkout retrieval failed.', 0, $exception);
        }

        $paymentStatus = (string) $result->getPaymentStatus();
        $paymentId = (string) $result->getPaymentId();
        $resultToken = (string) $result->getToken();
        $resultConversationId = (string) $result->getConversationId();
        $currency = (string) $result->getCurrency();
        $basketId = (string) $result->getBasketId();
        $paidPrice = (string) $result->getPaidPrice();
        $price = (string) $result->getPrice();
        $signature = (string) $result->getSignature();

        $expected = hash_hmac(
            'sha256',
            $paymentStatus.':'.$paymentId.':'.$currency.':'.$basketId.':'.$resultConversationId.':'.$paidPrice.':'.$price.':'.$resultToken,
            $options->getSecretKey(),
        );

        $paymentTransactionId = null;
        $items = $result->getPaymentItems();

        if (is_array($items) && $items !== []) {
            $first = reset($items);
            $candidate = is_object($first) ? $first->getPaymentTransactionId() : null;
            $paymentTransactionId = is_string($candidate) && $candidate !== '' ? $candidate : null;
        }

        $errorMessage = $result->getErrorMessage();

        return [
            'signature_valid' => hash_equals($expected, strtolower($signature)),
            'status' => $paymentStatus,
            'conversation_id' => $resultConversationId,
            'amount_minor' => $paidPrice !== '' && $currency !== '' ? $this->parsePriceToMinor($paidPrice, $currency) : 0,
            'currency' => $currency,
            'payment_id' => $paymentId,
            'payment_transaction_id' => $paymentTransactionId,
            'error_message' => is_string($errorMessage) && $errorMessage !== '' ? $errorMessage : null,
        ];
    }

    /**
     * Sağlayıcının kendi iade sebebi sabit `buyer_request`tir; süperadminin
     * yazdığı serbest metin `description` alanına gider — Iyzico'nun
     * dört sabit sebebi bizim gerekçemizin yerine geçemez.
     *
     * @return array{status: string, payment_id: string, conversation_id: string, amount_minor: int, currency: string, error_message: ?string}
     */
    public function refund(
        Options $options,
        string $conversationId,
        string $paymentTransactionId,
        int $amountMinor,
        string $currency,
        string $reason,
        string $ip,
    ): array {
        $request = new CreateRefundRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($conversationId);
        $request->setPaymentTransactionId($paymentTransactionId);
        $request->setPrice($this->formatMinorAsPrice($amountMinor, $currency));
        $request->setCurrency($this->mapCurrency($currency));
        $request->setIp($ip);
        $request->setReason(RefundReason::BUYER_REQUEST);
        $request->setDescription(mb_substr($reason, 0, 250));

        try {
            $result = Refund::create($request, $options);
        } catch (Throwable $exception) {
            throw new PaymentGatewayBadGatewayException('Iyzico refund request failed.', 0, $exception);
        }

        $price = (string) $result->getPrice();
        $resultCurrency = (string) $result->getCurrency();
        $errorMessage = $result->getErrorMessage();

        return [
            'status' => (string) $result->getStatus(),
            'payment_id' => (string) $result->getPaymentId(),
            'conversation_id' => (string) $result->getConversationId(),
            'amount_minor' => $price !== '' ? $this->parsePriceToMinor($price, $resultCurrency !== '' ? $resultCurrency : $currency) : $amountMinor,
            'currency' => $resultCurrency !== '' ? $resultCurrency : $currency,
            'error_message' => is_string($errorMessage) && $errorMessage !== '' ? $errorMessage : null,
        ];
    }

    public function mapCurrency(string $currency): string
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
