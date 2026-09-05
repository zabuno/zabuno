<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
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
 * - `MENU-API-MENU-ORDER-01` — hapların sırası GÜNÜN AKIŞIDIR, oluşturma
 *   sırası değil; saati olmayan menüler sonda, kendi aralarında `sort_order`
 *   ile durur.
 * - `MENU-API-MENU-ORDER-MIDNIGHT-01` — gece yarısını aşan pencere
 *   BAŞLANGICINA göre yerleşir, bitişine göre değil.
 * - `MENU-API-MENU-ORDER-SINGLE-01` — tek menülü şube bugünkü gibi çalışır.
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

    // --- MENU-API-MENU-ORDER-01 --------------------------------------------

    /**
     * SIRA TÜRETİLİR, ELLE VERİLMEZ.
     *
     * Akşam menüsünü önce kuran bir sahip, hapları "Akşam · Kahvaltı" diye
     * okuyordu; oysa gün kahvaltıyla başlar. Ekran o an sahibin GÜNÜNÜ değil,
     * veritabanına yazılma anını gösteriyordu.
     *
     * Bunu bir sürükle-bırak denetimiyle çözmek, saat her değiştiğinde sahibe
     * ikinci bir iş yükler ve iki gerçek (saat ve sıra) bir gün birbirinden
     * ayrılırdı: "Kahvaltı 07–11" yazan hap, akşam menüsünün solunda durmayı
     * sürdürebilirdi. Bu yüzden sıra servis BAŞLANGIÇ dakikasından gelir.
     */
    public function test_the_menu_pills_follow_the_day_not_the_creation_order(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-order');

        // Sahip günü TERS kurar: önce akşamı düşünür, kahvaltıyı sonra.
        $dinner = $this->createMenuViaApi($owner, $workspaceId, $locationId, 'Akşam');
        $breakfast = $this->createMenuViaApi($owner, $workspaceId, $locationId, 'Kahvaltı');
        $lunch = $this->createMenuViaApi($owner, $workspaceId, $locationId, 'Öğle');

        // Saati hiç verilmemiş iki menü: "Ramazan" gelecek yıl geri gelecek,
        // "Bayram" henüz kurulmakta. İkisi de rotasyonun dışında.
        $this->createMenuViaApi($owner, $workspaceId, $locationId, 'Ramazan');
        $this->createMenuViaApi($owner, $workspaceId, $locationId, 'Bayram');

        $this->setServiceWindowViaApi($owner, $workspaceId, $dinner, '18:00', '23:00');
        $this->setServiceWindowViaApi($owner, $workspaceId, $breakfast, '07:00', '11:00');
        $this->setServiceWindowViaApi($owner, $workspaceId, $lunch, '11:00', '18:00');

        $rows = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menus")
            ->assertOk()
            ->json('data');

        self::assertSame(
            ['Kahvaltı', 'Öğle', 'Akşam', 'Ramazan', 'Bayram'],
            array_column($rows, 'name'),
            'ORDER-01: haplar günün akışına göre gelmeli; saatsiz olanlar sonda, kendi aralarında oluşturma sırasıyla.'
        );

        // Sıra `sort_order` DEĞİL: akşam menüsü hâlâ 0'dır ama üçüncü sıradadır.
        self::assertSame(0, $rows[2]['sortOrder']);
        self::assertSame('07:00', $rows[0]['startsAt']);
        self::assertSame('11:00', $rows[1]['startsAt']);
        self::assertSame('18:00', $rows[2]['startsAt']);
        self::assertNull($rows[3]['startsAt'], 'ORDER-01: saatsiz menü saat ipucu uydurmamalı.');
    }

    // --- MENU-API-MENU-ORDER-MIDNIGHT-01 -----------------------------------

    /**
     * Gece menüsü günün BAŞINA düşmez.
     *
     * "22:00–02:00" bitişine göre sıralansaydı gece menüsü kahvaltıdan da
     * önce gelirdi — sahip listeye baktığında gününün geceyle başladığını
     * okurdu. Gün 22:00'de biter; hap da orada durur.
     */
    public function test_a_window_crossing_midnight_sits_by_its_start_not_its_end(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-order-night');

        $night = $this->createMenuViaApi($owner, $workspaceId, $locationId, 'Gece');
        $main = $this->createMenuViaApi($owner, $workspaceId, $locationId, 'Ana menü');

        $this->setServiceWindowViaApi($owner, $workspaceId, $night, '22:00', '02:00');
        $this->setServiceWindowViaApi($owner, $workspaceId, $main, '02:00', '22:00');

        $rows = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menus")
            ->assertOk()
            ->json('data');

        self::assertSame(
            ['Ana menü', 'Gece'],
            array_column($rows, 'name'),
            'ORDER-MIDNIGHT-01: 22:00–02:00 penceresi başlangıcına göre yerleşmeli.'
        );
        self::assertSame('22:00', $rows[1]['startsAt']);
        self::assertSame('02:00', $rows[1]['endsAt']);
    }

    // --- MENU-API-MENU-ORDER-SINGLE-01 -------------------------------------

    /**
     * Tek menülü şube — sahiplerin çoğu buradadır ve onlar için HİÇBİR ŞEY
     * değişmez: sıralamanın devreye girebilmesi için ikinci bir menü gerekir.
     */
    public function test_a_location_with_one_menu_is_untouched_by_the_new_order(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-api-order-single');

        $menuId = $this->createMenuViaApi($owner, $workspaceId, $locationId, 'Ana menü');

        $rows = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menus")
            ->assertOk()
            ->json('data');

        self::assertCount(1, $rows);
        self::assertSame($menuId, $rows[0]['id']);
        self::assertNull($rows[0]['startsAt'], 'ORDER-SINGLE-01: yeni menü kendiliğinden rotasyona girmez.');

        $this->setServiceWindowViaApi($owner, $workspaceId, $menuId, '00:00', '00:00');

        $rows = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menus")
            ->assertOk()
            ->json('data');

        self::assertCount(1, $rows);
        self::assertSame('00:00', $rows[0]['startsAt']);
        self::assertSame('00:00', $rows[0]['endsAt'], 'ORDER-SINGLE-01: tek menü günün tamamını tutar.');
    }

    private function createMenuViaApi(User $owner, int $workspaceId, int $locationId, string $name): int
    {
        return (int) $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu", ['name' => $name])
            ->assertStatus(201)
            ->json('id');
    }

    private function setServiceWindowViaApi(User $owner, int $workspaceId, int $menuId, string $startsAt, string $endsAt): void
    {
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/service-window",
            ['startsAt' => $startsAt, 'endsAt' => $endsAt],
        )->assertOk();
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
