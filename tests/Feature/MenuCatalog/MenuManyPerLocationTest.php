<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BU DOSYA BİR SÖZLEŞMEYİ DEĞİŞTİRİYOR — NEDENİ BURADA YAZILI.
 *
 * NE SİLİNDİ: `tests/Feature/MenuCatalog/MenuOnePerLocationConstraintTest.php`
 * ve onun dondurduğu iki gereksinim — `MENU-PERSIST-ONE-PER-LOCATION-01`,
 * `MENU-PERSIST-ONE-PER-LOCATION-NO-PARTIAL-WRITE-01`.
 *
 * NEDEN: **Sahip 2026-09-05'te açıkça soruldu ve "çoklu menü YAPILSIN,
 * saat bazlı geçişli" dedi.** Karar `docs/109-PANEL-V3.md` §7.1'de yazılıdır:
 * "Şube başına birden çok menü; her menünün adı, durumu ve saat aralığı
 * olur." Yani `menus.location_id` üzerindeki UNIQUE kısıt bir hata değildi,
 * o günün ÜRÜN KAPSAMIYDI; kapsam sahibi tarafından genişletildi.
 *
 * Eski testler yanlış değildi ve silinmeleri bir kusurun örtülmesi değil:
 * korudukları davranış artık ürünün istemediği bir davranış. Yerine bu
 * dosya, yeni kuralın kendi koruyucularını koyar.
 *
 * YENİ GEREKSİNİMLER
 * - `MENU-MANY-PER-LOCATION-01` — aynı şubede ikinci bir menü yaratılabilir.
 * - `MENU-ADDRESS-ANCHOR-01` — şubenin herkese açık adresi (`public_key`)
 *   ŞUBEYE aittir, menüye değil: yalnız ÇIPA menüde durur, ikinci menü
 *   kendi adresini ALMAZ. Aksi hâlde bir şubenin iki genel adresi olur ve
 *   aynı içerik iki adreste indekslenirdi.
 * - `MENU-SORT-ORDER-01` — her yeni menü sıradaki `sort_order` değerini alır;
 *   menü hapları ekranda kararlı bir sırada durur.
 * - `MENU-NEW-IS-DRAFT-01` — yeni menü ROTASYONA girmez (`draft`). Doğar
 *   doğmaz saat almış olsaydı, sahip daha adını yazarken misafirin gördüğü
 *   menü değişirdi.
 */
final class MenuManyPerLocationTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** @return array{0:int,1:int} [workspaceId, locationId] */
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

    // --- MENU-MANY-PER-LOCATION-01 -----------------------------------------

    public function test_a_location_may_carry_more_than_one_menu(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-many');
        $repository = $this->repository();

        $main = $repository->createDraftMenu($workspaceId, $locationId, 'Ana menü');
        $breakfast = $repository->createDraftMenu($workspaceId, $locationId, 'Kahvaltı');

        self::assertNotSame($main->id, $breakfast->id);
        self::assertSame(
            2,
            DB::table('menus')->where('location_id', $locationId)->count(),
            'MANY-PER-LOCATION-01: aynı şube iki menü taşıyabilmeli.'
        );
    }

    // --- MENU-ADDRESS-ANCHOR-01 --------------------------------------------

    public function test_only_the_first_menu_of_a_location_owns_the_public_address(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-anchor');
        $repository = $this->repository();

        $main = $repository->createDraftMenu($workspaceId, $locationId, 'Ana menü');
        $breakfast = $repository->createDraftMenu($workspaceId, $locationId, 'Kahvaltı');

        $anchorKey = DB::table('menus')->where('id', $main->id)->value('public_key');
        $secondKey = DB::table('menus')->where('id', $breakfast->id)->value('public_key');

        self::assertNotNull($anchorKey, 'ADDRESS-ANCHOR-01: şubenin ilk menüsü genel adresi taşımalı.');
        self::assertNull(
            $secondKey,
            'ADDRESS-ANCHOR-01: ikinci menü İKİNCİ bir genel adres açmamalı — adres şubeye aittir, menüye değil.'
        );
    }

    // --- MENU-SORT-ORDER-01 ------------------------------------------------

    public function test_menus_receive_a_stable_increasing_order(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-order');
        $repository = $this->repository();

        $first = $repository->createDraftMenu($workspaceId, $locationId, 'Ana menü');
        $second = $repository->createDraftMenu($workspaceId, $locationId, 'Kahvaltı');
        $third = $repository->createDraftMenu($workspaceId, $locationId, 'Ramazan');

        self::assertSame(0, (int) DB::table('menus')->where('id', $first->id)->value('sort_order'));
        self::assertSame(1, (int) DB::table('menus')->where('id', $second->id)->value('sort_order'));
        self::assertSame(2, (int) DB::table('menus')->where('id', $third->id)->value('sort_order'));
    }

    // --- MENU-NEW-IS-DRAFT-01 ----------------------------------------------

    public function test_a_new_menu_does_not_enter_the_rotation_by_itself(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-draft');
        $repository = $this->repository();

        $repository->createDraftMenu($workspaceId, $locationId, 'Ana menü');
        $breakfast = $repository->createDraftMenu($workspaceId, $locationId, 'Kahvaltı');

        self::assertSame('draft', (string) DB::table('menus')->where('id', $breakfast->id)->value('state'));
        self::assertSame(
            0,
            DB::table('menu_service_switches')->where('menu_id', $breakfast->id)->count(),
            'NEW-IS-DRAFT-01: yeni menü kendiliğinden bir saat dilimi sahiplenmemeli.'
        );
    }

    // --- ayrı şubeler etkilenmez -------------------------------------------

    public function test_two_different_locations_keep_their_own_menus(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $firstLocationId] = $this->workspaceWithLocation($owner, 'zeytin-distinct');
        $brandId = (int) DB::table('brands')->where('workspace_id', $workspaceId)->value('id');
        $secondLocationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Beşiktaş Şubesi',
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Barbaros Blv. No:2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $repository = $this->repository();

        $first = $repository->createDraftMenu($workspaceId, $firstLocationId, 'Kadıköy Menüsü');
        $second = $repository->createDraftMenu($workspaceId, $secondLocationId, 'Beşiktaş Menüsü');

        self::assertNotSame($first->id, $second->id);
        self::assertNotNull(DB::table('menus')->where('id', $second->id)->value('public_key'));
    }
}
