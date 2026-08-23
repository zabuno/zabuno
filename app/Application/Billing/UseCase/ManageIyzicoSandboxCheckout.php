<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Dto\IyzicoSandboxSession;
use App\Application\Billing\Exception\IyzicoSandboxBadGatewayException;
use App\Application\Billing\Exception\IyzicoSandboxConflictException;
use App\Application\Billing\Exception\IyzicoSandboxUnavailableException;
use App\Application\Billing\Port\IyzicoSandboxGatewayPort;
use App\Application\Billing\Port\IyzicoSandboxTransactionRepositoryPort;
use Illuminate\Support\Str;

final class ManageIyzicoSandboxCheckout
{
    public function __construct(
        private readonly IyzicoSandboxTransactionRepositoryPort $transactions,
        private readonly IyzicoSandboxGatewayPort $gateway,
    ) {}

    public function currentState(int $workspaceId): IyzicoSandboxSession
    {
        return $this->transactions->sessionState($workspaceId);
    }

    /**
     * @throws IyzicoSandboxConflictException
     * @throws IyzicoSandboxUnavailableException
     * @throws IyzicoSandboxBadGatewayException
     */
    public function checkout(int $workspaceId, int $actorUserId, string $idempotencyKey): IyzicoSandboxSession
    {
        $plan = $this->transactions->activeSubscriptionPlan($workspaceId);

        if ($plan === null) {
            throw new IyzicoSandboxConflictException('Workspace has no active subscription pinned to an active plan.');
        }

        if (
            ! is_string(config('services.iyzico.sandbox.api_key')) || config('services.iyzico.sandbox.api_key') === ''
            || ! is_string(config('services.iyzico.sandbox.secret_key')) || config('services.iyzico.sandbox.secret_key') === ''
            || ! is_string(config('services.iyzico.sandbox.base_url')) || config('services.iyzico.sandbox.base_url') === ''
        ) {
            throw new IyzicoSandboxUnavailableException('Iyzico sandbox provider is not configured.');
        }

        $conversationId = (string) Str::uuid();

        $claimed = $this->transactions->claimInitialization(
            $workspaceId,
            $actorUserId,
            $plan['plan_id'],
            $idempotencyKey,
            $conversationId,
            $plan['amount_minor'],
            $plan['currency'],
        );

        if (! $claimed) {
            $raced = $this->transactions->findByIdempotencyKey($idempotencyKey);

            if ($raced !== null && $raced['workspace_id'] === $workspaceId) {
                return new IyzicoSandboxSession(
                    $raced['state'],
                    $raced['conversation_id'],
                    $raced['amount_minor'],
                    $raced['currency'],
                    $raced['redirect_url'],
                );
            }

            throw new IyzicoSandboxConflictException('idempotency_key already used by another workspace.');
        }

        $result = $this->gateway->initializeCheckout(
            $conversationId,
            $workspaceId,
            $actorUserId,
            $plan['amount_minor'],
            $plan['currency'],
        );

        if (
            ($result['signature_valid'] ?? false) !== true
            || ($result['conversation_id'] ?? null) !== $conversationId
            || ! is_string($result['token'] ?? null) || $result['token'] === ''
            || ! is_string($result['redirect_url'] ?? null) || ! str_starts_with($result['redirect_url'], 'https://')
        ) {
            throw new IyzicoSandboxBadGatewayException('Iyzico sandbox provider returned an untrusted checkout initialization result.');
        }

        return $this->transactions->completeInitialization(
            $idempotencyKey,
            $result['token'],
            $result['redirect_url'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return int HTTP status code the caller should respond with.
     */
    public function receiveWebhook(array $payload, ?string $signature): int
    {
        foreach (['iyziEventType', 'iyziPaymentId', 'token', 'paymentConversationId', 'status', 'iyziReferenceCode'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field]) || $payload[$field] === '') {
                return 400;
            }
        }

        $secret = config('services.iyzico.sandbox.secret_key');

        if (! is_string($secret) || $secret === '' || ! is_string($signature) || $signature === '') {
            return 400;
        }

        $expected = hash_hmac(
            'sha256',
            $secret.$payload['iyziEventType'].$payload['iyziPaymentId'].$payload['token'].$payload['paymentConversationId'].$payload['status'],
            $secret,
        );

        if (! hash_equals($expected, strtolower($signature))) {
            return 400;
        }

        if ($payload['iyziEventType'] !== 'CHECKOUT_FORM_AUTH' || ! in_array($payload['status'], ['SUCCESS', 'FAILURE'], true)) {
            return 400;
        }

        $transaction = $this->transactions->findByConversationId($payload['paymentConversationId']);

        if ($transaction === null) {
            return 404;
        }

        if ($transaction['reference_code'] === $payload['iyziReferenceCode']) {
            return 200;
        }

        if (
            ! is_string($transaction['token'] ?? null) || $transaction['token'] === ''
            || ! hash_equals($transaction['token'], $payload['token'])
        ) {
            return 422;
        }

        $result = $this->gateway->retrieveCheckout($payload['token'], $payload['paymentConversationId']);

        if (
            ($result['signature_valid'] ?? false) !== true
            || ($result['conversation_id'] ?? null) !== $payload['paymentConversationId']
            || ($result['amount_minor'] ?? null) !== $transaction['amount_minor']
            || ($result['currency'] ?? null) !== $transaction['currency']
        ) {
            return 422;
        }

        $state = match ($result['status'] ?? null) {
            'SUCCESS' => 'succeeded',
            'FAILURE' => 'failed',
            default => null,
        };

        if ($state === null) {
            return 422;
        }

        $this->transactions->transitionToTerminal(
            $transaction['id'],
            $state,
            $payload['iyziReferenceCode'],
            (string) ($result['payment_id'] ?? ''),
            $payload['token'],
        );

        return 200;
    }

    /**
     * Handles the hosted-browser Checkout Form callback: resolves the
     * transaction from the provider-supplied token itself (the buyer's
     * browser carries no workspace-scoped API session), retrieves/verifies
     * a nonterminal transaction, and conditionally transitions it once. An
     * unknown/missing token or an already-terminal transaction is a no-op —
     * the caller always redirects safely regardless of the outcome here.
     */
    public function receiveCallback(?string $token): void
    {
        if (! is_string($token) || $token === '') {
            return;
        }

        $transaction = $this->transactions->findByToken($token);

        if ($transaction === null) {
            return;
        }

        if (in_array($transaction['state'], ['succeeded', 'failed'], true)) {
            return;
        }

        $result = $this->gateway->retrieveCheckout($token, $transaction['conversation_id']);

        if (
            ($result['signature_valid'] ?? false) !== true
            || ($result['conversation_id'] ?? null) !== $transaction['conversation_id']
            || ($result['amount_minor'] ?? null) !== $transaction['amount_minor']
            || ($result['currency'] ?? null) !== $transaction['currency']
        ) {
            return;
        }

        $state = match ($result['status'] ?? null) {
            'SUCCESS' => 'succeeded',
            'FAILURE' => 'failed',
            default => null,
        };

        if ($state === null) {
            return;
        }

        $this->transactions->transitionToTerminal(
            $transaction['id'],
            $state,
            null,
            (string) ($result['payment_id'] ?? ''),
            $token,
        );
    }
}
