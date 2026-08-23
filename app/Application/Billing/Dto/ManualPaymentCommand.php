<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

final class ManualPaymentCommand
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $actorUserId,
        public readonly int $planId,
        public readonly string $endsAt,
        public readonly string $paymentNote,
        public readonly string $documentReference,
        public readonly string $idempotencyKey,
    ) {}
}
