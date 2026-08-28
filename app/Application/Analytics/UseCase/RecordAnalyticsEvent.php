<?php

declare(strict_types=1);

namespace App\Application\Analytics\UseCase;

use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use Illuminate\Support\Carbon;
use Throwable;

final class RecordAnalyticsEvent
{
    public function __construct(private readonly AnalyticsRepositoryPort $analytics) {}

    /**
     * Best-effort: a ledger write failure must never break the caller
     * (e.g. the /q/{token} redirect), so any Throwable is swallowed here.
     */
    public function handle(
        int $workspaceId,
        int $locationId,
        int $qrCodeId,
        int $menuId,
        AnalyticsEventType $eventType,
        ?string $visitorKey = null,
    ): void {
        try {
            $this->analytics->record(
                $workspaceId,
                $locationId,
                $qrCodeId,
                $menuId,
                $eventType,
                Carbon::now(),
                $visitorKey,
            );
        } catch (Throwable) {
            // Analytics recording is best-effort and must not surface.
        }
    }
}
