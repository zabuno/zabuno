<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\UseCase;

use App\Application\Tenancy\Profile\Dto\LocationProfile;
use App\Application\Tenancy\Profile\Port\LocationRepositoryPort;

final class GetLocation
{
    public function __construct(
        private readonly LocationRepositoryPort $locations,
    ) {}

    public function handle(int $workspaceId, int $locationId): ?LocationProfile
    {
        return $this->locations->findByWorkspaceAndId($workspaceId, $locationId);
    }
}
