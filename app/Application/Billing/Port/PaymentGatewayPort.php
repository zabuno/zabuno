<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\BillingProfile;

/**
 * Ödeme geçidinin ortak sözleşmesi — kipten bağımsız.
 *
 * `IyzicoSandboxGatewayPort` (eski sandbox yüzeyi) bu sözleşmenin ÖNCESİDİR ve
 * dondurulmuş durur; buradaki üç metot kendi kendine abonelik yolunun her iki
 * kipte konuştuğu dildir. Kip seçimi `PaymentGatewaySelectorPort`'ta.
 */
interface PaymentGatewayPort
{
    /**
     * @return array{signature_valid: bool, token: string, conversation_id: string, redirect_url: string}
     */
    public function initializeCheckout(
        string $conversationId,
        int $workspaceId,
        int $actorUserId,
        int $amountMinor,
        string $currency,
        ?BillingProfile $buyer = null,
    ): array;

    /**
     * @return array{signature_valid: bool, status: string, conversation_id: string, amount_minor: int, currency: string, payment_id: string, payment_transaction_id: ?string, error_message: ?string}
     */
    public function retrieveCheckout(string $token, string $conversationId): array;

    /**
     * Iyzipay Refund — sağlayıcının İŞLEM kimliğiyle (paymentTransactionId).
     *
     * @return array{status: string, payment_id: string, conversation_id: string, amount_minor: int, currency: string, error_message: ?string}
     */
    public function refund(
        string $conversationId,
        string $paymentTransactionId,
        int $amountMinor,
        string $currency,
        string $reason,
    ): array;
}
