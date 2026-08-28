<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * MENU-ENTRY-SINGLE-SUBMIT — menüye ürün eklemek TEK bir iştir.
 *
 * Sahibinin tespiti: "category name girdikten sonra product name girerim.
 * ama category ekleme bilgileri ile ürün ekleme bilgileri aynı formda
 * olmaz." Ölçünce durum daha ağırdı: bir ürün eklemek DÖRT ayrı form ve ÜÇ
 * ayrı sunucu turuydu (ürün → menü satırı → alerjen).
 *
 * Bu, yalnız tıklama sayısı sorunu değildi. Üç ayrı yazma arasında ikincisi
 * düştüğünde, hiçbir menüde görünmeyen ÖKSÜZ bir ürün geride kalıyordu:
 * kullanıcı onu göremez, dolayısıyla temizleyemez. Aşağıdaki testler hem
 * tek adımı hem de o artığın imkânsızlığını dondurur.
 */
final class MenuEntrySingleSubmitTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** @return array{0:int,1:int} */
    private function workspaceWithMenuCategory(User $owner): array
    {
        $slugSeed = 'zeytin-entry-'.$owner->getKey();

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

        $menuId = (int) DB::table('menus')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Çorbalar',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $categoryId];
    }

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    // --- MENU-ENTRY-SINGLE-SUBMIT-01 --------------------------------------

    public function test_one_request_creates_the_product_its_menu_row_and_its_allergens(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $categoryId] = $this->workspaceWithMenuCategory($owner);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-entries",
            [
                'productName' => 'Mercimek Çorbası',
                'price' => '42.50',
                'currency' => 'TRY',
                'allergens' => ['gluten', 'süt'],
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('categoryId', $categoryId);
        $response->assertJsonPath('productName', 'Mercimek Çorbası');
        $response->assertJsonPath('priceMinorAmount', 4250);
        $response->assertJsonPath('currencyCode', 'TRY');
        $response->assertJsonPath('allergens', ['gluten', 'süt']);

        /*
            Yeni satır GÖRÜNÜR başlar — `docs/74` (P0-02).

            Eski yorum "menüye eklemek YAYINLAMAK değildir" diyordu ve bu
            doğru; ama o ayrımı `is_visible` korumuyor. Misafiri koruyan kapı
            yayındır: taslakta görünür bir ürün, `POST publications` çağrılana
            kadar hiçbir misafire ulaşmaz.
        */
        $response->assertJsonPath('isVisible', true);

        $productId = (int) $response->json('productId');
        self::assertSame(1, DB::table('products')->where('id', $productId)->count());
        self::assertSame(
            1,
            DB::table('menu_items')->where('product_id', $productId)->count(),
            'MENU-ENTRY-SINGLE-SUBMIT-01: ürün menüde bir satır olmalı.'
        );
        self::assertSame(
            2,
            DB::table('product_allergens')->where('product_id', $productId)->count()
        );
    }

    // --- MENU-ENTRY-SINGLE-SUBMIT-02 --------------------------------------

    public function test_allergens_are_optional_so_the_first_product_is_never_blocked(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $categoryId] = $this->workspaceWithMenuCategory($owner);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-entries",
            ['productName' => 'Su', 'price' => '10', 'currency' => 'TRY']
        );

        $response->assertStatus(201);
        $response->assertJsonPath('allergens', []);
    }

    // --- MENU-ENTRY-ATOMIC-03 ---------------------------------------------

    public function test_a_rejected_price_leaves_no_orphan_product_behind(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $categoryId] = $this->workspaceWithMenuCategory($owner);

        $before = DB::table('products')->count();

        // Marka para birimi TRY; başka bir para birimi reddedilir.
        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-entries",
            ['productName' => 'Mercimek Çorbası', 'price' => '42.50', 'currency' => 'EUR']
        );

        $response->assertStatus(422);

        // Asıl kazanım burada: üç ayrı çağrıda ilk adım çoktan yazılmış
        // olurdu ve hiçbir menüde görünmeyen bir ürün geride kalırdı.
        self::assertSame(
            $before,
            DB::table('products')->count(),
            'MENU-ENTRY-ATOMIC-03: reddedilen bir istek geride ürün bırakmamalı.'
        );
    }

    // --- MENU-ENTRY-AUTHZ-04 ----------------------------------------------

    public function test_a_nonmember_gets_an_enumeration_safe_not_found(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $categoryId] = $this->workspaceWithMenuCategory($owner);
        $stranger = $this->verifiedUser();

        $response = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-entries",
            ['productName' => 'Mercimek Çorbası', 'price' => '42.50', 'currency' => 'TRY']
        );

        // 403 DEĞİL 404: 403, workspace'in var olduğunu doğrulardı.
        $response->assertStatus(404);
        self::assertSame(0, DB::table('products')->where('name', 'Mercimek Çorbası')->count());
    }
}
