<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Dto\PlanSummary;
use App\Application\Billing\Port\PlanCatalogRepositoryPort;

final class ListPlanCatalog
{
    public function __construct(
        private readonly PlanCatalogRepositoryPort $plans,
    ) {}

    /**
     * @return list<PlanSummary>
     */
    public function handle(): array
    {
        return $this->plans->listActivePlans();
    }
}
