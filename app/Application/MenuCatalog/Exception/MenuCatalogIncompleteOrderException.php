<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Exception;

use RuntimeException;

/**
 * Sıralama listesi eksik ya da fazla — `docs/73`.
 *
 * Kısmî bir sıralama, listelenmeyen satırları öngörülemez bir yere bırakır ve
 * kullanıcı bunu ekranda değil, yayınladıktan sonra misafirin menüsünde fark
 * eder. Sessizce tamamlamak yerine reddedilir.
 */
final class MenuCatalogIncompleteOrderException extends RuntimeException
{
    public static function forCounts(int $existing, int $requested): self
    {
        return new self("Order list must contain every row exactly once ({$requested} given, {$existing} expected).");
    }
}
