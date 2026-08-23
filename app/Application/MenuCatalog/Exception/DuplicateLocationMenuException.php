<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Exception;

use RuntimeException;

final class DuplicateLocationMenuException extends RuntimeException
{
    public static function forLocation(int $locationId): self
    {
        return new self("Location [{$locationId}] already has a draft menu.");
    }
}
