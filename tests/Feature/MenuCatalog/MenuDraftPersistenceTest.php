<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED integration candidate for the Menu Catalog persistence P1 slice
 * (frozen application/persistence contract per task instruction: a Menu
 * draft is created for a workspace + location and starts in state
 * 'draft'; Categories belong to a Menu; Products are workspace-scoped
 * catalog identities; MenuItems wrap a Product with an independent
 * Money price inside a Category; the full draft tree — menu, its
 * categories, each category's menu items, each item's product name/price
 * and attached allergen names — must be readable back through one
 * repository call).
 *
 * None of App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort or its
 * Eloquent implementation exist yet, and the menus/menu_categories/
 * products/menu_items/taxonomy_terms/menu_item_allergens tables have no
 * migration, so every test below is expected to fail RED with a
 * binding-resolution error (interface not instantiable) or a missing-table
 * QueryException, not a logic assertion failure or a syntax/bootstrap
 * defect in this suite.
 *
 * Frozen contract under test (see also MenuOnePerLocationConstraintTest,
 * MenuItemPriceIntegrityTest, MenuCatalogTenantEscapeTest,
 * MenuItemAllergenPersistenceTest for the remaining P1 surface):
 *
 *   App\Application\MenuCatalog\Dto\MenuDraftSummary(int $id, int
 *     $workspaceId, int $locationId, string $name, string $state)
 *   App\Application\MenuCatalog\Dto\CategorySummary(int $id, int $menuId,
 *     string $name, int $position)
 *   App\Application\MenuCatalog\Dto\ProductSummary(int $id, int
 *     $workspaceId, string $name)
 *   App\Application\MenuCatalog\Dto\MenuItemSummary(int $id, int
 *     $categoryId, int $productId, int $priceMinorAmount, string
 *     $currencyCode, int $position)
 *   App\Application\MenuCatalog\Dto\MenuDraftTree(int $id, int
 *     $workspaceId, int $locationId, string $name, string $state, array
 *     $categories) where $categories is a list of
 *     ['id'=>int,'name'=>string,'position'=>int,'items'=>list of
 *     ['id'=>int,'productId'=>int,'productName'=>string,
 *      'priceMinorAmount'=>int,'currencyCode'=>string,'position'=>int,
 *      'allergens'=>list<string>]]
 *
 *   interface MenuCatalogRepositoryPort {
 *     createDraftMenu(int $workspaceId, int $locationId, string $name): MenuDraftSummary;
 *     getDraftTree(int $workspaceId, int $menuId): ?MenuDraftTree;
 *     addCategory(int $workspaceId, int $menuId, string $name): CategorySummary;
 *     createProduct(int $workspaceId, string $name): ProductSummary;
 *     addMenuItem(int $workspaceId, int $categoryId, int $productId, int $priceMinorAmount, string $currencyCode): MenuItemSummary;
 *     createTaxonomyTerm(string $name, string $type): TaxonomyTermSummary;
 *     attachAllergenToProduct(int $workspaceId, int $productId, int $taxonomyTermId): void;
 *   }
 *
 * Requirement IDs: MENU-PERSIST-DRAFT-CREATE-01, MENU-PERSIST-DRAFT-STATE-01,
 * MENU-PERSIST-CATEGORY-CREATE-01, MENU-PERSIST-ITEM-CREATE-01,
 * MENU-PERSIST-DRAFT-TREE-READ-01, MENU-PERSIST-DRAFT-TREE-MISSING-01,
 * MENU-PERSIST-DRAFT-CREATE-FOREIGN-LOCATION-01.
 *
 * Reviewer correction handoff (same P1 package): a focused RED case is
 * added below for createDraftMenu(workspaceA, locationOwnedByWorkspaceB) —
 * the location belongs to a different workspace than the one making the
 * call, so this must throw
 * App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException
 * and write no menus row, mirroring the write-side tenant guard already
 * covered for addCategory/addMenuItem/attachAllergenToProduct in
 * MenuCatalogTenantEscapeTest.
 */
