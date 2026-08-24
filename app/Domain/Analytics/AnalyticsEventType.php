<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

enum AnalyticsEventType: string
{
    case QrResolve = 'qr_resolve';
    case MenuOpen = 'menu_open';
}
