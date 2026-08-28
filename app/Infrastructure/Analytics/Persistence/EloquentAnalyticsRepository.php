<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics\Persistence;

use App\Application\Analytics\Dto\AnalyticsBreakdownRow;
use App\Application\Analytics\Dto\AnalyticsSummary;
use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use Illuminate\Database\Query\Builder;
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
        ?string $visitorKey = null,
    ): void {
        DB::table('analytics_events')->insert([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'qr_code_id' => $qrCodeId,
            'menu_id' => $menuId,
            'event_type' => $eventType->value,
            'visitor_key' => $visitorKey,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    /**
     * @param  int|null  $locationId  `null` ise çalışma alanının TAMAMI.
     *
     * Şube kapsamı isteğe bağlı oldu: iki şubesi olan bir işletme markanın
     * bütününü göremiyordu ve toplamı bulmak için şubeleri tek tek gezmek
     * zorundaydı (`docs/68`).
     */
    public function summarize(
        int $workspaceId,
        ?int $locationId,
        string $range,
        Carbon $now,
    ): AnalyticsSummary {
        $cutoff = match ($range) {
            'today' => $now->copy()->startOfDay(),
            '7d' => $now->copy()->subDays(7),
            '30d' => $now->copy()->subDays(30),
            default => throw new InvalidArgumentException("Unknown analytics range [{$range}]."),
        };

        $scope = fn () => DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->where('occurred_at', '>=', $cutoff);

        $counts = $scope()
            ->selectRaw('event_type, count(*) as aggregate')
            ->groupBy('event_type')
            ->pluck('aggregate', 'event_type');

        /*
            Benzersiz sayım YALNIZ anahtarı olan olayları sayar. Bu ölçüm
            eklenmeden önce yazılan satırların anahtarı yoktur ve onları "bir
            kişi" saymak, bilinmeyeni bilinen gibi göstermek olurdu.

            Sayım karekod ÇÖZÜMLEME olayı üzerinden yapılır: menü açılışı
            aynı ziyaretçinin ikinci olayıdır ve ikisini birlikte saymak aynı
            kişiyi iki kez sayardı.
        */
        $uniqueVisitorCount = (int) $scope()
            ->where('event_type', AnalyticsEventType::QrResolve->value)
            ->whereNotNull('visitor_key')
            ->distinct()
            ->count('visitor_key');

        return new AnalyticsSummary(
            $range,
            (int) ($counts[AnalyticsEventType::QrResolve->value] ?? 0),
            (int) ($counts[AnalyticsEventType::MenuOpen->value] ?? 0),
            $uniqueVisitorCount,
            $this->breakdown($scope(), 'location_id', 'locations', 'display_name'),
            /*
                Karekodun İNSAN ADI yok: `qr_codes` yalnız jeton taşıyor.
                Kod adlandırma bir ürün özelliğidir ve henüz yok. Jeton
                kullanılıyor, çünkü basılı kodun adresinde de o geçiyor —
                kullanıcı eşleştirebilir. Uydurulmuş bir "QR #3" etiketi ise
                hiçbir basılı kodla eşleşmezdi.
            */
            $this->breakdown($scope(), 'qr_code_id', 'qr_codes', 'token'),
            $now->toIso8601String(),
        );
    }

    /**
     * Bir boyuta göre kırılım.
     *
     * Etiket ADI ilgili tablodan gelir: kullanıcıya `location_id = 923`
     * göstermek, ona veritabanı satırını okutmaktır. Adı silinmiş bir kayıt
     * için kimlik geri düşer — satırı hiç göstermemek, o kaydın taramalarını
     * toplamdan sessizce düşürürdü.
     *
     * @return list<AnalyticsBreakdownRow>
     */
    private function breakdown(
        Builder $scope,
        string $column,
        string $labelTable,
        string $labelColumn,
    ): array {
        $rows = $scope
            ->selectRaw("{$column} as dimension_id, event_type, count(*) as aggregate")
            ->groupBy($column, 'event_type')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('dimension_id')->unique()->values();

        $labels = DB::table($labelTable)
            ->whereIn('id', $ids)
            ->pluck($labelColumn, 'id');

        $byId = [];

        foreach ($rows as $row) {
            $id = (int) $row->dimension_id;
            $byId[$id] ??= ['resolve' => 0, 'open' => 0];

            if ($row->event_type === AnalyticsEventType::QrResolve->value) {
                $byId[$id]['resolve'] = (int) $row->aggregate;
            } elseif ($row->event_type === AnalyticsEventType::MenuOpen->value) {
                $byId[$id]['open'] = (int) $row->aggregate;
            }
        }

        $breakdown = [];

        foreach ($byId as $id => $totals) {
            $breakdown[] = new AnalyticsBreakdownRow(
                $id,
                (string) ($labels[$id] ?? "#{$id}"),
                $totals['resolve'],
                $totals['open'],
            );
        }

        // En çok taranan önce: listenin başı en çok bilgi taşıyan yerdir.
        usort(
            $breakdown,
            static fn (AnalyticsBreakdownRow $a, AnalyticsBreakdownRow $b): int => $b->qrResolveCount <=> $a->qrResolveCount,
        );

        return $breakdown;
    }
}