final class MenuDraftPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceWithLocation(User $owner, string $slugSeed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Kadıköy Şubesi',
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId];
    }

    private function repository(): MenuCatalogRepositoryPort
    {
        return app(MenuCatalogRepositoryPort::class);
    }

    // --- MENU-PERSIST-DRAFT-CREATE-01 / MENU-PERSIST-DRAFT-STATE-01 --------

    public function test_creating_a_menu_draft_persists_it_scoped_to_workspace_and_location_in_draft_state(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-draft-create');

        $summary = $this->repository()->createDraftMenu($workspaceId, $locationId, 'Ana Menü');

        self::assertSame($workspaceId, $summary->workspaceId, 'DRAFT-CREATE-01: menü doğru workspace\'e bağlı olmalı.');
        self::assertSame($locationId, $summary->locationId, 'DRAFT-CREATE-01: menü doğru location\'a bağlı olmalı.');
        self::assertSame('Ana Menü', $summary->name);
        self::assertSame('draft', $summary->state, 'DRAFT-STATE-01: yeni oluşturulan menü draft durumunda başlamalı.');
        self::assertGreaterThan(0, $summary->id);
        self::assertSame(1, DB::table('menus')->where('id', $summary->id)->count(), 'DRAFT-CREATE-01: menu satırı menus tablosuna yazılmalı.');
    }

    // --- MENU-PERSIST-DRAFT-TREE-READ-01 ------------------------------------

    public function test_the_full_draft_tree_is_readable_back_with_categories_items_and_allergens(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-draft-tree');
        $repository = $this->repository();

        $menu = $repository->createDraftMenu($workspaceId, $locationId, 'Ana Menü');
        $category = $repository->addCategory($workspaceId, $menu->id, 'Çorbalar');
        $product = $repository->createProduct($workspaceId, 'Mercimek Çorbası');
        $term = $repository->createTaxonomyTerm('gluten', 'allergen');
        $repository->attachAllergenToProduct($workspaceId, $product->id, $term->id);
        $item = $repository->addMenuItem($workspaceId, $category->id, $product->id, 4500, 'TRY');

        $tree = $repository->getDraftTree($workspaceId, $menu->id);

        self::assertNotNull($tree, 'DRAFT-TREE-READ-01: tam draft tree tek repository çağrısıyla okunabilmeli.');
        self::assertSame($menu->id, $tree->id);
        self::assertSame('draft', $tree->state);
        self::assertCount(1, $tree->categories);
        self::assertSame($category->id, $tree->categories[0]['id']);
        self::assertSame('Çorbalar', $tree->categories[0]['name']);
        self::assertCount(1, $tree->categories[0]['items']);

        $readItem = $tree->categories[0]['items'][0];
        self::assertSame($item->id, $readItem['id']);
        self::assertSame($product->id, $readItem['productId']);
        self::assertSame('Mercimek Çorbası', $readItem['productName']);
        self::assertSame(4500, $readItem['priceMinorAmount']);
        self::assertSame('TRY', $readItem['currencyCode']);
        self::assertSame(['gluten'], $readItem['allergens']);
    }

    // --- MENU-PERSIST-DRAFT-TREE-MISSING-01 ---------------------------------

    public function test_reading_a_nonexistent_menu_id_returns_null_not_an_exception(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId] = $this->workspaceWithLocation($owner, 'zeytin-draft-missing');

        $tree = $this->repository()->getDraftTree($workspaceId, 999999);

        self::assertNull($tree, 'DRAFT-TREE-MISSING-01: var olmayan menu id\'si için null dönmeli, exception fırlatılmamalı.');
    }

    // --- MENU-PERSIST-DRAFT-CREATE-FOREIGN-LOCATION-01 ----------------------

    public function test_creating_a_draft_menu_against_a_location_owned_by_a_different_workspace_is_rejected_without_writing_a_row(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA] = $this->workspaceWithLocation($ownerA, 'zeytin-draft-foreign-location-a');
        [, $locationB] = $this->workspaceWithLocation($ownerB, 'zeytin-draft-foreign-location-b');

        $beforeCount = DB::table('menus')->count();

        try {
            $this->repository()->createDraftMenu($workspaceA, $locationB, 'Yabancı Location Menüsü');
            self::fail('DRAFT-CREATE-FOREIGN-LOCATION-01: workspaceA, workspaceB\'ye ait bir location için menu draft oluşturamamalı.');
        } catch (MenuCatalogTenantMismatchException) {
            // expected
        }

        self::assertSame(
            $beforeCount,
            DB::table('menus')->count(),
            'DRAFT-CREATE-FOREIGN-LOCATION-01: reddedilen çapraz-tenant create menus tablosuna satır yazmamalı.'
        );
    }
}
