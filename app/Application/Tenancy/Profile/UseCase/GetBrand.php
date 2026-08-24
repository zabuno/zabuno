<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\UseCase;

use App\Application\Tenancy\Profile\Dto\BrandProfile;
use App\Application\Tenancy\Profile\Port\BrandRepositoryPort;

final class GetBrand
{
    public function __construct(
        private readonly BrandRepositoryPort $brands,
    ) {}

    public function handle(int $workspaceId): ?BrandProfile
    {
        return $this->brands->findByWorkspaceId($workspaceId);
    }
}
