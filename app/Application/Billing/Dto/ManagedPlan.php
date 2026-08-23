<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

final class ManagedPlan
{
    /**
     * @param  list<string>  $entitlements
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly int $version,
        public readonly array $entitlements,
        public readonly ?int $amountMinor,
        public readonly ?string $currency,
        public readonly int $sortOrder,
        public readonly bool $isActive,
    ) {}

    /**
     * @return array{id: int, name: string, code: string, version: int, entitlements: list<string>, amount_minor: ?int, currency: ?string, sort_order: int, is_active: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'version' => $this->version,
            'entitlements' => $this->entitlements,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];
    }
}
