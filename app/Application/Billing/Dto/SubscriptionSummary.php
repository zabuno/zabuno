<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

final class SubscriptionSummary
{
    private function __construct(
        public readonly string $state,
        public readonly ?int $planId,
        public readonly ?string $planCode,
        public readonly ?string $planName,
        public readonly ?int $planVersion,
        public readonly ?string $endsAt,
    ) {}

    public static function none(): self
    {
        return new self('none', null, null, null, null, null);
    }

    public static function active(int $planId, string $planCode, string $planName, int $planVersion, string $endsAtIso): self
    {
        return new self('active', $planId, $planCode, $planName, $planVersion, $endsAtIso);
    }

    /**
     * @return array{state: string, plan_id?: int, plan_code?: string, plan_name?: string, plan_version?: int, ends_at?: string}
     */
    public function toArray(): array
    {
        if ($this->state === 'none') {
            return ['state' => 'none'];
        }

        return [
            'state' => $this->state,
            'plan_id' => $this->planId,
            'plan_code' => $this->planCode,
            'plan_name' => $this->planName,
            'plan_version' => $this->planVersion,
            'ends_at' => $this->endsAt,
        ];
    }
}
