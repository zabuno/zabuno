<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Dto\ManagedPlan;
use App\Application\Billing\Exception\PlanVersionConflictException;
use App\Application\Billing\Port\PlanManagementRepositoryPort;

final class ManagePlanCatalog
{
    public function __construct(
        private readonly PlanManagementRepositoryPort $plans,
    ) {}

    /**
     * @return list<ManagedPlan>
     */
    public function listAllVersions(): array
    {
        return $this->plans->listAllVersions();
    }

    /**
     * @param  list<string>  $entitlements
     *
     * @throws PlanVersionConflictException
     */
    public function create(
        string $name,
        string $code,
        int $version,
        array $entitlements,
        ?int $amountMinor,
        ?string $currency,
        int $sortOrder,
    ): ManagedPlan {
        return $this->plans->createInactive($name, $code, $version, $entitlements, $amountMinor, $currency, $sortOrder);
    }

    public function activate(int $planId): ?ManagedPlan
    {
        return $this->plans->activate($planId);
    }
}
