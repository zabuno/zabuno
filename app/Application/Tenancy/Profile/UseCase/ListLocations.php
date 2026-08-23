<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\UseCase;

use App\Application\Tenancy\Profile\Dto\LocationProfile;
use App\Application\Tenancy\Profile\Port\LocationRepositoryPort;

final class ListLocations
{
    public function __construct(
        private readonly LocationRepositoryPort $locations,
    ) {}

    /**
     * @return list<LocationProfile>
     */
    public function handle(int $workspaceId, int $brandId): array
    {
        return $this->locations->listByWorkspaceAndBrand($workspaceId, $brandId);
    }
}
