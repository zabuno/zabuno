<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics\Persistence;

use App\Application\Analytics\Dto\AnalyticsBreakdownRow;
use App\Application\Analytics\Dto\AnalyticsComparison;
use App\Application\Analytics\Dto\AnalyticsDailyBucket;
use App\Application\Analytics\Dto\AnalyticsHourCell;
use App\Application\Analytics\Dto\AnalyticsLocationShare;
use App\Application\Analytics\Dto\AnalyticsSummary;
use App\Application\Analytics\Dto\AnalyticsTimeSeries;
use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentAnalyticsRepository implements AnalyticsRepositoryPort
{
    public function record(
        int $workspaceId,
        int $locationId,
        ?int $qrCodeId,
        int $menuId,
        AnalyticsEventType $eventType,
        Carbon $occurredAt,
        ?string $visitorKey = null,
        ?int $menuItemId = null,
        ?string $searchTerm = null,
    ): void {
        DB::table('analytics_events')->insert([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'qr_code_id' => $qrCodeId,
            'menu_id' => $menuId,
            'menu_item_id' => $menuItemId,
            'search_term' => $searchTerm,
            'event_type' => $eventType->value,
            'visitor_key' => $visitorKey,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    public function itemViewAlreadyCounted(int $workspaceId, int $menuItemId, string $visitorKey, Carbon $on): bool
    {
        return DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->where('event_type', AnalyticsEventType::ItemView->value)
            ->where('menu_item_id', $menuItemId)
            ->where('visitor_key', $visitorKey)
            ->whereBetween('occurred_at', [$on->copy()->startOfDay(), $on->copy()->endOfDay()])
            ->exists();
    }

    /**
     * Aralık BİR YERDE tanımlıdır.
     *
     * İki ayrı `match` bloğu, biri güncellenip diğeri unutulduğunda özet ile
     * ürün raporunun farklı pencerelerden konuşmasına yol açardı — ve bu
     * ayrışma ekranda "toplam 10, ürünlerin toplamı 14" gibi görünürdü.
     */
    private static function cutoffFor(string $range, Carbon $now): Carbon
    {
        return match ($range) {
            'today' => $now->copy()->startOfDay(),
            '7d' => $now->copy()->subDays(7),
            '30d' => $now->copy()->subDays(30),
            default => throw new InvalidArgumentException("Unknown analytics range [{$range}]."),
        };
    }

    public function itemViewersByMenuItem(int $workspaceId, string $range, Carbon $now): array
    {
        return DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->where('event_type', AnalyticsEventType::ItemView->value)
            ->whereNotNull('menu_item_id')
            ->where('occurred_at', '>=', self::cutoffFor($range, $now))
            ->groupBy('menu_item_id')
            // Ham vuruş değil FARKLI ziyaretçi: hem daha anlamlı hem de
            // herkese açık uçtan gelen ucuz şişirmeye dayanıklı (`docs/84`).
            ->selectRaw('menu_item_id, COUNT(DISTINCT visitor_key) as viewers')
            ->pluck('viewers', 'menu_item_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
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
        $cutoff = self::cutoffFor($range, $now);

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
     * Serinin yayımlanması için gereken en az FARKLI ziyaretçi.
     *
     * `ShowMenuEngineeringController::MINIMUM_VIEWERS` ile aynı sayı ve aynı
     * gerekçe: üç ziyaretçilik bir haftanın gün ve saat kırılımı, o üç kişinin
     * ne zaman geldiğini gösterir. Toplam kalabalığı gizler, KOVA gizlemez —
     * eşik bu yüzden kovaya inerken de geçerlidir.
     */
    private const MINIMUM_VISITORS = 5;

    /**
     * Bir ısı haritası hücresinin yayımlanması için gereken en az ziyaretçi.
     *
     * "Salı 03:00 · 1 tarama" bir istatistik değil, bir kişinin o gece oraya
     * girdiğinin kaydıdır. İki kişi bir kalabalık değildir ama tek kişi hiç
     * değildir; eşik teşhis edilebilirliğin başladığı yerdedir.
     */
    private const MINIMUM_CELL_VISITORS = 2;

    /**
     * Karşılaştırma penceresinin GERİ KAYDIRILDIĞI süre (gün).
     *
     * Tek bir kural iki ihtiyacı birden karşılar: pencereyi kendi uzunluğu
     * kadar geri kaydırmak "önceki dönem"i verir (7 gün ↔ önceki 7 gün),
     * "bugün"ü yedi gün geri kaydırmak ise geçen haftanın AYNI GÜNÜNÜ, aynı
     * saate kadar verir. İkincisi şart: bir restoranda cumartesi ile salı
     * aynı işletme değildir, ve öğle saatinde yarım günü tam günle
     * karşılaştıran bir rapor sahibi boşuna paniğe sokar.
     */
    private static function comparisonShiftDays(string $range): int
    {
        return match ($range) {
            'today', '7d' => 7,
            '30d' => 30,
            default => throw new InvalidArgumentException("Unknown analytics range [{$range}]."),
        };
    }

    /**
     * Kovaların çizileceği saat dilimi — ŞUBENİN saati, sunucununki değil.
     *
     * İstanbul'da 00:30'da okutulan bir karekod, sunucu UTC'de olduğu için
     * bir önceki günün kovasına düşerse sahibin "cumartesi gecesi" dediği şey
     * pazar raporunda görünür ve grafiğin tamamı bir gün kayar.
     *
     * Marka kapsamında ilk şubenin saati kullanılır: bir markanın şubeleri
     * pratikte aynı saat diliminde ve tek bir grafiğin tek bir gün sınırı
     * olabilir. Hiçbiri yoksa UTC — `ShowMenuEngineeringController`'ın
     * yanındaki `ShowMenuController` da aynı geri düşüşü kullanıyor.
     */
    private function timezoneFor(int $workspaceId, ?int $locationId): string
    {
        $timezone = $locationId !== null
            ? DB::table('locations')->where('id', $locationId)->value('timezone')
            : DB::table('locations')->where('workspace_id', $workspaceId)->orderBy('id')->value('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    public function timeSeries(
        int $workspaceId,
        ?int $locationId,
        string $range,
        Carbon $now,
    ): AnalyticsTimeSeries {
        /*
            ARALIK ÖZETLE AYNI YERDEN OKUNUR (`self::cutoffFor`).

            Ayrı bir aralık hesabı yazmak, ekranda "toplam 214, günlerin
            toplamı 209" gibi görünürdü — ve bir kez yanlış çıkan rapor bir
            daha okunmaz.
        */
        $cutoff = self::cutoffFor($range, $now);
        $timezone = $this->timezoneFor($workspaceId, $locationId);

        /*
            SATIRLAR HAM OKUNUR, GRUPLAMA PHP'DE YAPILIR.

            Gün ve saat kırılımı, veritabanının tarih fonksiyonlarıyla
            (`strftime` / `DATE_FORMAT` / `date_trunc`) yazılabilirdi ama
            üçünün sözdizimi de farklıdır: SQLite'ta çalışan sorgu MySQL'de
            sessizce başka bir şey döndürür. Üstelik gün sınırı ŞUBENİN saat
            diliminde çizilmeli ve saat dilimi dönüşümü üç motorda da başka
            türlü yapılıyor.

            Pencere zaten kiracıya ve tarihe göre indekslidir
            (`analytics_events_scope_range_index`); okunan şey bir raporun
            kendi penceresidir, tablonun tamamı değil.
        */
        $rows = DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->whereIn('event_type', [AnalyticsEventType::QrResolve->value, AnalyticsEventType::MenuOpen->value])
            ->where('occurred_at', '>=', $cutoff)
            ->select(['event_type', 'occurred_at', 'visitor_key'])
            ->get();

        /*
            Eşik ölçüsü özetteki `uniqueVisitorCount` ile AYNI disiplindedir:
            yalnız karekod çözümlemesi ve yalnız anahtarı olan olaylar sayılır.
            Menü açılışı aynı ziyaretçinin ikinci olayıdır; ikisini birlikte
            saymak aynı kişiyi iki kez sayardı.
        */
        $windowVisitors = [];

        foreach ($rows as $row) {
            if ($row->event_type === AnalyticsEventType::QrResolve->value && is_string($row->visitor_key) && $row->visitor_key !== '') {
                $windowVisitors[$row->visitor_key] = true;
            }
        }

        $observedVisitors = count($windowVisitors);

        if ($observedVisitors < self::MINIMUM_VISITORS) {
            /*
                Boş bir grafik sahibe "ürünüm bozuk" dedirtir. Sebep ve EŞİK
                açıkça söylenir (`docs/66` disiplini): kaç ziyaretçi
                gerektiğini bilmeyen biri, ne kadar bekleyeceğini de bilemez.
            */
            return new AnalyticsTimeSeries(
                $range,
                AnalyticsTimeSeries::STATE_NOT_ENOUGH_DATA,
                self::MINIMUM_VISITORS,
                $observedVisitors,
                $timezone,
                [],
                null,
                [],
                0,
                [],
                'workspace',
                $now->toIso8601String(),
            );
        }

        [$buckets, $hourly, $suppressedHourCells, $currentResolves] = $this->bucketize($rows, $cutoff, $now, $timezone);

        return new AnalyticsTimeSeries(
            $range,
            AnalyticsTimeSeries::STATE_READY,
            self::MINIMUM_VISITORS,
            $observedVisitors,
            $timezone,
            $buckets,
            $this->comparison($workspaceId, $locationId, $range, $cutoff, $now, $currentResolves),
            $hourly,
            $suppressedHourCells,
            $this->locationShare($workspaceId, $cutoff),
            'workspace',
            $now->toIso8601String(),
        );
    }

    /**
     * Ham satırları gün kovalarına ve saat hücrelerine dağıtır.
     *
     * @param  Collection<int, object>  $rows
     * @return array{0: list<AnalyticsDailyBucket>, 1: list<AnalyticsHourCell>, 2: int, 3: int}
     */
    private function bucketize(Collection $rows, Carbon $cutoff, Carbon $now, string $timezone): array
    {
        /** @var array<string, array{resolve: int, open: int}> $days */
        $days = [];
        /** @var array<string, array{count: int, visitors: array<string, true>}> $cells */
        $cells = [];
        $currentResolves = 0;

        foreach ($rows as $row) {
            $local = Carbon::parse((string) $row->occurred_at)->setTimezone($timezone);
            $date = $local->format('Y-m-d');

            $days[$date] ??= ['resolve' => 0, 'open' => 0];

            if ($row->event_type === AnalyticsEventType::MenuOpen->value) {
                $days[$date]['open']++;

                continue;
            }

            $days[$date]['resolve']++;
            $currentResolves++;

            // Isı haritası TARAMAYI sayar: kaynağın ipucu metni de "N tarama"
            // diyor. Menü açılışını da katmak, aynı ziyareti iki kez ısıtırdı.
            $cellKey = $local->isoWeekday().'-'.$local->hour;
            $cells[$cellKey] ??= ['count' => 0, 'visitors' => []];
            $cells[$cellKey]['count']++;

            if (is_string($row->visitor_key) && $row->visitor_key !== '') {
                $cells[$cellKey]['visitors'][$row->visitor_key] = true;
            }
        }

        /*
            BOŞ GÜN DE BİR KOVADIR.

            Yalnız olayı olan günleri döndüren bir seri, salı ile perşembeyi
            yan yana çizer ve aradaki çarşamba yokmuş gibi görünür. Grafiğin
            söylediği şey "çarşamba düştü" değil, "çarşamba hiç olmadı" olurdu.
        */
        $buckets = [];
        $cursor = $cutoff->copy()->setTimezone($timezone)->startOfDay();
        $last = $now->copy()->setTimezone($timezone)->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $date = $cursor->format('Y-m-d');
            $buckets[] = new AnalyticsDailyBucket(
                $date,
                $days[$date]['resolve'] ?? 0,
                $days[$date]['open'] ?? 0,
            );
            $cursor->addDay();
        }

        /*
            TEK KİŞİLİK HÜCRE YAYIMLANMAZ ama SESSİZCE de düşürülmez.

            Anahtarı olmayan olay bir ziyaretçi saymaz: onu "bir kişi" saymak,
            bilinmeyeni bilinen gibi göstermek olurdu. Bu yüzden yalnız
            anahtarsız olaylardan oluşan bir hücre de yayımlanmaz.
        */
        $hourly = [];
        $suppressed = 0;

        foreach ($cells as $key => $cell) {
            if (count($cell['visitors']) < self::MINIMUM_CELL_VISITORS) {
                $suppressed++;

                continue;
            }

            [$weekday, $hour] = explode('-', (string) $key);
            $hourly[] = new AnalyticsHourCell((int) $weekday, (int) $hour, $cell['count']);
        }

        usort(
            $hourly,
            static fn (AnalyticsHourCell $a, AnalyticsHourCell $b): int => [$a->weekday, $a->hour] <=> [$b->weekday, $b->hour],
        );

        return [$buckets, $hourly, $suppressed, $currentResolves];
    }

    /**
     * Bir önceki pencere: aynı pencere, geriye kaydırılmış hâli.
     *
     * Yalnız karekod çözümlemesi sayılır — kaynağın deltası da tarama
     * üzerinden konuşuyor ("%12 · geçen perşembe") ve iki farklı olayın
     * toplamı üzerinden hesaplanan bir yüzde, hangi şeyin değiştiğini
     * söylemez.
     */
    private function comparison(
        int $workspaceId,
        ?int $locationId,
        string $range,
        Carbon $cutoff,
        Carbon $now,
        int $currentResolves,
    ): AnalyticsComparison {
        $shift = self::comparisonShiftDays($range);
        $previousStart = $cutoff->copy()->subDays($shift);
        $previousEnd = $now->copy()->subDays($shift);

        $previousResolves = (int) DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->where('event_type', AnalyticsEventType::QrResolve->value)
            ->where('occurred_at', '>=', $previousStart)
            ->where('occurred_at', '<', $previousEnd)
            ->count();

        return new AnalyticsComparison(
            $range === 'today'
                ? AnalyticsComparison::BASIS_SAME_WEEKDAY_LAST_WEEK
                : AnalyticsComparison::BASIS_PREVIOUS_PERIOD,
            $currentResolves,
            $previousResolves,
            $previousStart->toIso8601String(),
            $previousEnd->toIso8601String(),
        );
    }

    /**
     * Şube payı — HER ZAMAN markanın tamamından.
     *
     * Tek şubeye süzülmüş bir ekranda payı da o şubeye süzmek, halkayı her
     * zaman %100 çizerdi: hiçbir şey söylemeyen bir daire. Sorunun kendisi
     * "bu şube markanın ne kadarı?" olduğu için kapsam çalışma alanıdır ve
     * yanıtta `locationShareScope` ile açıkça yazar.
     *
     * @return list<AnalyticsLocationShare>
     */
    private function locationShare(int $workspaceId, Carbon $cutoff): array
    {
        $rows = DB::table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->where('event_type', AnalyticsEventType::QrResolve->value)
            ->where('occurred_at', '>=', $cutoff)
            ->groupBy('location_id')
            ->selectRaw('location_id, count(*) as aggregate')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $total = (int) $rows->sum('aggregate');

        $labels = DB::table('locations')
            ->whereIn('id', $rows->pluck('location_id')->all())
            ->pluck('display_name', 'id');

        $share = [];

        foreach ($rows as $row) {
            $id = (int) $row->location_id;
            $count = (int) $row->aggregate;

            $share[] = new AnalyticsLocationShare(
                $id,
                // Adı silinmiş bir kayıt için kimlik geri düşer: satırı hiç
                // göstermemek, o şubenin taramalarını paydan sessizce düşürürdü.
                (string) ($labels[$id] ?? "#{$id}"),
                $count,
                $total === 0 ? 0.0 : round($count / $total * 100, 2),
            );
        }

        usort(
            $share,
            static fn (AnalyticsLocationShare $a, AnalyticsLocationShare $b): int => $b->qrResolveCount <=> $a->qrResolveCount,
        );

        return $share;
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
