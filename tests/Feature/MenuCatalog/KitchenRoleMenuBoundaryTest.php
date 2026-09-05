<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * MUTFAK ROLÜNÜN SUNUCU KAPISI — kaynak `panel.dc.html`
 * (`data-screen-label="Takım"`), cümlesi `docs/109` §6.4: *"Mutfak —
 * Alerjen ve 'bugün bitti'. Başka bir şey görmez."*
 *
 * ═══ GİZLEMEK KORUMA DEĞİLDİR ═══
 *
 * Bu dosyanın tek varlık sebebi budur. Ekranda bir düğmeyi çizmemek, o
 * düğmenin arkasındaki ucu kapatmaz: tarayıcının ağ sekmesini açan,
 * `curl` bilen ya da eski bir sekmeyi açık bırakan herkes fiyat ucunu
 * doğrudan çağırabilir. Aşçının telefonu mutfakta, un içinde, çoğu zaman
 * kilidi açık durur — bu rolün sınırı SUNUCUDA durmak zorunda.
 *
 * NEDEN KIRMIZI: `kitchen` diye bir üyelik rolü yok. Bugün böyle bir satır
 * yazıldığında `RolePermissions::for()` bir `\UnhandledMatchError` atar; o
 * satır varsayılsa bile alerjen ve stok uçları geniş `menu.manage` iznine
 * bakıyor, yani aşçıya alerjeni açmanın tek yolu fiyatı da açmaktır.
 *
 * MÜŞTERİ YOLCULUĞU. Aşçı Hasan servis sırasında "Levrek bitti" der ve
 * fıstık alerjisini işaretler — ikisi de saniyeler içinde misafire yansır.
 * Aynı Hasan, aynı oturumda, Levrek'in fiyatını 420 TL'den 42 TL'ye
 * çekemez; sunucu 403 der ve fiyat veritabanında olduğu gibi kalır.
 *
 * Requirement IDs: KITCHEN-HTTP-ALLERGENS-01, KITCHEN-HTTP-STOCK-01,
 * KITCHEN-HTTP-BULK-STOCK-01, KITCHEN-HTTP-PRICE-DENIED-01,
 * KITCHEN-HTTP-CATALOG-DENIED-01, KITCHEN-HTTP-PUBLISH-DENIED-01,
 * KITCHEN-HTTP-OFF-MENU-DENIED-01, KITCHEN-HTTP-MULTI-MENU-01.
 */
