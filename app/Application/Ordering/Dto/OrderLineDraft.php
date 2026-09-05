<?php

declare(strict_types=1);

namespace App\Application\Ordering\Dto;

/**
 * Siparişe YAZILACAK satır — kopyası çıkarılmış hâliyle.
 *
 * `menuItemId` yalnız izlenebilirlik için taşınır ("hangi satır ne kadar
 * sipariş edildi"). Mutfağın okuduğu metin bu tipin İÇİNDEDİR ve bir daha
 * menüye bakmaz: yarın fiyat değiştiğinde ya da ürün yeniden
 * adlandırıldığında dünkü sipariş değişmemeli.
 */
final class OrderLineDraft
{
    /** @param list<string> $allergens */
    public function __construct(
        public readonly int $menuItemId,
        public readonly string $productName,
        public readonly int $unitPriceMinorAmount,
        public readonly string $currencyCode,
        public readonly int $quantity,
        public readonly int $lineTotalMinorAmount,
        public readonly array $allergens,
    ) {}
}
