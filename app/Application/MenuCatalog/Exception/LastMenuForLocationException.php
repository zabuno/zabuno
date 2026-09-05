<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Exception;

use RuntimeException;

/**
 * Şubenin son menüsü silinemez.
 *
 * Menüsüz bir şubenin karekodu bir yere gider ama orada hiçbir şey
 * yoktur: misafir masada oturur, telefonunda boş bir sayfa vardır ve
 * restoranın yapabileceği bir şey yoktur. Bu yüzden silme burada
 * REDDEDİLİR; sahibin isteği "bu menüyü istemiyorum"sa yapılacak iş yenisini
 * kurup eskisini silmektir.
 */
final class LastMenuForLocationException extends RuntimeException
{
    public static function forLocation(int $locationId): self
    {
        return new self("Location [{$locationId}] must keep at least one menu.");
    }
}
