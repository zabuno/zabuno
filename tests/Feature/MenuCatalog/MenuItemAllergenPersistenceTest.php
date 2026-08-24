<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Blind RED integration candidate for the Menu Catalog persistence P1 slice
 * — allergen persistence (frozen decision per task instruction: a
 * taxonomy_terms row must carry type='allergen' before it can be attached
 * to a product via the product_allergens pivot table; attaching a
 * non-allergen-typed term is rejected with InvalidArgumentException and
 * writes no pivot row; attaching the same allergen term to the same
 * product twice is idempotent — the pivot never duplicates a
 * (product_id, taxonomy_term_id) pair, mirroring the domain-level guard
 * already covered by tests/Unit/MenuCatalog/MenuAggregateInvariantsTest).
 *
 * None of the MenuCatalogRepositoryPort contract or the underlying
 * products/taxonomy_terms/product_allergens tables exist yet, so every
 * test below is expected to fail RED with a binding-resolution error or
 * missing-table QueryException, not a logic assertion failure. See
 * MenuDraftPersistenceTest for the full frozen contract this file
 * exercises.
 *
 * Requirement IDs: MENU-PERSIST-ALLERGEN-TYPE-GUARD-01,
 * MENU-PERSIST-ALLERGEN-TYPE-GUARD-NO-PARTIAL-WRITE-01,
 * MENU-PERSIST-ALLERGEN-PIVOT-DEDUPLICATED-01.
 */
final class MenuItemAllergenPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspace(User $owner, string $slugSeed): int
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

        return $workspaceId;
    }

    private function repository(): MenuCatalogRepositoryPort
    {
        return app(MenuCatalogRepositoryPort::class);
    }

    // --- MENU-PERSIST-ALLERGEN-TYPE-GUARD-01 --------------------------------
    // --- MENU-PERSIST-ALLERGEN-TYPE-GUARD-NO-PARTIAL-WRITE-01 ---------------

    public function test_attaching_a_non_allergen_typed_term_is_rejected_and_writes_no_pivot_row(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspace($owner, 'zeytin-allergen-type-guard');
        $repository = $this->repository();

        $product = $repository->createProduct($workspaceId, 'Mercimek Çorbası');
        $notAnAllergenTerm = $repository->createTaxonomyTerm('floor-1', 'floor-area');

        try {
            $repository->attachAllergenToProduct($workspaceId, $product->id, $notAnAllergenTerm->id);
            self::fail('ALLERGEN-TYPE-GUARD-01: allergen olmayan bir taxonomy term\'i eklemek reddedilmeli.');
        } catch (InvalidArgumentException) {
            // expected
        }

        self::assertSame(
            0,
            DB::table('product_allergens')->where('product_id', $product->id)->count(),
            'ALLERGEN-TYPE-GUARD-NO-PARTIAL-WRITE-01: reddedilen attach pivot tabloya satır yazmamalı.'
        );
    }

    public function test_attaching_an_allergen_typed_term_succeeds_and_writes_one_pivot_row(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspace($owner, 'zeytin-allergen-attach-success');
        $repository = $this->repository();

        $product = $repository->createProduct($workspaceId, 'Mercimek Çorbası');
        $glutenTerm = $repository->createTaxonomyTerm('gluten', 'allergen');

        $repository->attachAllergenToProduct($workspaceId, $product->id, $glutenTerm->id);

        self::assertSame(1, DB::table('product_allergens')
            ->where('product_id', $product->id)
            ->where('taxonomy_term_id', $glutenTerm->id)
            ->count());
    }

    // --- MENU-PERSIST-ALLERGEN-PIVOT-DEDUPLICATED-01 -------------------------

    public function test_attaching_the_same_allergen_term_twice_does_not_duplicate_the_pivot_row(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspace($owner, 'zeytin-allergen-pivot-dedup');
        $repository = $this->repository();

        $product = $repository->createProduct($workspaceId, 'Mercimek Çorbası');
        $glutenTerm = $repository->createTaxonomyTerm('gluten', 'allergen');

        $repository->attachAllergenToProduct($workspaceId, $product->id, $glutenTerm->id);
        $repository->attachAllergenToProduct($workspaceId, $product->id, $glutenTerm->id);

        self::assertSame(
            1,
            DB::table('product_allergens')
                ->where('product_id', $product->id)
                ->where('taxonomy_term_id', $glutenTerm->id)
                ->count(),
            'ALLERGEN-PIVOT-DEDUPLICATED-01: aynı (product_id, taxonomy_term_id) çifti iki kez eklenirse pivot tekrarlanmamalı.'
        );
    }

    public function test_attaching_two_distinct_allergens_to_the_same_product_writes_two_distinct_pivot_rows(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspace($owner, 'zeytin-allergen-two-distinct');
        $repository = $this->repository();

        $product = $repository->createProduct($workspaceId, 'Mercimek Çorbası');
        $glutenTerm = $repository->createTaxonomyTerm('gluten', 'allergen');
        $milkTerm = $repository->createTaxonomyTerm('milk', 'allergen');

        $repository->attachAllergenToProduct($workspaceId, $product->id, $glutenTerm->id);
        $repository->attachAllergenToProduct($workspaceId, $product->id, $milkTerm->id);

        self::assertSame(2, DB::table('product_allergens')->where('product_id', $product->id)->count());
    }
}
