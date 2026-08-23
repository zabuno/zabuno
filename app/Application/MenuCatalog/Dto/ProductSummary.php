<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Dto;

final class ProductSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $name,
    ) {}
}
