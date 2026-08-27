<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Dto;

/**
 * Menüye eklenen bir satırın TAMAMI: ürün, fiyatı ve alerjenleri.
 *
 * Ayrı ayrı `ProductSummary` + `MenuItemSummary` döndürmek yerine tek bir
 * kayıt döndürülür, çünkü kullanıcı için de bunlar tek bir şeydir: "menüye
 * bir ürün ekledim". Üçe bölünmüş bir cevap, arayüzü üç ayrı forma bölen
 * eski tasarımın veri tarafındaki yankısıydı.
 */
final class MenuEntrySummary
{
    /** @param list<string> $allergens */
    public function __construct(
        public readonly int $menuItemId,
        public readonly int $categoryId,
        public readonly int $productId,
        public readonly string $productName,
        public readonly int $priceMinorAmount,
        public readonly string $currencyCode,
        public readonly int $position,
        public readonly bool $isVisible,
        public readonly array $allergens,
    ) {}
}
