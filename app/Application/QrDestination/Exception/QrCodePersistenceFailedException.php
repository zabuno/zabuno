<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Exception;

use RuntimeException;
use Throwable;

final class QrCodePersistenceFailedException extends RuntimeException
{
    public static function fromPrevious(Throwable $previous): self
    {
        return new self('QR code persistence failed.', 0, $previous);
    }
}
