<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Api\Dto;

final class CategoryApiContext
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly string $brandCurrencyCode,
    ) {}
}
