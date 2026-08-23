<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\PlanSummary;

interface PlanCatalogRepositoryPort
{
    /**
     * @return list<PlanSummary>
     */
    public function listActivePlans(): array;
}
