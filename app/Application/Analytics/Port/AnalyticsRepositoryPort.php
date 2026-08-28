<?php

declare(strict_types=1);

namespace App\Application\Analytics\Port;

use App\Application\Analytics\Dto\AnalyticsSummary;
use App\Domain\Analytics\AnalyticsEventType;
use Illuminate\Support\Carbon;

interface AnalyticsRepositoryPort
{
    public function record(
        int $workspaceId,
        int $locationId,
        int $qrCodeId,
        int $menuId,
        AnalyticsEventType $eventType,
        Carbon $occurredAt,
        /** Türetilmiş, geri çevrilemez ziyaretçi özeti; bilinmiyorsa null. */
        ?string $visitorKey = null,
    ): void;

    /** @param  int|null  $locationId  `null` ise çalışma alanının tamamı. */
    public function summarize(
        int $workspaceId,
        ?int $locationId,
        string $range,
        Carbon $now,
    ): AnalyticsSummary;
}
