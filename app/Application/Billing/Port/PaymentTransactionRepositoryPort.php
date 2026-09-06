<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\PaymentTransaction;
use App\Domain\Billing\BillingMode;

interface PaymentTransactionRepositoryPort
{
    /**
     * Satın alınabilir plan: aktif VE fiyatlı. Tutar BURADAN okunur, istemciden değil.
     *
     * @return array{plan_id: int, amount_minor: int, currency: string}|null
     */
    public function purchasablePlan(int $planId): ?array;

    /** Panelin gördüğü son girişim — `reserved` hariç. */
    public function latestFor(int $workspaceId): ?PaymentTransaction;

    public function findByIdempotencyKey(string $idempotencyKey): ?PaymentTransaction;

    public function findByConversationId(string $conversationId): ?PaymentTransaction;

    public function findByToken(string $token): ?PaymentTransaction;

    public function findForWorkspace(int $workspaceId, int $id): ?PaymentTransaction;

    /**
     * Sağlayıcı çağrılmadan ÖNCE atomik rezervasyon. true = bu çağrı
     * kazandı ve sağlayıcıyı çağırmalı; false = anahtar zaten alınmış.
     */
    public function claim(
        int $workspaceId,
        int $actorUserId,
        int $planId,
        BillingMode $mode,
        string $idempotencyKey,
        string $conversationId,
        int $amountMinor,
        string $currency,
        int $periodDays,
    ): bool;

    public function complete(string $idempotencyKey, string $token, string $redirectUrl): PaymentTransaction;

    /** Koşullu geçiş: yalnız uç olmayan bir durumdan. Geçiş olduysa true. */
    public function markSucceeded(int $id, ?string $referenceCode, string $paymentId, ?string $paymentTransactionId, string $token): bool;

    public function markFailed(int $id, ?string $referenceCode, ?string $paymentId, ?string $token, ?string $failureReason): bool;

    public function recordSubscriptionWindow(int $id, ?string $before, ?string $after): void;

    public function markRefunded(int $id, string $reason, int $byUserId): bool;
}
