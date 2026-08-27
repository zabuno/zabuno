<?php

declare(strict_types=1);

namespace App\Application\Ai\Exception;

use App\Application\Ai\Port\AiAvailability;
use RuntimeException;

final class AiUnavailableException extends RuntimeException
{
    public function __construct(public readonly AiAvailability $reason)
    {
        parent::__construct("AI kullanılamıyor: {$reason->value}");
    }
}
