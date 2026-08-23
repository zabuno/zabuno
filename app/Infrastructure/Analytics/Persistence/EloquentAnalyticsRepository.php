<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics\Persistence;

use App\Application\Analytics\Dto\AnalyticsSummary;
use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentAnalyticsRepository implements AnalyticsRepositoryPort
{
    public function record(
        int $workspaceId,
        int $locationId,
        int $qrCodeId,
        int $menuId,
        AnalyticsEventType $eventType,
        Carbon $occurredAt,
    ): void {
        DB::table('analytics_events')->insert([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'qr_code_id' => $qrCodeId,
            'menu_id' => $menuId,
            'event_type' => $eventType->value,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    public function summarize(int $workspaceId, int $locationId, string $range, Carbon $now): AnalyticsSummary
    {
        $cutoff = match ($range) {
            'today' => $now->copy()->startOfDay(),
            '7d' => $now->copy()->subDays(7),
            '30d' => $now->copy()->subDays(30),
            default => throw new InvalidArgumentException("Unknown analytics range [{$range}]."),
        };

        $counts = DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->where('occurred_at', '>=', $cutoff)
            ->selectRaw('event_type, count(*) as aggregate')
            ->groupBy('event_type')
            ->pluck('aggregate', 'event_type');

        return new AnalyticsSummary(
            $range,
            (int) ($counts[AnalyticsEventType::QrResolve->value] ?? 0),
            (int) ($counts[AnalyticsEventType::MenuOpen->value] ?? 0),
            $now->toIso8601String(),
        );
    }
}
