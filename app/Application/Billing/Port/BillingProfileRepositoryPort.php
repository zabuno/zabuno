<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\BillingProfile;

interface BillingProfileRepositoryPort
{
    public function find(int $workspaceId): ?BillingProfile;

    public function save(int $workspaceId, BillingProfile $profile): BillingProfile;
}
