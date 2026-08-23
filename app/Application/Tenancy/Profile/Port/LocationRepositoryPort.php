<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\Port;

use App\Application\Tenancy\Profile\Dto\LocationProfile;

interface LocationRepositoryPort
{
    public function findByWorkspaceAndId(int $workspaceId, int $locationId): ?LocationProfile;

    /**
     * @return list<LocationProfile>
     */
    public function listByWorkspaceAndBrand(int $workspaceId, int $brandId): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $workspaceId, int $brandId, array $attributes): LocationProfile;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $workspaceId, int $locationId, array $attributes): ?LocationProfile;
}
