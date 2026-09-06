<?php

declare(strict_types=1);

namespace App\Application\Billing\Exception;

use RuntimeException;

/** `live` istendi ama bir kapı kapalı; `reason` kapının adıdır. */
final class BillingModeRejectedException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
