<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MenuCatalog\Api;

use App\Application\MenuCatalog\Api\Dto\CategoryApiContext;
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

    public function menuIdForLocation(int $workspaceId, int $locationId): ?int
    {
        $menu = DB::table('menus')
            ->where('location_id', $locationId)
            ->where('workspace_id', $workspaceId)
            ->first();

        return $menu === null ? null : (int) $menu->id;
    }

    public function categoryContext(int $categoryId): ?CategoryApiContext
    {
        $row = DB::table('menu_categories')
            ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
            ->join('locations', 'locations.id', '=', 'menus.location_id')
            ->join('brands', 'brands.id', '=', 'locations.brand_id')
            ->where('menu_categories.id', $categoryId)
            ->select('menus.workspace_id as workspace_id', 'brands.currency as currency')
            ->first();

        if ($row === null) {
            return null;
        }

        return new CategoryApiContext((int) $row->workspace_id, (string) $row->currency);
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
            ->where('menu_items.id', $menuItemId)
            ->select('menus.workspace_id as workspace_id', 'menu_items.product_id as product_id', 'brands.currency as brand_currency')
            ->first();

        if ($row === null) {
            return null;
        }

        return new MenuItemApiContext((int) $row->workspace_id, (int) $row->product_id, (string) $row->brand_currency);
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
