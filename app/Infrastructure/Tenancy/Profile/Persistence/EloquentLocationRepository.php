<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Profile\Persistence;

use App\Application\Tenancy\Profile\Dto\LocationProfile;
use App\Application\Tenancy\Profile\Port\LocationRepositoryPort;
use App\Models\Location;

final class EloquentLocationRepository implements LocationRepositoryPort
{
    public function findByWorkspaceAndId(int $workspaceId, int $locationId): ?LocationProfile
    {
        $location = Location::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($locationId)
            ->first();

        return $location === null ? null : $this->toProfile($location);
    }

    /**
     * @return list<LocationProfile>
     */
    public function listByWorkspaceAndBrand(int $workspaceId, int $brandId): array
    {
        return Location::query()
            ->where('workspace_id', $workspaceId)
            ->where('brand_id', $brandId)
            ->orderBy('id')
            ->get()
            ->map(fn (Location $location): LocationProfile => $this->toProfile($location))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $workspaceId, int $brandId, array $attributes): LocationProfile
    {
        $location = Location::query()->create([
            ...$attributes,
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
        ]);

        return $this->toProfile($location);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $workspaceId, int $locationId, array $attributes): ?LocationProfile
    {
        $location = Location::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($locationId)
            ->first();

        if ($location === null) {
            return null;
        }

        $location->update($attributes);

        return $this->toProfile($location);
    }

    private function toProfile(Location $location): LocationProfile
    {
        return new LocationProfile(
            (int) $location->getKey(),
            (int) $location->workspace_id,
            (int) $location->brand_id,
            (string) $location->display_name,
            (string) $location->country_code,
            (string) $location->timezone,
            (string) $location->city,
            (string) $location->address_line1,
            $location->address_line2,
            $location->postal_code,
        );
    }
}
