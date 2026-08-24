<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Api\Dto;

final class MenuItemApiContext
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $productId,
        public readonly string $brandCurrencyCode,
    ) {}
}
