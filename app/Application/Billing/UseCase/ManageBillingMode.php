<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Dto\BillingModeStatus;
use App\Application\Billing\Exception\BillingModeRejectedException;
use App\Application\Billing\Port\BillingModePort;
use App\Domain\Billing\BillingMode;

final class ManageBillingMode
{
    public function __construct(
        private readonly BillingModePort $mode,
    ) {}

    public function status(): BillingModeStatus
    {
        return $this->mode->status();
    }

    /** @throws BillingModeRejectedException */
    public function request(BillingMode $mode, int $byUserId): BillingModeStatus
    {
        return $this->mode->request($mode, $byUserId);
    }
}
