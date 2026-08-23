<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Dto;

final class MenuItemSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $categoryId,
        public readonly int $productId,
        public readonly int $priceMinorAmount,
        public readonly string $currencyCode,
        public readonly int $position,
        public readonly bool $isVisible,
    ) {}
}
