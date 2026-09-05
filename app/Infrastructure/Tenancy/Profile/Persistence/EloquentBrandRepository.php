<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Profile\Persistence;

use App\Application\Tenancy\Profile\Dto\BrandProfile;
use App\Application\Tenancy\Profile\Port\BrandRepositoryPort;
use App\Models\Brand;

final class EloquentBrandRepository implements BrandRepositoryPort
{
    public function findByWorkspaceId(int $workspaceId): ?BrandProfile
    {
        $brand = Brand::query()->where('workspace_id', $workspaceId)->first();

        return $brand === null ? null : $this->toProfile($brand);
    }

    public function slugExists(string $slug): bool
    {
        return Brand::query()->where('slug', $slug)->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $workspaceId, array $attributes): BrandProfile
    {
        $brand = Brand::query()->create([...$attributes, 'workspace_id' => $workspaceId]);

        return $this->toProfile($brand);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $workspaceId, array $attributes): ?BrandProfile
    {
        $brand = Brand::query()->where('workspace_id', $workspaceId)->first();

        if ($brand === null) {
            return null;
        }

        $brand->update($attributes);

        return $this->toProfile($brand);
    }

    private function toProfile(Brand $brand): BrandProfile
    {
        return new BrandProfile(
            (int) $brand->getKey(),
            (int) $brand->workspace_id,
            (string) $brand->name,
            (string) $brand->slug,
            (string) $brand->locale,
            (string) $brand->timezone,
            (string) $brand->currency,
            $brand->description,
            $brand->contact_email,
            $brand->contact_phone,
            $brand->primary_color,
            $brand->secondary_color,
            $brand->skin_variant,
        );
    }
}
