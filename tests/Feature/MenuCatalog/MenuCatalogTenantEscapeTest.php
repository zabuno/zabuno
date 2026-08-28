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
 * — cross-workspace/location tenant isolation (frozen decision per task
 * instruction: every repository read/write in
 * App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort takes an
 * explicit $workspaceId and must not let a caller read or mutate a menu,
 * category, product or menu item owned by a different workspace; reads
 * return null uniformly — mirroring
 * App\Application\Tenancy\Port\WorkspaceRepositoryPort::findEligibleForUser
 * — while writes throw
 * App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
 * addMenuItem must reject atomically — no menu_items row written — when the
 * given category belongs to a different workspace than the given
 * $workspaceId, even if the product itself is workspace-local).
 *
 * None of the port, its exception, its Eloquent implementation, or the
 * underlying tables exist yet, so every test below is expected to fail RED
 * with a class-not-found / binding-resolution error, not a logic assertion
 * failure. See MenuDraftPersistenceTest for the full frozen contract this
 * file exercises.
 *
 * Requirement IDs: MENU-PERSIST-TENANT-ESCAPE-READ-01,
 * MENU-PERSIST-TENANT-ESCAPE-WRITE-CATEGORY-01,
 * MENU-PERSIST-TENANT-ESCAPE-WRITE-ITEM-FOREIGN-CATEGORY-01,
 * MENU-PERSIST-TENANT-ESCAPE-WRITE-ITEM-ATOMIC-01,
 * MENU-PERSIST-TENANT-ESCAPE-ALLERGEN-01.
 */
final class MenuCatalogTenantEscapeTest extends TestCase
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
            'timezone' => 'Europe/Istanbul',
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

    // --- MENU-PERSIST-TENANT-ESCAPE-READ-01 ---------------------------------

    public function test_reading_a_draft_tree_with_a_foreign_workspace_id_returns_null(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA, $locationA] = $this->workspaceWithLocation($ownerA, 'zeytin-escape-read-a');
        [$workspaceB] = $this->workspaceWithLocation($ownerB, 'zeytin-escape-read-b');
        $repository = $this->repository();

        $menu = $repository->createDraftMenu($workspaceA, $locationA, 'Ana Menü');

        $tree = $repository->getDraftTree($workspaceB, $menu->id);

        self::assertNull($tree, 'TENANT-ESCAPE-READ-01: yabancı workspace kimliğiyle draft tree okunamamalı, null dönmeli.');
    }

    // --- MENU-PERSIST-TENANT-ESCAPE-WRITE-CATEGORY-01 -----------------------

    public function test_adding_a_category_to_a_foreign_workspaces_menu_is_rejected(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA, $locationA] = $this->workspaceWithLocation($ownerA, 'zeytin-escape-write-cat-a');
        [$workspaceB] = $this->workspaceWithLocation($ownerB, 'zeytin-escape-write-cat-b');
        $repository = $this->repository();

        $menu = $repository->createDraftMenu($workspaceA, $locationA, 'Ana Menü');

        $this->expectException(MenuCatalogTenantMismatchException::class);

        $repository->addCategory($workspaceB, $menu->id, 'Yabancı Kategori');
    }

    // --- MENU-PERSIST-TENANT-ESCAPE-WRITE-ITEM-FOREIGN-CATEGORY-01 ----------
    // --- MENU-PERSIST-TENANT-ESCAPE-WRITE-ITEM-ATOMIC-01 --------------------

    public function test_adding_a_menu_item_against_a_foreign_workspaces_category_is_rejected_without_writing_a_row(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA, $locationA] = $this->workspaceWithLocation($ownerA, 'zeytin-escape-write-item-a');
        [$workspaceB, $locationB] = $this->workspaceWithLocation($ownerB, 'zeytin-escape-write-item-b');
        $repository = $this->repository();

        $menuA = $repository->createDraftMenu($workspaceA, $locationA, 'A Menü');
        $categoryA = $repository->addCategory($workspaceA, $menuA->id, 'A Kategori');

        $menuB = $repository->createDraftMenu($workspaceB, $locationB, 'B Menü');
        $repository->addCategory($workspaceB, $menuB->id, 'B Kategori');
        $productB = $repository->createProduct($workspaceB, 'B Ürünü');

        $beforeCount = DB::table('menu_items')->count();

        try {
            $repository->addMenuItem($workspaceB, $categoryA->id, $productB->id, 1000, 'TRY');
            self::fail('TENANT-ESCAPE-WRITE-ITEM-FOREIGN-CATEGORY-01: yabancı workspace\'in kategorisine menu item eklemek reddedilmeli.');
        } catch (MenuCatalogTenantMismatchException) {
            // expected
        }

        self::assertSame(
            $beforeCount,
            DB::table('menu_items')->count(),
            'TENANT-ESCAPE-WRITE-ITEM-ATOMIC-01: reddedilen çapraz-tenant item ekleme menu_items tablosuna satır yazmamalı (atomic rejection).'
        );
    }

    // --- MENU-PERSIST-TENANT-ESCAPE-ALLERGEN-01 ------------------------------

    public function test_attaching_an_allergen_to_a_foreign_workspaces_product_is_rejected(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        [$workspaceA] = $this->workspaceWithLocation($ownerA, 'zeytin-escape-allergen-a');
        [$workspaceB] = $this->workspaceWithLocation($ownerB, 'zeytin-escape-allergen-b');
        $repository = $this->repository();

        $productA = $repository->createProduct($workspaceA, 'A Ürünü');
        $term = $repository->createTaxonomyTerm('gluten', 'allergen');

        $this->expectException(MenuCatalogTenantMismatchException::class);

        $repository->attachAllergenToProduct($workspaceB, $productA->id, $term->id);
    }
}
