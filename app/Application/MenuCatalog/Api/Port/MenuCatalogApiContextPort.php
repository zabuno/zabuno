<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Api\Port;

use App\Application\MenuCatalog\Api\Dto\CategoryApiContext;
use App\Application\MenuCatalog\Api\Dto\MenuItemApiContext;

interface MenuCatalogApiContextPort
{
    public function locationWorkspaceId(int $locationId): ?int;

    public function menuIdForLocation(int $workspaceId, int $locationId): ?int;

    public function categoryContext(int $categoryId): ?CategoryApiContext;

    public function productWorkspaceId(int $productId): ?int;

    public function menuItemContext(int $menuItemId): ?MenuItemApiContext;

    /**
     * @return list<string>
     */
    public function allergensForProduct(int $productId): array;
}
