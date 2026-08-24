<?php

declare(strict_types=1);

namespace App\Application\Publication\Exception;

use RuntimeException;
use Throwable;

final class PublicationPersistenceFailedException extends RuntimeException
{
    public static function fromPrevious(Throwable $previous): self
    {
        return new self('Publication persistence failed.', 0, $previous);
    }
}
