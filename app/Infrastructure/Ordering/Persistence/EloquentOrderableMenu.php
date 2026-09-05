<?php

declare(strict_types=1);

namespace App\Infrastructure\Ordering\Persistence;

use App\Application\Ordering\Dto\OrderableLine;
use App\Application\Ordering\Port\OrderableMenuPort;
use App\Domain\MenuCatalog\StockState;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sipariş edilebilir satırlar — kataloğun sunucudaki gerçeği.
 *
 * KİRACI SORGUNUN İÇİNDE. `menu_items` doğrudan bir workspace taşımaz;
 * bağ `menu_categories → menus → workspace_id` üzerinden kurulur ve bu
 * birleştirme `WHERE`'in parçasıdır. Yalnız menü kimliğiyle sorgulasaydık,
 * doğru menü kimliğini bilen biri başka bir kiracının satırını sipariş
 * edebilirdi.
 *
 * "BUGÜN BİTTİ" DÜŞÜRÜLMEZ, İŞARETLENİR. Menüdeki tükenmiş ürün misafire
 * hâlâ görünür (`docs/82`); sipariş yolunda reddedilmesi gereken şey ise
 * onu SEÇMEK'tir. Satırı hiç döndürmeseydik, misafir "bugün bitti" yerine
 * "bu ürün yok" cevabını alırdı — ve iki cümle aynı şey değildir.
 */
final class EloquentOrderableMenu implements OrderableMenuPort
{
    public function linesForMenu(int $workspaceId, int $menuId): array
    {
        $rows = DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
            ->join('products', 'products.id', '=', 'menu_items.product_id')
            ->join('locations', 'locations.id', '=', 'menus.location_id')
            ->where('menus.id', $menuId)
            ->where('menus.workspace_id', $workspaceId)
            // Gizli ürün menüde YOKTUR; sipariş de edilemez.
            ->where('menu_items.is_visible', true)
            ->select([
                'menu_items.id as id',
                'products.id as product_id',
                'products.name as product_name',
                'menu_items.price_minor_amount as price_minor_amount',
                'menu_items.currency_code as currency_code',
                'menu_items.out_of_stock_since as out_of_stock_since',
                // "Bugün" ŞUBENİN saat dilimindeki gündür: İstanbul'da gece
                // yarısı, sunucunun UTC'sinde henüz dündür.
                'locations.timezone as timezone',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $allergens = $this->allergensByProduct(
            $rows->pluck('product_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all(),
        );

        // Test saati (`Carbon::setTestNow`) domaine TAŞINIR: domain saati
        // kendi okusaydı "yarın ne olur" sorusu sınanamazdı.
        $now = new DateTimeImmutable(Carbon::now()->toIso8601String());

        $lines = [];

        foreach ($rows as $row) {
            $menuItemId = (int) $row->id;

            $lines[$menuItemId] = new OrderableLine(
                $menuItemId,
                (string) $row->product_name,
                (int) $row->price_minor_amount,
                (string) $row->currency_code,
                $allergens[(int) $row->product_id] ?? [],
                StockState::isOutOfStockNow(
                    $row->out_of_stock_since === null ? null : (string) $row->out_of_stock_since,
                    (string) $row->timezone,
                    $now,
                ),
            );
        }

        return $lines;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, list<string>>
     */
    private function allergensByProduct(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $grouped = [];

        $rows = DB::table('product_allergens')
            ->join('taxonomy_terms', 'taxonomy_terms.id', '=', 'product_allergens.taxonomy_term_id')
            ->whereIn('product_allergens.product_id', $productIds)
            ->orderBy('taxonomy_terms.name')
            ->select(['product_allergens.product_id as product_id', 'taxonomy_terms.name as name'])
            ->get();

        foreach ($rows as $row) {
            $grouped[(int) $row->product_id][] = (string) $row->name;
        }

        return $grouped;
    }
}
