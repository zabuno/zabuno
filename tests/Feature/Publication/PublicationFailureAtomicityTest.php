<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AbortOnInsertFixture;
use Tests\TestCase;

/**
 * S1-WP04a RED — publish failure atomicity: any snapshot persistence /
 * current-pointer failure leaves the last successful publication current
 * and never exposes a partial publication (modules/publication.md
 * "Acceptance": "publish failure durumunda son başarılı sürümün
 * korunduğunun testi"). Forces a controlled failure with a real SQLite
 * BEFORE INSERT trigger on menu_publications that aborts the second insert
 * attempt, rather than replacing any application-port container binding —
 * this proves the actual persistence path is atomic, not a shape-
 * constraining fake.
 *
 * The menu_publications table does not exist yet (no publication migration
 * in this codebase), so creating the trigger itself fails RED before any
 * publish request runs, which is itself the intended RED signal for this
 * contract.
 *
 * Requirement ID: PUB-ATOMICITY-01.
 */
final class PublicationFailureAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function installSecondInsertAbortTrigger(): void
    {
        AbortOnInsertFixture::install(
            's1_wp04a_abort_second_publication',
            'menu_publications',
            'S1-WP04a controlled persistence failure fixture.',
            condition: '(SELECT COUNT(*) FROM menu_publications) >= 1',
        );
    }

    private function dropSecondInsertAbortTrigger(): void
    {
        AbortOnInsertFixture::remove('s1_wp04a_abort_second_publication', 'menu_publications');
    }

    /**
     * @return array{0: int, 1: int} [workspaceId, menuId]
     */
    private function workspaceWithReadyMenu(User $owner, string $slugSeed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$slugSeed,
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
            'name' => 'Marka '.$slugSeed,
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
            'display_name' => 'Şube '.$slugSeed,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$slugSeed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Starters',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Kahve',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 4250,
            'currency_code' => 'TRY',
            'position' => 0,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $menuId];
    }

    // --- PUB-ATOMICITY-01 ---------------------------------------------------

    public function test_a_persistence_failure_on_the_second_publish_leaves_the_first_publication_current_and_exposes_no_partial_state(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $menuId] = $this->workspaceWithReadyMenu($owner, 'atomicity-1');

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        );
        $first->assertStatus(201);
        $firstVersion = $first->json('version');

        $this->installSecondInsertAbortTrigger();

        try {
            $second = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
                "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
            );

            self::assertNotSame(201, $second->getStatusCode(), 'PUB-ATOMICITY-01: kontrollü persistence hatasında 201 dönmemeli.');
            self::assertContains($second->getStatusCode(), [500, 503], 'PUB-ATOMICITY-01: sunucu hatası 500/503 olmalı, sahte 2xx değil.');
        } finally {
            $this->dropSecondInsertAbortTrigger();
        }

        $current = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current"
        );
        $current->assertStatus(200, 'PUB-ATOMICITY-01: başarısız ikinci publish sonrası current hâlâ ilk başarılı sürüm olmalı.');
        $current->assertJsonPath('version', $firstVersion);
        $current->assertJsonPath('state', 'published');

        $publications = DB::table('menu_publications')->where('menu_id', $menuId)->get();
        self::assertCount(1, $publications, 'PUB-ATOMICITY-01: başarısız ikinci publish denemesi kalıcı hiçbir satır bırakmamalı.');
        foreach ($publications as $publication) {
            self::assertNotSame('generating', $publication->state ?? null, 'PUB-ATOMICITY-01: yarım kalmış (generating) publication asla görünür/kalıcı olmamalı.');
        }
    }
}
