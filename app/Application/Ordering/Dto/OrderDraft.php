<?php

declare(strict_types=1);

namespace App\Application\Ordering\Dto;

/**
 * Yazılmaya hazır sipariş: kopyaları çıkarılmış satırlar ve DONMUŞ toplam.
 *
 * Toplam burada hesaplanır ve siparişle birlikte yazılır; satırlardan
 * sonradan yeniden hesaplanmaz. Satır kopyaları değişmese bile, bir gün
 * yuvarlama ya da toplama kuralı değişirse iki sayı ayrışırdı — ve hangisinin
 * doğru olduğu ancak masada tartışma çıktığında anlaşılırdı.
 */
final class OrderDraft
{
    /** @param list<OrderLineDraft> $lines */
    public function __construct(
        public readonly array $lines,
        public readonly int $totalMinorAmount,
        public readonly string $currencyCode,
    ) {}
}
