<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Dto;

final class CategorySummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $menuId,
        public readonly string $name,
        public readonly int $position,
    ) {}
}
