<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MenuCatalog\Api;

use App\Application\MenuCatalog\Api\Dto\CategoryApiContext;
use App\Application\MenuCatalog\Api\Dto\MenuApiContext;
use App\Application\MenuCatalog\Api\Dto\MenuItemApiContext;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use Illuminate\Support\Facades\DB;

final class EloquentMenuCatalogApiContext implements MenuCatalogApiContextPort
{
    public function locationWorkspaceId(int $locationId): ?int
    {
        $location = DB::table('locations')->where('id', $locationId)->first();

        return $location === null ? null : (int) $location->workspace_id;
    }

    /**
     * Şubenin ÇIPA menüsü.
     *
     * Şube başına tek menü varken sıralama gereksizdi: hangi satır gelirse
     * gelsin doğru cevaptı. Sahibin 2026-09-05 çoklu menü kararından sonra
     * (`docs/109` §7.1) sırasız bir `first()` şubenin menülerinden RASTGELE
     * birini seçerdi ve seçim sürücüye kalırdı — aynı istek iki farklı
     * cevap verebilirdi. Cevap artık kararlıdır: genel adresi taşıyan menü,
     * o da yoksa şubenin en eski menüsü.
     */
    public function menuIdForLocation(int $workspaceId, int $locationId): ?int
    {
        $menu = DB::table('menus')
            ->where('location_id', $locationId)
            ->where('workspace_id', $workspaceId)
            ->orderByRaw('CASE WHEN public_key IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->first();

        return $menu === null ? null : (int) $menu->id;
    }

    public function menuContext(int $menuId): ?MenuApiContext
    {
        $row = DB::table('menus')
            ->where('id', $menuId)
            ->select('workspace_id', 'location_id', 'name')
            ->first();

        if ($row === null) {
            return null;
        }

        return new MenuApiContext((int) $row->workspace_id, (int) $row->location_id, (string) $row->name);
    }

    public function categoryContext(int $categoryId): ?CategoryApiContext
    {
        $row = DB::table('menu_categories')
            ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
            ->join('locations', 'locations.id', '=', 'menus.location_id')
            ->join('brands', 'brands.id', '=', 'locations.brand_id')
            ->where('menu_categories.id', $categoryId)
            // Ad ve menü kimliği denetim izi için okunur (FF-154): kategori
            // silindikten SONRA ikisi de sorulamaz. Sorgu zaten yapılıyordu;
            // iki sütun daha eklemek fazladan bir gidiş-dönüş açmaz.
            ->select(
                'menus.workspace_id as workspace_id',
                'brands.currency as currency',
                'menus.id as menu_id',
                'menu_categories.name as name',
            )
            ->first();

        if ($row === null) {
            return null;
        }

        return new CategoryApiContext(
            (int) $row->workspace_id,
            (string) $row->currency,
            (int) $row->menu_id,
            (string) $row->name,
        );
    }

    public function productWorkspaceId(int $productId): ?int
    {
        $product = DB::table('products')->where('id', $productId)->first();

        return $product === null ? null : (int) $product->workspace_id;
    }

    public function menuItemContext(int $menuItemId): ?MenuItemApiContext
    {
        $row = DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
            ->join('locations', 'locations.id', '=', 'menus.location_id')
            ->join('brands', 'brands.id', '=', 'locations.brand_id')
            // Ad ÜRÜNDE durur, satırda değil (`RenameMenuItemController`).
            ->join('products', 'products.id', '=', 'menu_items.product_id')
            ->where('menu_items.id', $menuItemId)
            /*
                Satırın O ANKİ hâli de okunur (FF-154). Denetim izinde
                "öncesi" olmadan bir fiyat kaydı işe yaramaz ve öncesi
                yalnız YAZMADAN ÖNCE okunabilir. Sorgu zaten yapılıyordu;
                sütun eklemek fazladan bir gidiş-dönüş açmaz.
            */
            ->select(
                'menus.workspace_id as workspace_id',
                'menu_items.product_id as product_id',
                'brands.currency as brand_currency',
                'menus.id as menu_id',
                'products.name as product_name',
                'menu_items.price_minor_amount as price_minor_amount',
                'menu_items.currency_code as currency_code',
                'menu_items.is_visible as is_visible',
            )
            ->first();

        if ($row === null) {
            return null;
        }

        return new MenuItemApiContext(
            (int) $row->workspace_id,
            (int) $row->product_id,
            (string) $row->brand_currency,
            (int) $row->menu_id,
            (string) $row->product_name,
            (int) $row->price_minor_amount,
            (string) $row->currency_code,
            (bool) $row->is_visible,
        );
    }

    public function allergensForProduct(int $productId): array
    {
        return DB::table('product_allergens')
            ->join('taxonomy_terms', 'taxonomy_terms.id', '=', 'product_allergens.taxonomy_term_id')
            ->where('product_allergens.product_id', $productId)
            ->pluck('taxonomy_terms.name')
            ->map(static fn ($name): string => (string) $name)
            ->values()
            ->all();
    }
}
