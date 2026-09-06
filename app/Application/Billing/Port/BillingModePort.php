<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\BillingModeStatus;
use App\Application\Billing\Exception\BillingModeRejectedException;
use App\Domain\Billing\BillingMode;

interface BillingModePort
{
    /** Şu an geçerli kip — üç kapının sonucu. */
    public function effective(): BillingMode;

    public function status(): BillingModeStatus;

    /**
     * Süperadminin isteği. `live` yalnız kapılar açıkken kabul edilir; her
     * değişim denetime yazılır.
     *
     * @throws BillingModeRejectedException
     */
    public function request(BillingMode $mode, ?int $byUserId): BillingModeStatus;
}
