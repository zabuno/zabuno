<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Application\MenuCatalog\Exception\DuplicateLocationMenuException;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED integration candidate for the Menu Catalog persistence P1 slice
 * — one-menu-per-location constraint (frozen decision per task instruction:
 * `menus.location_id` is unique; a second createDraftMenu call against the
 * same location must fail deterministically at the application layer with
 * App\Application\MenuCatalog\Exception\DuplicateLocationMenuException,
 * not leak an uncaught Illuminate\Database\QueryException from the
 * database's own unique index).
 *
 * Neither the exception class, the port, its Eloquent implementation, nor
 * the migration enforcing the unique constraint exist yet, so every test
 * below is expected to fail RED with a class-not-found / binding-resolution
 * error, not a logic assertion failure. See MenuDraftPersistenceTest for the
 * full frozen contract this file exercises.
 *
 * Requirement IDs: MENU-PERSIST-ONE-PER-LOCATION-01,
 * MENU-PERSIST-ONE-PER-LOCATION-NO-PARTIAL-WRITE-01.
 */
final class MenuOnePerLocationConstraintTest extends TestCase
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

    // --- MENU-PERSIST-ONE-PER-LOCATION-01 -----------------------------------

    public function test_a_second_draft_menu_for_the_same_location_is_rejected_with_a_stable_domain_exception(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-one-per-location');
        $repository = $this->repository();

        $repository->createDraftMenu($workspaceId, $locationId, 'Ana Menü');

        $this->expectException(DuplicateLocationMenuException::class);

        $repository->createDraftMenu($workspaceId, $locationId, 'İkinci Menü');
    }

    // --- MENU-PERSIST-ONE-PER-LOCATION-NO-PARTIAL-WRITE-01 ------------------

    public function test_a_rejected_duplicate_menu_creation_leaves_exactly_one_menu_row_for_the_location(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-one-per-location-no-partial');
        $repository = $this->repository();

        $repository->createDraftMenu($workspaceId, $locationId, 'Ana Menü');

        try {
            $repository->createDraftMenu($workspaceId, $locationId, 'İkinci Menü');
        } catch (DuplicateLocationMenuException) {
            // expected
        }

        self::assertSame(
            1,
            DB::table('menus')->where('location_id', $locationId)->count(),
            'ONE-PER-LOCATION-NO-PARTIAL-WRITE-01: reddedilen ikinci create menus tablosuna satır eklememeli.'
        );
    }

    // --- distinct locations remain unaffected -------------------------------

    public function test_two_different_locations_may_each_have_their_own_draft_menu(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $firstLocationId] = $this->workspaceWithLocation($owner, 'zeytin-one-per-location-distinct-a');
        $brandId = (int) DB::table('brands')->where('workspace_id', $workspaceId)->value('id');
        $secondLocationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Beşiktaş Şubesi',
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Barbaros Blv. No:2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $repository = $this->repository();

        $first = $repository->createDraftMenu($workspaceId, $firstLocationId, 'Kadıköy Menüsü');
        $second = $repository->createDraftMenu($workspaceId, $secondLocationId, 'Beşiktaş Menüsü');

        self::assertNotSame($first->id, $second->id);
        self::assertSame(2, DB::table('menus')->count());
    }
}