final class KitchenRoleMenuBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{workspaceId:int,locationId:int,menuId:int,secondMenuId:int,categoryId:int,itemId:int,productId:int,kitchen:User,owner:User}
     */
    private function scenario(string $seed): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $kitchen = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $seed, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            ['workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
                'created_at' => now(), 'updated_at' => now()],
            // Kaynağın kendi anahtarı: `<option value="kitchen">Mutfak</option>`.
            ['workspace_id' => $workspaceId, 'user_id' => $kitchen->id, 'role' => 'kitchen',
                'created_at' => now(), 'updated_at' => now()],
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => $seed.'-b',
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        /*
            İKİNCİ MENÜ — çoklu menü FF-137'de indi (`docs/109` §7.1) ve
            Mutfak rolü onunla da çalışmak zorunda. Tek menülü bir kurulumda
            sınamak, "haplardan Kahvaltı'yı seçen aşçı" hâlini hiç
            denemeden yeşil derdi.
        */
        $secondMenuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Kahvaltı', 'state' => 'draft',
            'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => 'Balıklar', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Levrek',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $itemId = (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 42000, 'currency_code' => 'TRY',
            'position' => 0, 'is_visible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact(
            'workspaceId', 'locationId', 'menuId', 'secondMenuId',
            'categoryId', 'itemId', 'productId', 'kitchen', 'owner'
        );
    }

    private function api(User $user)
    {
        return $this->actingAs($user)->withHeaders(['Accept' => 'application/json']);
    }

    // --- KITCHEN-HTTP-ALLERGENS-01 ---------------------------------------

    public function test_kitchen_can_set_allergens(): void
    {
        $s = $this->scenario('kitchen-allergens');

        $this->api($s['kitchen'])
            ->putJson("/api/workspaces/{$s['workspaceId']}/menu-items/{$s['itemId']}/allergens", [
                'allergens' => ['fish', 'peanuts'],
            ])
            ->assertOk();

        // Sonuç VERİTABANINDA doğrulanır: 200 dönen ama hiçbir şey yazmayan
        // bir uç, aşçıya işini yaptığını söyleyip misafiri korumazdı.
        $this->assertSame(2, DB::table('product_allergens')
            ->where('product_id', $s['productId'])
            ->count());
    }

    // --- KITCHEN-HTTP-STOCK-01 -------------------------------------------

    public function test_kitchen_can_mark_sold_out_and_open_it_again(): void
    {
        $s = $this->scenario('kitchen-stock');

        $this->api($s['kitchen'])
            ->putJson("/api/workspaces/{$s['workspaceId']}/menu-items/{$s['itemId']}/stock", ['outOfStock' => true])
            ->assertOk();

        $this->assertNotNull(DB::table('menu_items')->where('id', $s['itemId'])->value('out_of_stock_since'));

        // GERİ AÇMAK da işin parçası: ertesi sabah balık gelir.
        $this->api($s['kitchen'])
            ->putJson("/api/workspaces/{$s['workspaceId']}/menu-items/{$s['itemId']}/stock", ['outOfStock' => false])
            ->assertOk();

        $this->assertNull(DB::table('menu_items')->where('id', $s['itemId'])->value('out_of_stock_since'));
    }

    // --- KITCHEN-HTTP-BULK-STOCK-01 --------------------------------------

    public function test_kitchen_can_mark_a_whole_menu_at_once(): void
    {
        $s = $this->scenario('kitchen-bulk');

        // Akşam servisinde biten şey tek ürün değildir; toplu yol da
        // Mutfak'a açık olmalı, yoksa aşçı altı isteği tek tek yollar.
        $this->api($s['kitchen'])
            ->putJson("/api/workspaces/{$s['workspaceId']}/menu/{$s['menuId']}/stock", [
                'outOfStock' => [$s['itemId']],
                'inStock' => [],
            ])
            ->assertOk()
            ->assertJson(['markedOutOfStock' => 1]);
    }

    // --- KITCHEN-HTTP-PRICE-DENIED-01 (BU PAKETİN ASIL KANITI) -----------

    public function test_kitchen_calling_the_price_endpoint_directly_is_forbidden(): void
    {
        $s = $this->scenario('kitchen-price');

        $this->api($s['kitchen'])
            ->putJson("/api/workspaces/{$s['workspaceId']}/menu-items/{$s['itemId']}/price", [
                'price' => '42.00',
                'currency' => 'TRY',
            ])
            ->assertForbidden();

        // 403 tek başına yetmez: fiyat GERÇEKTEN değişmemiş olmalı.
        $this->assertSame(42000, (int) DB::table('menu_items')->where('id', $s['itemId'])->value('price_minor_amount'));
    }

    // --- KITCHEN-HTTP-CATALOG-DENIED-01 ----------------------------------

    public function test_kitchen_cannot_add_rename_hide_or_delete_anything(): void
    {
        $s = $this->scenario('kitchen-catalog');
        $w = $s['workspaceId'];

        $this->api($s['kitchen'])
            ->postJson("/api/workspaces/{$w}/menu/{$s['menuId']}/categories", ['name' => 'Tatlılar'])
            ->assertForbidden();

        $this->api($s['kitchen'])
            ->postJson("/api/workspaces/{$w}/menu-categories/{$s['categoryId']}/menu-entries", [
                'productName' => 'Çipura', 'price' => '380.00', 'currency' => 'TRY', 'allergens' => [],
            ])
            ->assertForbidden();

        $this->api($s['kitchen'])
            ->putJson("/api/workspaces/{$w}/menu-items/{$s['itemId']}", ['productName' => 'Levrek Izgara'])
            ->assertForbidden();

        /*
            GİZLEMEK, "BİTTİ" DEĞİLDİR ve Mutfak'a kapalıdır.

            Gizli ürün menüde HİÇ YOKTUR; tükenen ürün menüde vardır ve
            bugün alınamaz. Birincisi menünün neye benzediğine dair kalıcı
            bir karardır ve sahibin/yöneticinin işidir.
        */
        $this->api($s['kitchen'])
            ->putJson("/api/workspaces/{$w}/menu-items/{$s['itemId']}/visibility", ['isVisible' => false])
            ->assertForbidden();

        $this->api($s['kitchen'])
            ->deleteJson("/api/workspaces/{$w}/menu-items/{$s['itemId']}")
            ->assertForbidden();

        // Hiçbiri yazmamış olmalı.
        $this->assertTrue((bool) DB::table('menu_items')->where('id', $s['itemId'])->value('is_visible'));
        $this->assertSame(1, DB::table('menu_categories')->where('menu_id', $s['menuId'])->count());
    }

    // --- KITCHEN-HTTP-PUBLISH-DENIED-01 ----------------------------------

    public function test_kitchen_cannot_publish(): void
    {
        $s = $this->scenario('kitchen-publish');

        // "Bugün bitti" yayın GEREKTİRMEDEN misafire yansır (`docs/82`);
        // tam da bu yüzden Mutfak'a yayın izni vermek gereksiz ve tehlikeli
        // olurdu — sahibin yarım kalmış fiyat taslağını canlıya iterdi.
        $this->api($s['kitchen'])
            ->postJson("/api/workspaces/{$s['workspaceId']}/menu/{$s['menuId']}/publications")
            ->assertForbidden();
    }

    // --- KITCHEN-HTTP-OFF-MENU-DENIED-01 ---------------------------------

    public function test_kitchen_sees_nothing_outside_the_menu(): void
    {
        $s = $this->scenario('kitchen-off-menu');
        $w = $s['workspaceId'];

        /*
            "BAŞKA BİR ŞEY GÖRMEZ" — kaynağın kendi cümlesi, sunucuda.

            Karekod ve takım uçları GÖRME izni olmadığında 404 der, 403
            değil: yetkisi olmayanın o kaynağın VARLIĞINI bile öğrenmemesi
            gerekir (deponun iki aşamalı kapı dili).
        */
        $this->api($s['kitchen'])
            ->getJson("/api/workspaces/{$w}/brand/locations/{$s['locationId']}/qr-codes")
            ->assertNotFound();

        $this->api($s['kitchen'])->getJson("/api/workspaces/{$w}/team/members")->assertNotFound();

        /*
            MEDYAYA YAZMAK 403'tür, 404 değil — ve bu ayrım bilerek korunur.

            Kütüphaneyi LİSTELEMEK bu depoda `workspace.view` ile açıktır
            (eski salt okunur `member` rolü için de öyle); yani Mutfak da
            listeyi çekebilir. Bu paket o gevşekliği DEĞİŞTİRMEZ, çünkü
            değiştirmek `member` rolünün bugünkü davranışını da sessizce
            kırardı. Sınırın gerçekten durduğu yer YAZMAKTIR.

            Silme ucu seçildi çünkü yetki kapısı her şeyden ÖNCE çalışır:
            olmayan bir dosya kimliğiyle bile 403 döner, yani "önce doğrula
            sonra yetkilendir" sırasına takılmadan yetkinin kendisini
            kanıtlar.
        */
        $this->api($s['kitchen'])
            ->deleteJson("/api/workspaces/{$w}/media/999999")
            ->assertForbidden();
    }

    // --- KITCHEN-HTTP-MULTI-MENU-01 --------------------------------------

    public function test_kitchen_works_on_a_location_with_several_menus(): void
    {
        $s = $this->scenario('kitchen-multi');
        $w = $s['workspaceId'];

        // Menü haplarının verisi: aşçı hangi menüde çalıştığını görebilmeli.
        $this->api($s['kitchen'])
            ->getJson("/api/workspaces/{$w}/brand/locations/{$s['locationId']}/menus")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Belirli bir menüyü açmak (hapa basmak) da açık olmalı.
        $this->api($s['kitchen'])->getJson("/api/workspaces/{$w}/menu/{$s['menuId']}")->assertOk();
        $this->api($s['kitchen'])->getJson("/api/workspaces/{$w}/menu/{$s['secondMenuId']}")->assertOk();
    }
}
