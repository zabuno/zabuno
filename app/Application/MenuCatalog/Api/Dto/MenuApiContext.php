<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Api\Dto;

/**
 * Bir menünün, HTTP katmanının karar vermek için ihtiyaç duyduğu bağlamı —
 * FF-154.
 *
 * Menü düzeyinde bugüne kadar böyle bir okuma gerekmiyordu: kiracı sınırını
 * yazma kapıları (`MenuSchedulePort`) kendi içinde zorluyor. Denetim izi
 * yeni bir ihtiyaç doğurdu — silinen ya da adı değişen bir menünün ESKİ ADI
 * yalnız işlemden ÖNCE okunabilir; sonrasında sorulacak yer kalmıyor.
 */
final class MenuApiContext
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $locationId,
        public readonly string $name,
    ) {}
}
