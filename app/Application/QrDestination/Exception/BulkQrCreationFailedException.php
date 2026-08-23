<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Exception;

use RuntimeException;
use Throwable;

final class BulkQrCreationFailedException extends RuntimeException
{
    public static function fromPrevious(Throwable $previous): self
    {
        return new self('Bulk QR creation failed.', 0, $previous);
    }
}
