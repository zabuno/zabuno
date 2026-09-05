<?php

declare(strict_types=1);

namespace App\Infrastructure\Rating\Persistence;

use App\Application\Rating\Port\RatableMenuPort;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menü satırının arkasındaki tabak — `docs/116` §1 (P4/P5).
 *
 * ═══ İKİ SINIR BİRDEN KONTROL EDİLİR ═══
 *
 * Satır hem BU MENÜYE hem BU KİRACIYA ait olmak zorunda. Yalnız menüye
 * baksaydık, kiracı sınırı menünün kendi kaydına bağlı kalırdı; yalnız
 * kiracıya baksaydık, misafir aynı restoranın BAŞKA bir menüsündeki tabağa
 * masadan oy verebilirdi — sabah menüsündeki bir ürüne akşam masasından.
 */
final class EloquentRatableMenu implements RatableMenuPort
{
    public function productForMenuItem(int $workspaceId, int $menuId, int $menuItemId): ?int
    {
        $row = $this->query($workspaceId, $menuId)
            ->where('menu_items.id', $menuItemId)
            ->first();

        return $row === null ? null : (int) $row->product_id;
    }

    public function productsForMenu(int $workspaceId, int $menuId): array
    {
        $map = [];

        foreach ($this->query($workspaceId, $menuId)->get() as $row) {
            $map[(int) $row->menu_item_id] = (int) $row->product_id;
        }

        return $map;
    }

    /**
     * Kolonlar TAKMA ADLA seçilir.
     *
     * `menu_items.id` ile `products.id` aynı sonuç satırında `id` diye
     * çakışır ve sürücüye göre biri diğerini ezer — PostgreSQL'de bu, yanlış
     * ürüne oy yazmak demektir. Ham `DB::raw` yerine açık takma ad
     * kullanılmasının sebebi de bu: takma adsız bir ham ifade aynı çakışmayı
     * sessizce geri getirirdi.
     */
    private function query(int $workspaceId, int $menuId): Builder
    {
        return DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->join('products', 'products.id', '=', 'menu_items.product_id')
            ->where('menu_categories.menu_id', $menuId)
            ->where('products.workspace_id', $workspaceId)
            ->select([
                'menu_items.id as menu_item_id',
                'menu_items.product_id as product_id',
            ]);
    }
}
