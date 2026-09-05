<?php

declare(strict_types=1);

namespace App\Infrastructure\Ordering\Persistence;

use App\Application\Ordering\Dto\OrderHistoryPage;
use App\Application\Ordering\Dto\OrderLineSummary;
use App\Application\Ordering\Dto\OrderSummary;
use App\Application\Ordering\Port\OrderQueryPort;
use App\Domain\Ordering\OrderStatus;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentOrderQuery implements OrderQueryPort
{
    /**
     * Kuyrukta ve monitörde okunan en fazla satır.
     *
     * Sınır bir performans ayarı değil, bir DÜRÜSTLÜK sınırı: yüzlerce satırı
     * mutfak duvarındaki bir ekrana basmak, aşçının hiçbirini okumaması
     * demektir. Sayı yoklamayla birlikte her aralıkta yeniden okunuyor;
     * sınırsız bir liste, servisin en yoğun anında en ağır isteği üretirdi.
     */
    public const BOARD_LIMIT = 100;

    public function pending(int $workspaceId, int $locationId, int $limit): array
    {
        return $this->board(
            $workspaceId,
            $locationId,
            [OrderStatus::Pending->value],
            'asc',
            $limit,
        );
    }

    public function kitchenBoard(int $workspaceId, int $locationId, int $limit): array
    {
        /*
            MUTFAĞIN GÖRDÜĞÜ DURUMLAR ALANDAN OKUNUR.

            `['confirmed', 'preparing', 'ready']` diye yazmak tek satır daha
            kısaydı ve yeni bir durum eklendiği gün sessizce eksik kalırdı —
            eksikliği ancak bir tabak hiç pişmediğinde fark edilirdi.
        */
        $statuses = [];

        foreach (OrderStatus::cases() as $case) {
            if ($case->isVisibleToKitchen()) {
                $statuses[] = $case->value;
            }
        }

        return $this->board($workspaceId, $locationId, $statuses, 'asc', $limit);
    }

    public function history(int $workspaceId, int $locationId, int $page, int $perPage): OrderHistoryPage
    {
        $total = DB::table('orders')
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->count();

        $pageCount = $total === 0 ? 1 : (int) ceil($total / $perPage);
        $page = max(1, min($page, $pageCount));

        $rows = $this->baseQuery($workspaceId, $locationId)
            // Geçmişte aranan şey son olandır: en YENİ üstte. Kuyrukla ters
            // olması bilinçli — kuyruk "kim en çok bekledi", geçmiş "az önce
            // ne oldu" sorusudur.
            ->orderByDesc('orders.placed_at')
            ->orderByDesc('orders.id')
            ->forPage($page, $perPage)
            ->get();

        return new OrderHistoryPage($this->hydrate($rows->all()), $page, $pageCount);
    }

    /**
     * @param  list<string>  $statuses
     * @return list<OrderSummary>
     */
    private function board(
        int $workspaceId,
        int $locationId,
        array $statuses,
        string $direction,
        int $limit,
    ): array {
        if ($statuses === []) {
            return [];
        }

        $rows = $this->baseQuery($workspaceId, $locationId)
            ->whereIn('orders.status', $statuses)
            ->orderBy('orders.placed_at', $direction)
            ->orderBy('orders.id', $direction)
            ->limit(max(1, min($limit, self::BOARD_LIMIT)))
            ->get();

        return $this->hydrate($rows->all());
    }

    /**
     * Sipariş + masa + salon + şubenin saat dilimi, TEK sorguda.
     *
     * Masa adı sipariş satırına kopyalanmadı ve bu bilinçli: ürün adı ve
     * fiyatı kopyalanır çünkü onlar DEĞİŞİR (yarın fiyat başkadır); masa ise
     * mekânın kendisidir ve adı düzeltildiğinde eski siparişin de düzelmesi
     * DOĞRU davranıştır. "Masa 7" yazan bir fişin, masa "7A" olarak yeniden
     * adlandırıldıktan sonra hâlâ eski adı göstermesi garsonu yanlış masaya
     * gönderirdi.
     */
    private function baseQuery(int $workspaceId, int $locationId): Builder
    {
        return DB::table('orders')
            ->join('dining_tables', 'dining_tables.id', '=', 'orders.dining_table_id')
            ->leftJoin('dining_areas', 'dining_areas.id', '=', 'dining_tables.area_id')
            ->leftJoin('locations', 'locations.id', '=', 'orders.location_id')
            // Kiracı VE şube sorgunun İÇİNDE; ekran kuralı değil.
            ->where('orders.workspace_id', $workspaceId)
            ->where('orders.location_id', $locationId)
            ->select([
                'orders.id',
                'orders.status',
                'orders.total_minor_amount',
                'orders.currency_code',
                'orders.rejection_reason',
                'orders.placed_at',
                'orders.status_changed_at',
                'dining_tables.name as table_name',
                'dining_areas.label as area_label',
                'locations.timezone as location_timezone',
            ]);
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<OrderSummary>
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $orderIds = array_map(static fn (stdClass $row): int => (int) $row->id, $rows);

        /*
            SATIRLAR TEK SORGUDA. Sipariş başına bir sorgu (N+1), on masalı
            bir kuyrukta on bir istek demekti — ve o istekler yoklamayla
            birlikte her aralıkta tekrarlanırdı.
        */
        $lineRows = DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->orderBy('id')
            ->get();

        $linesByOrder = [];

        foreach ($lineRows as $line) {
            $linesByOrder[(int) $line->order_id][] = new OrderLineSummary(
                (string) $line->product_name,
                (int) $line->quantity,
                (int) $line->unit_price_minor_amount,
                (int) $line->line_total_minor_amount,
                (string) $line->currency_code,
                $this->allergensOf($line->allergens),
            );
        }

        $summaries = [];

        foreach ($rows as $row) {
            $status = OrderStatus::tryFrom((string) $row->status);

            if ($status === null) {
                /*
                    Tanınmayan bir durum ATLANIR, uydurulmaz. Bir sipariş
                    ekranda görünmezse fark edilir ve sorulur; yanlış bir
                    durumla görünürse aşçı ona göre davranır.
                */
                continue;
            }

            $summaries[] = new OrderSummary(
                (int) $row->id,
                $status,
                (string) $row->table_name,
                $row->area_label === null ? null : (string) $row->area_label,
                (int) $row->total_minor_amount,
                (string) $row->currency_code,
                $row->rejection_reason === null ? null : (string) $row->rejection_reason,
                new DateTimeImmutable((string) $row->placed_at),
                new DateTimeImmutable((string) $row->status_changed_at),
                $row->location_timezone === null ? null : (string) $row->location_timezone,
                $linesByOrder[(int) $row->id] ?? [],
            );
        }

        return $summaries;
    }

    /**
     * @return list<string>
     */
    private function allergensOf(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_map(strval(...), $raw));
        }

        if (! is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            // Bozuk bir alerjen kopyası BOŞ döner, ham metin olarak
            // basılmaz: mutfak ekranında okunamayan bir uyarı, uyarı
            // olmadığını sandıran bir uyarıdır.
            return [];
        }

        return array_values(array_map(strval(...), $decoded));
    }
}
