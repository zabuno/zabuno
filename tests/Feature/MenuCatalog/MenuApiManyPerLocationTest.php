<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\MenuCatalog\Support\MultiMenuScaffold;
use Tests\TestCase;

/**
 * BU DOSYA BİR SÖZLEŞMEYİ DEĞİŞTİRİYOR — NEDENİ BURADA YAZILI.
 *
 * NE SİLİNDİ: `tests/Feature/MenuCatalog/MenuApiOnePerLocationTest.php` ve
 * dondurduğu üç gereksinim — `MENU-API-ONE-PER-LOCATION-STABLE-01`,
 * `-NO-EXTRA-ROW-01`, `-DIFFERENT-LOCATION-OK-01`. O testler POST .../menu
 * yolunun ikinci çağrıda 409/422 dönmesini SÖZLEŞME olarak donduruyordu.
 *
 * NEDEN: **Sahip 2026-09-05'te açıkça soruldu ve "çoklu menü YAPILSIN,
 * saat bazlı geçişli" dedi** (`docs/109-PANEL-V3.md` §7.1). Kaynak
 * `panel.dc.html` menü haplarını gösteriyor: "Ana menü yayında · Kahvaltı
 * 07–11 · Ramazan kapalı". İkinci menüyü reddeden bir yol, o hapların
 * arkasına konacak hiçbir veriye izin vermiyordu.
 *
 * YENİ GEREKSİNİMLER
 * - `MENU-API-MANY-01` — ikinci menü 201 ile yaratılır.
 * - `MENU-API-MENU-LIST-01` — şubenin menüleri tek yerden, sırasıyla ve
 *   saat ipuçlarıyla okunur (ekrandaki hapların kaynağı).
 * - `MENU-API-MENU-TREE-01` — bir hapa basıldığında O MENÜNÜN kategorileri
 *   ve ürünleri gelir; iki menünün içeriği birbirine karışmaz.
 * - `MENU-API-MENU-RENAME-01` / `MENU-API-MENU-DELETE-01` — menü düzenleme
 *   ve silme akışı vardır.
 * - `MENU-API-MENU-LAST-ONE-KEPT-01` — şubenin SON menüsü silinemez;
 *   silinebilseydi misafir boş bir sayfa görürdü.
 */
final class MenuApiManyPerLocationTest extends TestCase
{
    use MultiMenuScaffold;
    use RefreshDatabase;

    /** @return array<string,string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    // --- MENU-API-MANY-01 ---------------------------------------------------

    public function test_a_second_menu_for_the_same_location_is_created_not_rejected(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-many');
        $uri = "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu";

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson($uri, ['name' => 'Ana menü']);
        $first->assertStatus(201);

        $second = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson($uri, ['name' => 'Kahvaltı']);

        $second->assertStatus(201, 'API-MANY-01: aynı şube için ikinci menü artık kabul edilmeli.');
        self::assertNotSame($first->json('id'), $second->json('id'));
        self::assertSame(2, DB::table('menus')->where('location_id', $locationId)->count());
    }

    // --- MENU-API-MENU-LIST-01 ---------------------------------------------

    public function test_the_location_menu_list_carries_name_state_and_the_clock_hint(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-list');
        $createUri = "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu";

        $main = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($createUri, ['name' => 'Ana menü'])->json('id');
        $breakfast = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($createUri, ['name' => 'Kahvaltı'])->json('id');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/menu/{$main}/service-window",
            ['startsAt' => '00:00', 'endsAt' => '00:00'],
        )->assertOk();

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/menu/{$breakfast}/service-window",
            ['startsAt' => '07:00', 'endsAt' => '11:00'],
        )->assertOk();

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menus");

        $response->assertOk();
        $rows = $response->json('data');

        self::assertCount(2, $rows);
        self::assertSame('Ana menü', $rows[0]['name']);
        self::assertSame('Kahvaltı', $rows[1]['name']);
        self::assertSame('active', $rows[1]['state']);
        self::assertSame('07:00', $rows[1]['startsAt'], 'MENU-LIST-01: hapın saat ipucu gerçek veriden gelmeli.');
        self::assertSame('11:00', $rows[1]['endsAt']);
        self::assertTrue($rows[0]['isAddressAnchor'], 'MENU-LIST-01: şubenin adresi ilk menüde durur.');
        self::assertFalse($rows[1]['isAddressAnchor']);
    }

    // --- MENU-API-MENU-TREE-01 ---------------------------------------------

    public function test_each_menu_serves_its_own_categories_and_items(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-tree');

        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);
        $this->fillMenu($workspaceId, $mainId, 'Kebaplar', 'Adana kebap', 42000);
        $this->fillMenu($workspaceId, $breakfastId, 'Kahvaltılıklar', 'Menemen', 18000);

        $tree = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/menu/{$breakfastId}");

        $tree->assertOk();
        $tree->assertJsonPath('id', $breakfastId);
        $tree->assertJsonPath('name', 'Kahvaltı');
        $tree->assertJsonPath('categories.0.name', 'Kahvaltılıklar');
        $tree->assertJsonPath('categories.0.menuItems.0.productName', 'Menemen');
        self::assertCount(1, $tree->json('categories'), 'MENU-TREE-01: bir menü öteki menünün kategorilerini göstermemeli.');
    }

    // --- MENU-API-MENU-RENAME-01 -------------------------------------------

    public function test_a_menu_can_be_renamed(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-rename');
        $menuId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', $this->newPublicKey(), 0);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson("/api/workspaces/{$workspaceId}/menu/{$menuId}", ['name' => 'Serpme kahvaltı'])
            ->assertOk()
            ->assertJsonPath('name', 'Serpme kahvaltı');

        self::assertSame('Serpme kahvaltı', (string) DB::table('menus')->where('id', $menuId)->value('name'));
    }

    // --- MENU-API-MENU-DELETE-01 -------------------------------------------

    public function test_a_menu_can_be_deleted_and_the_printed_address_survives(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-delete');

        $anchorKey = $this->newPublicKey();
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $anchorKey, 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson("/api/workspaces/{$workspaceId}/menu/{$breakfastId}")
            ->assertOk();

        self::assertSame(0, DB::table('menus')->where('id', $breakfastId)->count());
        self::assertSame(
            $anchorKey,
            (string) DB::table('menus')->where('id', $mainId)->value('public_key'),
            'MENU-DELETE-01: silme, basılı koda giden genel adresi kımıldatmamalı.'
        );
    }

    // --- MENU-API-MENU-LAST-ONE-KEPT-01 ------------------------------------

    public function test_the_last_menu_of_a_location_cannot_be_deleted(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-last');
        $menuId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->deleteJson("/api/workspaces/{$workspaceId}/menu/{$menuId}");

        $response->assertStatus(409);
        $response->assertJsonStructure(['message']);
        self::assertSame(
            1,
            DB::table('menus')->where('id', $menuId)->count(),
            'LAST-ONE-KEPT-01: son menü silinseydi misafir boş bir sayfa görürdü.'
        );
    }
}
