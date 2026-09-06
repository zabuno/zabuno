<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

use App\Domain\Billing\BillingMode;

/**
 * Kip anahtarının okunabilir durumu: ne istendi, ne geçerli, neden.
 *
 * `reasons` boşsa istenen kip geçerlidir; doluysa etkin kip sandbox'tır ve
 * her satır kapalı bir kapının adıdır (`deployment_mode_sandbox`,
 * `vault_missing`). Panel bunu ADIYLA gösterir; "bir şeyler eksik" demez.
 */
final readonly class BillingModeStatus
{
    /** @param list<string> $reasons */
    public function __construct(
        public BillingMode $requested,
        public BillingMode $effective,
        public BillingMode $deploymentMode,
        public bool $vaultConfigured,
        public array $reasons,
        public ?string $updatedAt,
        public ?int $updatedByUserId,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'requested' => $this->requested->value,
            'effective' => $this->effective->value,
            'deployment_mode' => $this->deploymentMode->value,
            'vault_configured' => $this->vaultConfigured,
            'reasons' => $this->reasons,
            'updated_at' => $this->updatedAt,
            'updated_by_user_id' => $this->updatedByUserId,
        ];
    }
}
