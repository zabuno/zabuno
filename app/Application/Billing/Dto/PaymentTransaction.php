<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

/**
 * Bir ödeme girişimi — başlatıldığı kipi, tutarı ve vardığı yeri taşır.
 *
 * `toArray()` istemciye giden biçimdir ve sağlayıcı jetonu ile idempotency
 * anahtarını BİLEREK dışarıda bırakır: ikisi de yalnız sunucuyla sağlayıcı
 * arasındaki konuşmanın parçasıdır.
 */
final readonly class PaymentTransaction
{
    public const TERMINAL_STATES = ['succeeded', 'failed', 'refunded'];

    public function __construct(
        public int $id,
        public int $workspaceId,
        public int $actorUserId,
        public int $planId,
        public string $mode,
        public string $conversationId,
        public ?string $token,
        public ?string $redirectUrl,
        public int $amountMinor,
        public string $currency,
        public int $periodDays,
        public string $state,
        public ?string $referenceCode,
        public ?string $paymentId,
        public ?string $paymentTransactionId,
        public ?string $failureReason,
        public ?string $refundReason,
        public ?int $refundedByUserId,
        public ?string $refundedAt,
        public ?string $createdAt,
    ) {}

    public function isTerminal(): bool
    {
        return in_array($this->state, self::TERMINAL_STATES, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state,
            'mode' => $this->mode,
            'plan_id' => $this->planId,
            'conversation_id' => $this->conversationId,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
            'period_days' => $this->periodDays,
            'redirect_url' => $this->redirectUrl,
            'failure_reason' => $this->failureReason,
            'refund_reason' => $this->refundReason,
            'refunded_at' => $this->refundedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
