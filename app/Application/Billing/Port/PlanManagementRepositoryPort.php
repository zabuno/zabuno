<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\ManagedPlan;
use App\Application\Billing\Exception\PlanVersionConflictException;

interface PlanManagementRepositoryPort
{
    /**
     * @return list<ManagedPlan>
     */
    public function listAllVersions(): array;

    /**
     * @param  list<string>  $entitlements
     *
     * @throws PlanVersionConflictException
     */
    public function createInactive(
        string $name,
        string $code,
        int $version,
        array $entitlements,
        ?int $amountMinor,
        ?string $currency,
        int $sortOrder,
    ): ManagedPlan;

    public function activate(int $planId): ?ManagedPlan;
}
