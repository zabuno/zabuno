<?php

declare(strict_types=1);

namespace App\Infrastructure\MenuCatalog\Persistence;

use App\Application\MenuCatalog\Dto\CategorySummary;
use App\Application\MenuCatalog\Dto\MenuDraftSummary;
use App\Application\MenuCatalog\Dto\MenuDraftTree;
use App\Application\MenuCatalog\Dto\MenuItemSummary;
use App\Application\MenuCatalog\Dto\ProductSummary;
use App\Application\MenuCatalog\Dto\TaxonomyTermSummary;
use App\Application\MenuCatalog\Exception\DuplicateLocationMenuException;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Money\Money;
use App\Domain\Publication\MenuPublicAddress;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentMenuCatalogRepository implements MenuCatalogRepositoryPort
{
    public function createDraftMenu(int $workspaceId, int $locationId, string $name): MenuDraftSummary
    {
        $location = DB::table('locations')->where('id', $locationId)->first();

        if ($location === null || (int) $location->workspace_id !== $workspaceId) {
            throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
        }

        $row = [
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => $name,
            // Menü doğduğu anda kalıcı adresini alır. Sonradan atamak,
            // adresi olmayan bir menünün var olabileceği bir pencere
            // bırakırdı — ve o pencerede yayınlanırsa adres kayar.
            'public_key' => MenuPublicAddress::generateKey(),
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            // İç içe işlem = SAVEPOINT. PostgreSQL'de başarısız bir INSERT
            // içinde bulunduğu işlemin TAMAMINI zehirler (SQLSTATE 25P02):
            // sonraki her sorgu, işlem kapanana kadar reddedilir. SQLite
            // böyle davranmadığı için bu desen yıllarca çalışıyor göründü.
            // Savepoint, başarısızlığı yalnız kendi kapsamına geri sarar.
            $id = (int) DB::transaction(
                static fn (): int => (int) DB::table('menus')->insertGetId($row)
            );
        } catch (QueryException $e) {
            throw DuplicateLocationMenuException::forLocation($locationId);
        }

        return new MenuDraftSummary($id, $workspaceId, $locationId, $name, 'draft');
    }

    public function getDraftTree(int $workspaceId, int $menuId): ?MenuDraftTree
    {
        $menu = DB::table('menus')->where('id', $menuId)->where('workspace_id', $workspaceId)->first();

        if ($menu === null) {
            return null;
        }

        $categories = DB::table('menu_categories')
            ->where('menu_id', $menuId)
            ->orderBy('position')
            ->get();

        $categoryTrees = [];

        foreach ($categories as $category) {
            $items = DB::table('menu_items')
                ->join('products', 'products.id', '=', 'menu_items.product_id')
                ->where('menu_items.category_id', $category->id)
                ->orderBy('menu_items.position')
                ->select(
                    'menu_items.id as id',
                    'menu_items.product_id as product_id',
                    'products.name as product_name',
                    'menu_items.price_minor_amount as price_minor_amount',
                    'menu_items.currency_code as currency_code',
                    'menu_items.position as position',
                    'menu_items.is_visible as is_visible',
                )
                ->get();

            $itemTrees = [];

            foreach ($items as $item) {
                $allergens = DB::table('product_allergens')
                    ->join('taxonomy_terms', 'taxonomy_terms.id', '=', 'product_allergens.taxonomy_term_id')
                    ->where('product_allergens.product_id', $item->product_id)
                    ->pluck('taxonomy_terms.name')
                    ->values()
                    ->all();

                $itemTrees[] = [
                    'id' => (int) $item->id,
                    'productId' => (int) $item->product_id,
                    'productName' => (string) $item->product_name,
                    'priceMinorAmount' => (int) $item->price_minor_amount,
                    'currencyCode' => (string) $item->currency_code,
                    'position' => (int) $item->position,
                    'isVisible' => (bool) $item->is_visible,
                    'allergens' => array_values(array_map(static fn ($name): string => (string) $name, $allergens)),
                ];
            }

            $categoryTrees[] = [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'position' => (int) $category->position,
                'items' => $itemTrees,
            ];
        }

        return new MenuDraftTree(
            (int) $menu->id,
            (int) $menu->workspace_id,
            (int) $menu->location_id,
            (string) $menu->name,
            (string) $menu->state,
            $categoryTrees,
        );
    }

    public function addCategory(int $workspaceId, int $menuId, string $name): CategorySummary
    {
        $menu = DB::table('menus')->where('id', $menuId)->first();

        if ($menu === null || (int) $menu->workspace_id !== $workspaceId) {
            throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
        }

        $position = (int) DB::table('menu_categories')->where('menu_id', $menuId)->count();

        $id = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => $name,
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new CategorySummary($id, $menuId, $name, $position);
    }

    public function createProduct(int $workspaceId, string $name): ProductSummary
    {
        $id = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new ProductSummary($id, $workspaceId, $name);
    }

    public function addMenuItem(int $workspaceId, int $categoryId, int $productId, int $priceMinorAmount, string $currencyCode): MenuItemSummary
    {
        return DB::transaction(function () use ($workspaceId, $categoryId, $productId, $priceMinorAmount, $currencyCode): MenuItemSummary {
            $category = DB::table('menu_categories')
                ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
                ->where('menu_categories.id', $categoryId)
                ->select('menu_categories.id as id', 'menus.workspace_id as workspace_id')
                ->first();

            if ($category === null || (int) $category->workspace_id !== $workspaceId) {
                throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
            }

            $product = DB::table('products')->where('id', $productId)->first();

            if ($product === null || (int) $product->workspace_id !== $workspaceId) {
                throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
            }

            $money = Money::fromMinorAmount($priceMinorAmount, $currencyCode);

            $position = (int) DB::table('menu_items')->where('category_id', $categoryId)->count();

            $id = (int) DB::table('menu_items')->insertGetId([
                'category_id' => $categoryId,
                'product_id' => $productId,
                'price_minor_amount' => $money->minorAmount(),
                'currency_code' => $money->currencyCode(),
                'is_visible' => false,
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return new MenuItemSummary($id, $categoryId, $productId, $money->minorAmount(), $money->currencyCode(), $position, false);
        });
    }

    public function updateMenuItemPrice(int $workspaceId, int $menuItemId, int $priceMinorAmount, string $currencyCode): MenuItemSummary
    {
        return DB::transaction(function () use ($workspaceId, $menuItemId, $priceMinorAmount, $currencyCode): MenuItemSummary {
            $menuItem = DB::table('menu_items')
                ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
                ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
                ->where('menu_items.id', $menuItemId)
                ->select(
                    'menus.workspace_id as workspace_id',
                    'menu_items.category_id as category_id',
                    'menu_items.product_id as product_id',
                    'menu_items.position as position',
                    'menu_items.is_visible as is_visible',
                )
                ->first();

            if ($menuItem === null || (int) $menuItem->workspace_id !== $workspaceId) {
                throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
            }

            $money = Money::fromMinorAmount($priceMinorAmount, $currencyCode);

            DB::table('menu_items')->where('id', $menuItemId)->update([
                'price_minor_amount' => $money->minorAmount(),
                'currency_code' => $money->currencyCode(),
                'updated_at' => now(),
            ]);

            return new MenuItemSummary(
                $menuItemId,
                (int) $menuItem->category_id,
                (int) $menuItem->product_id,
                $money->minorAmount(),
                $money->currencyCode(),
                (int) $menuItem->position,
                (bool) $menuItem->is_visible,
            );
        });
    }

    public function updateMenuItemVisibility(int $workspaceId, int $menuItemId, bool $isVisible): MenuItemSummary
    {
        return DB::transaction(function () use ($workspaceId, $menuItemId, $isVisible): MenuItemSummary {
            $menuItem = DB::table('menu_items')
                ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
                ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
                ->where('menu_items.id', $menuItemId)
                ->select(
                    'menus.workspace_id as workspace_id',
                    'menu_items.category_id as category_id',
                    'menu_items.product_id as product_id',
                    'menu_items.price_minor_amount as price_minor_amount',
                    'menu_items.currency_code as currency_code',
                    'menu_items.position as position',
                )
                ->first();

            if ($menuItem === null || (int) $menuItem->workspace_id !== $workspaceId) {
                throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
            }

            DB::table('menu_items')->where('id', $menuItemId)->update([
                'is_visible' => $isVisible,
                'updated_at' => now(),
            ]);

            return new MenuItemSummary(
                $menuItemId,
                (int) $menuItem->category_id,
                (int) $menuItem->product_id,
                (int) $menuItem->price_minor_amount,
                (string) $menuItem->currency_code,
                (int) $menuItem->position,
                $isVisible,
            );
        });
    }

    public function createTaxonomyTerm(string $name, string $type): TaxonomyTermSummary
    {
        $existing = DB::table('taxonomy_terms')->where('name', $name)->where('type', $type)->first();

        if ($existing !== null) {
            return new TaxonomyTermSummary((int) $existing->id, $name, $type);
        }

        $id = (int) DB::table('taxonomy_terms')->insertGetId([
            'name' => $name,
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new TaxonomyTermSummary($id, $name, $type);
    }

    public function attachAllergenToProduct(int $workspaceId, int $productId, int $taxonomyTermId): void
    {
        $product = DB::table('products')->where('id', $productId)->first();

        if ($product === null || (int) $product->workspace_id !== $workspaceId) {
            throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
        }

        $term = DB::table('taxonomy_terms')->where('id', $taxonomyTermId)->first();

        if ($term === null || (string) $term->type !== 'allergen') {
            throw new InvalidArgumentException('Only taxonomy terms of type [allergen] can be attached to a product.');
        }

        $exists = DB::table('product_allergens')
            ->where('product_id', $productId)
            ->where('taxonomy_term_id', $taxonomyTermId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('product_allergens')->insert([
            'product_id' => $productId,
            'taxonomy_term_id' => $taxonomyTermId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function replaceProductAllergens(int $workspaceId, int $productId, array $allergenNames): array
    {
        return DB::transaction(function () use ($workspaceId, $productId, $allergenNames): array {
            $product = DB::table('products')->where('id', $productId)->first();

            if ($product === null || (int) $product->workspace_id !== $workspaceId) {
                throw MenuCatalogTenantMismatchException::forWorkspace($workspaceId);
            }

            $termIds = [];
            $names = [];

            foreach ($allergenNames as $allergenName) {
                $term = DB::table('taxonomy_terms')->where('name', $allergenName)->where('type', 'allergen')->first();

                if ($term === null) {
                    $termId = (int) DB::table('taxonomy_terms')->insertGetId([
                        'name' => $allergenName,
                        'type' => 'allergen',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $termId = (int) $term->id;
                }

                if (! in_array($termId, $termIds, true)) {
                    $termIds[] = $termId;
                    $names[] = $allergenName;
                }
            }

            DB::table('product_allergens')->where('product_id', $productId)->delete();

            if ($termIds !== []) {
                $now = now();
                DB::table('product_allergens')->insert(array_map(
                    static fn (int $termId): array => [
                        'product_id' => $productId,
                        'taxonomy_term_id' => $termId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $termIds,
                ));
            }

            return $names;
        });
    }
}
