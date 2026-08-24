<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

final class PlanSummary
{
    /**
     * @param  list<string>  $entitlements
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly array $entitlements,
        public readonly ?int $amountMinor,
        public readonly ?string $currency,
    ) {}

    /**
     * @return array{id: int, name: string, code: string, entitlements: list<string>, amount_minor: ?int, currency: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'entitlements' => $this->entitlements,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
        ];
    }
}
