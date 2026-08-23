<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Exception;

use RuntimeException;

final class MenuCatalogTenantMismatchException extends RuntimeException
{
    public static function forWorkspace(int $workspaceId): self
    {
        return new self("The requested Menu Catalog resource does not belong to workspace [{$workspaceId}].");
    }
}
