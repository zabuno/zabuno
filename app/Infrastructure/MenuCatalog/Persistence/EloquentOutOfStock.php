<?php

declare(strict_types=1);

namespace App\Infrastructure\MenuCatalog\Persistence;

use App\Application\MenuCatalog\Port\OutOfStockPort;
use App\Domain\MenuCatalog\StockState;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentOutOfStock implements OutOfStockPort
{
    public function forMenu(int $menuId): array
    {
        $rows = DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
            ->join('locations', 'locations.id', '=', 'menus.location_id')
            ->where('menu_categories.menu_id', $menuId)
            ->whereNotNull('menu_items.out_of_stock_since')
            ->select([
                'menu_items.id as id',
                'menu_items.out_of_stock_since as since',
                // "Bugün" ŞUBENİN saat diliminde bir gündür: İstanbul'da
                // gece yarısı, sunucunun UTC'sinde henüz dündür.
                'locations.timezone as timezone',
            ])
            ->get();

        return $rows
            ->filter(fn (object $row): bool => StockState::isOutOfStockNow(
                (string) $row->since,
                (string) $row->timezone,
                // Test saati (`Carbon::setTestNow`) burada domaine
                // TAŞINIR: domain saati kendi okusaydı, "yarın ne olur"
                // sorusu sınanamazdı.
                new DateTimeImmutable(Carbon::now()->toIso8601String()),
            ))
            ->map(static fn (object $row): int => (int) $row->id)
            ->values()
            ->all();
    }
}
