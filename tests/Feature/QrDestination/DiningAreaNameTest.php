<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * AREA-NAME-01 — FF-123, sahibin cümlesi (2026-09-04):
 * "[salon üst kat, salon içerisi, salon bahçe] gibi seçenekleri seçtikten
 * sonra export edebilmeliyim."
 *
 * Toplu üretim bölümleri "Area 1", "Area 2" diye açıyor ve bu bir YER
 * TUTUCUDUR: hiçbir restoran sahibi salonunu böyle adlandırmaz. Kart basarken
 * alanı seçen kişi kendi kullandığı adı görmeli; yoksa hangi "Area"nın bahçe
 * olduğunu hatırlamak zorunda kalır ve yanlış kartları bastırır.
 */
final class DiningAreaNameTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    public function test_the_owner_sees_every_area_with_how_many_tables_it_holds(): void
    {
        [$owner, $workspaceId, $locationId] = $this->locationWithAreas();

        $areas = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson($this->areasUrl($workspaceId, $locationId))
            ->assertStatus(200)
            ->json();

        self::assertCount(2, $areas);
        // Masa sayısı, yeniden adlandırırken hangi alan olduğunu hatırlatır.
        self::assertSame(2, $areas[0]['tableCount']);
        self::assertSame('Area 1', $areas[0]['label']);
    }

    public function test_the_owner_writes_their_own_name_and_the_qr_list_shows_it(): void
    {
        [$owner, $workspaceId, $locationId] = $this->locationWithAreas();

        $areaId = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson($this->areasUrl($workspaceId, $locationId))->json()[0]['id'];

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson($this->areasUrl($workspaceId, $locationId)."/{$areaId}", ['label' => 'Bahçe'])
            ->assertStatus(200)
            ->assertJson(['label' => 'Bahçe']);

        // Ad, kartların basıldığı yerde de görünür.
        $codes = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes")
            ->json();

        $labels = array_values(array_unique(array_column($codes, 'areaLabel')));
        sort($labels);

        self::assertSame(['Area 2', 'Bahçe'], $labels);
    }

    public function test_renaming_never_breaks_a_printed_card(): void
    {
        /*
            Ad DEĞİŞİR ama kimlik değişmez. Basılı kartlar alanın adına değil
            kendi token'ına bağlıdır; yeniden adlandırmak masadaki hiçbir kartı
            bozmaz. Bu ürünün en güçlü vaadi ve burada da tutulmalı.
        */
        [$owner, $workspaceId, $locationId] = $this->locationWithAreas();

        $before = DB::table('qr_codes')->where('location_id', $locationId)->pluck('token')->sort()->values()->all();

        $areaId = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson($this->areasUrl($workspaceId, $locationId))->json()[0]['id'];

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson($this->areasUrl($workspaceId, $locationId)."/{$areaId}", ['label' => 'Üst kat'])
            ->assertStatus(200);

        $after = DB::table('qr_codes')->where('location_id', $locationId)->pluck('token')->sort()->values()->all();

        self::assertSame($before, $after);
    }

    public function test_a_blank_name_is_refused(): void
    {
        // Adsız bir bölüm, seçicide boş bir düğme olurdu.
        [$owner, $workspaceId, $locationId] = $this->locationWithAreas();

        $areaId = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson($this->areasUrl($workspaceId, $locationId))->json()[0]['id'];

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson($this->areasUrl($workspaceId, $locationId)."/{$areaId}", ['label' => '   '])
            ->assertStatus(422);
    }

    public function test_an_area_from_another_location_cannot_be_renamed(): void
    {
        [$owner, $workspaceId, $locationId] = $this->locationWithAreas();
        [$other, $otherWorkspaceId, $otherLocationId] = $this->locationWithAreas();

        $foreignAreaId = $this->actingAs($other)->withHeaders(['Accept' => 'application/json'])
            ->getJson($this->areasUrl($otherWorkspaceId, $otherLocationId))->json()[0]['id'];

        // Kimliği bilen biri başka şubenin bölümünü yeniden adlandıramaz.
        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson($this->areasUrl($workspaceId, $locationId)."/{$foreignAreaId}", ['label' => 'Ele geçti'])
            ->assertStatus(404);

        self::assertDatabaseMissing('dining_areas', ['label' => 'Ele geçti']);
    }

    public function test_a_stranger_sees_nothing(): void
    {
        [, $workspaceId, $locationId] = $this->locationWithAreas();
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->withHeaders(['Accept' => 'application/json'])
            ->getJson($this->areasUrl($workspaceId, $locationId))
            ->assertStatus(404);
    }

    private function areasUrl(int $workspaceId, int $locationId): string
    {
        return "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/dining-areas";
    }

    /** @return array{0: User, 1: int, 2: int} */
    private function locationWithAreas(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kebapçı', 'slug' => 'area-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Kebapçı Ali', 'slug' => 'kebap-'.uniqid(),
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId, 'display_name' => 'Merkez',
            'country_code' => 'TR', 'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Adres', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId, 'menu_id' => $menuId, 'location_id' => $locationId,
            'version' => 1, 'state' => 'published', 'snapshot' => json_encode(['categories' => []]),
            'published_by' => $owner->id, 'published_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId, 'current_publication_id' => $publicationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->grantEntitlements($workspaceId);

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk",
            ['menuId' => $menuId, 'areaSectionCount' => 2, 'tableCount' => 4, 'seatCountPerTable' => 4],
        )->assertStatus(201);

        return [$owner, $workspaceId, $locationId];
    }
}
