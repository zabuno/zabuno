<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Port;

/**
 * Bugün tükenmiş menü satırları — `docs/82` (P1-04).
 *
 * Yayın snapshot'ından AYRI okunur: tükendi, donmuş menünün üstüne konan
 * bir tebeşir notudur; menünün kendisi değil.
 */
interface OutOfStockPort
{
    /** @return list<int> */
    public function forMenu(int $menuId): array;
}
