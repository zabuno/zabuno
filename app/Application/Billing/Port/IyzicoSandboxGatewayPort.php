<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

interface IyzicoSandboxGatewayPort
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
    ): array;

    /**
     * @return array{signature_valid: bool, status: string, conversation_id: string, amount_minor: int, currency: string, payment_id: string}
     */
    public function retrieveCheckout(string $token, string $conversationId): array;
}
