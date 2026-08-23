<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED HTTP-delivery candidate for the one-menu-per-location invariant
 * of the POST .../menu route in the S1-WP03 Menu Catalog API P2 slice (see
 * MenuApiJourneyTest for the frozen route + JSON contract this file
 * assumes; frozen scope per task instruction: the menus.location_id column
 * carries a unique constraint at the DB layer — see
 * database/migrations/2026_08_20_000002_create_menu_catalog_tables.php and
 * tests/Feature/MenuCatalog/MenuOnePerLocationConstraintTest.php for the P1
 * repository-level coverage of that same invariant — and the HTTP layer
 * must translate the resulting persistence-layer rejection into a stable
 * JSON error response, not a 500, on every repeated attempt against the
 * same location; frozen contract for this file: exactly one of 422 or 409
 * is accepted, and the SAME status code plus a "message" key are returned
 * both on a second attempt and on subsequent repeated attempts).
 *
 * The POST .../menu route is not registered in routes/api.php, so every
 * request below is expected to fail RED with a 404 route-not-found
 * response before any one-per-location logic runs — not a logic assertion
 * failure or a syntax/bootstrap defect in this suite.
 *
 * Requirement IDs: MENU-API-ONE-PER-LOCATION-STABLE-01,
 * MENU-API-ONE-PER-LOCATION-NO-EXTRA-ROW-01,
 * MENU-API-ONE-PER-LOCATION-DIFFERENT-LOCATION-OK-01.
 */
final class MenuApiOnePerLocationTest extends TestCase
{
    use RefreshDatabase;

    private const STABLE_CONFLICT_STATUSES = [422, 409];

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * @return array{0: int, 1: int} [workspaceId, locationId]
     */
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

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    // --- MENU-API-ONE-PER-LOCATION-STABLE-01 --------------------------------

    public function test_creating_a_second_menu_for_the_same_location_gets_a_stable_422_or_409_json_response(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-one-per-location');
        $uri = "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu";

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson($uri, ['name' => 'Ana Menü']);
        $first->assertStatus(201, 'ONE-PER-LOCATION-STABLE-01: aynı location için ilk menu draft oluşturma başarılı olmalı.');

        $second = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson($uri, ['name' => 'İkinci Menü']);
        self::assertContains(
            $second->getStatusCode(),
            self::STABLE_CONFLICT_STATUSES,
            'ONE-PER-LOCATION-STABLE-01: aynı location için ikinci menu oluşturma denemesi 422 veya 409 ile reddedilmeli, 500 değil.'
        );
        $second->assertJsonStructure(['message']);

        $third = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson($uri, ['name' => 'Üçüncü Menü']);
        self::assertSame(
            $second->getStatusCode(),
            $third->getStatusCode(),
            'ONE-PER-LOCATION-STABLE-01: tekrarlanan reddedilen denemeler her seferinde AYNI status kodunu dönmeli (stabil davranış).'
        );
        $third->assertJsonStructure(['message']);
    }

    // --- MENU-API-ONE-PER-LOCATION-NO-EXTRA-ROW-01 --------------------------

    public function test_a_rejected_second_menu_attempt_writes_no_additional_menus_row(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-one-per-location-no-row');
        $uri = "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu";

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson($uri, ['name' => 'Ana Menü'])->assertStatus(201);

        $countAfterFirst = DB::table('menus')->where('location_id', $locationId)->count();
        self::assertSame(1, $countAfterFirst);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson($uri, ['name' => 'İkinci Menü']);

        $countAfterSecond = DB::table('menus')->where('location_id', $locationId)->count();
        self::assertSame(
            $countAfterFirst,
            $countAfterSecond,
            'ONE-PER-LOCATION-NO-EXTRA-ROW-01: reddedilen ikinci deneme menus tablosuna ek satır yazmamalı.'
        );
    }

    // --- MENU-API-ONE-PER-LOCATION-DIFFERENT-LOCATION-OK-01 -----------------

    public function test_creating_a_menu_for_a_different_location_in_the_same_workspace_still_succeeds(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationOneId] = $this->workspaceWithLocation($owner, 'zeytin-one-per-location-multi-1');

        $brandId = (int) DB::table('brands')->where('workspace_id', $workspaceId)->value('id');
        $locationTwoId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Beşiktaş Şubesi',
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Barbaros Blv. No:2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationOneId}/menu",
            ['name' => 'Kadıköy Menüsü']
        )->assertStatus(201);

        $secondLocationResponse = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationTwoId}/menu",
            ['name' => 'Beşiktaş Menüsü']
        );

        $secondLocationResponse->assertStatus(201, 'ONE-PER-LOCATION-DIFFERENT-LOCATION-OK-01: farklı bir location için menu oluşturma kısıtlanmamalı.');
        $secondLocationResponse->assertJsonPath('locationId', $locationTwoId);
    }
}
