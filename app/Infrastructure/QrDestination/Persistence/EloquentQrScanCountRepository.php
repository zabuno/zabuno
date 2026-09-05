<?php

declare(strict_types=1);

namespace App\Infrastructure\QrDestination\Persistence;

use App\Application\QrDestination\Port\QrScanCountPort;
use App\Domain\Analytics\AnalyticsEventType;
use Illuminate\Support\Facades\DB;

/**
 * Tarama sayıları — `analytics_events` üstünde TEK sorgu.
 *
 * Kod başına ayrı bir sayım yapmak kırk masalı bir restoranda kırk sorgu
 * demekti; ekranın açılışı masa sayısıyla birlikte yavaşlar ve bunu ilk fark
 * eden, en çok masası olan (yani en değerli) müşteri olurdu.
 *
 * Kapsam HEM çalışma alanı HEM şubedir. Yalnız `qr_code_id`'lerle sormak da
 * doğru sonucu verirdi ama kiracı sınırını sorgunun kendisine yazmak,
 * çağıranın bir gün süzgeci unutmasına karşı ikinci bir duvardır.
 */
final class EloquentQrScanCountRepository implements QrScanCountPort
{
    /**
     * @return array<int, int>
     */
    public function countsForLocation(int $workspaceId, int $locationId): array
    {
        $rows = DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->where('event_type', AnalyticsEventType::QrResolve->value)
            ->whereNotNull('qr_code_id')
            ->selectRaw('qr_code_id, count(*) as aggregate')
            ->groupBy('qr_code_id')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->qr_code_id] = (int) $row->aggregate;
        }

        return $counts;
    }
}
