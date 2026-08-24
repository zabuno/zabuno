<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\IyzicoSandboxSession;

interface IyzicoSandboxTransactionRepositoryPort
{
    public function sessionState(int $workspaceId): IyzicoSandboxSession;

    /**
     * @return array{plan_id: int, amount_minor: int, currency: string}|null
     */
    public function activeSubscriptionPlan(int $workspaceId): ?array;

    /**
     * @return array{id: int, workspace_id: int, conversation_id: string, amount_minor: int, currency: string, redirect_url: ?string, state: string}|null
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?array;

    /**
     * Atomically inserts a non-authoritative reservation for this
     * idempotency key before the provider is ever called. Returns true when
     * this call won the race (the caller must call the provider next and
     * then completeInitialization()); returns false when another concurrent
     * caller already reserved this key (the caller must never call the
     * provider and must resolve the winner via findByIdempotencyKey()).
     */
    public function claimInitialization(
        int $workspaceId,
        int $actorUserId,
        int $planId,
        string $idempotencyKey,
        string $conversationId,
        int $amountMinor,
        string $currency,
    ): bool;

    /**
     * Promotes a previously claimed reservation to an authoritative
     * "initiated" session after a trusted (signature_valid) provider result.
     * Only the race winner calls this.
     */
    public function completeInitialization(
        string $idempotencyKey,
        string $token,
        string $redirectUrl,
    ): IyzicoSandboxSession;

    /**
     * @return array{id: int, workspace_id: int, conversation_id: string, token: ?string, amount_minor: int, currency: string, state: string, reference_code: ?string}|null
     */
    public function findByConversationId(string $conversationId): ?array;

    /**
     * @return array{id: int, workspace_id: int, conversation_id: string, token: string, amount_minor: int, currency: string, state: string}|null
     */
    public function findByToken(string $token): ?array;

    public function transitionToTerminal(
        int $id,
        string $state,
        ?string $referenceCode,
        string $paymentId,
        string $token,
    ): void;
}
