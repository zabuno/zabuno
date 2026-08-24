<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\Port;

use App\Application\Tenancy\Profile\Dto\BrandProfile;

interface BrandRepositoryPort
{
    public function findByWorkspaceId(int $workspaceId): ?BrandProfile;

    public function slugExists(string $slug): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $workspaceId, array $attributes): BrandProfile;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $workspaceId, array $attributes): ?BrandProfile;
}
