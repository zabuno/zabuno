<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

final class ManualPaymentOutcome
{
    public function __construct(
        public readonly SubscriptionSummary $subscription,
        public readonly bool $wasReplay,
    ) {}

    /**
     * @return array{state: string, plan_id?: int, plan_code?: string, plan_name?: string, plan_version?: int, ends_at?: string}
     */
    public function toArray(): array
    {
        return $this->subscription->toArray();
    }
}
