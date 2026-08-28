<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1-04 RED — "bugün tükendi" (`docs/82`).
 *
 * MÜŞTERİ SORUNU. Akşam servisinde balık bitti. Sahibin tek seçeneği ürünü
 * GİZLEMEK; o zaman ürün menüden tamamen kaybolur. Misafir "bugün balık var
 * mı?" diye sorar, garson "vardı, bitti" der — dijital menünün çözmesi
 * gereken sürtünme aynen kalır. Ertesi sabah sahip altı ürünü tek tek geri
 * açmak zorundadır.
 *
 * KARAR (kriter 3): tükendi işareti YAYIN GEREKTİRMEDEN misafire yansır.
 * Sebep: "balık bitti" servis sırasında geçerli, dakikalık bir gerçektir.
 * Yayın beklemek hem yavaştır hem TEHLİKELİDİR — sahibin taslağında yarım
 * kalmış bir fiyat düzenlemesi olabilir ve yayın onu da canlıya iterdi.
 *
 * Yayın snapshot'ı DEĞİŞMEZ: tükendi, donmuş menünün üstüne konan bir
 * tebeşir notudur; menünün kendisi değil.
 *
 * Requirement IDs: STOCK-GUEST-VISIBLE-01, STOCK-NO-PUBLISH-01,
 * STOCK-INDEPENDENT-OF-VISIBILITY-01, STOCK-RESETS-NEXT-DAY-01,
 * STOCK-BULK-01, STOCK-AUTHZ-01.
 */
final class OutOfStockTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{owner:User,workspaceId:int,menuId:int,items:array<string,int>,publicKey:string} */
    private function scenario(string $seed, string $timezone = 'Europe/Istanbul'): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $seed, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => $seed.'-b',
            'locale' => 'tr', 'timezone' => $timezone, 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => $timezone, 'city' => 'İstanbul', 'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicKey = Str::lower(Str::random(10));

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => $publicKey, 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => 'Balıklar', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $items = [];
        $position = 0;

        foreach (['Levrek' => 42000, 'Çipura' => 38000, 'Hamsi' => 22000] as $name => $price) {
            $productId = (int) DB::table('products')->insertGetId([
                'workspace_id' => $workspaceId, 'name' => $name,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $items[$name] = (int) DB::table('menu_items')->insertGetId([
                'category_id' => $categoryId, 'product_id' => $productId,
                'price_minor_amount' => $price, 'currency_code' => 'TRY',
                'position' => $position++, 'is_visible' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return compact('owner', 'workspaceId', 'menuId', 'items', 'publicKey');
    }

    private function api(User $user)
    {
        return $this->actingAs($user)->withHeaders(['Accept' => 'application/json']);
    }

    private function guestHtml(string $publicKey): string
    {
        // `withHeaders` aynı test içinde YAPIŞKANDIR: yönetim isteklerinde
        // kurulan `Accept: application/json`, misafir sayfasını da JSON'a
        // çevirirdi.
        return $this->withHeaders(['Accept' => 'text/html'])
            ->followingRedirects()
            ->get("/menu/{$publicKey}")
            ->getContent();
    }

    // --- STOCK-GUEST-VISIBLE-01 / STOCK-NO-PUBLISH-01 ---------------------

    public function test_a_sold_out_dish_stays_on_the_menu_and_says_so_without_a_publish(): void
    {
        $s = $this->scenario('stock-guest');

        $published = $this->api($s['owner'])
            ->postJson("/api/workspaces/{$s['workspaceId']}/menu/{$s['menuId']}/publications")->json();

        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/menu-items/{$s['items']['Levrek']}/stock",
            ['outOfStock' => true]
        )->assertOk();

        $html = $this->guestHtml($s['publicKey']);

        // Ürün MENÜDE KALIR — gizlemek onu tamamen kaybettiriyordu.
        self::assertStringContainsString('Levrek', $html);
        // Fiyatı görünür: misafir yarın ne ödeyeceğini bilir.
        self::assertStringContainsString('420.00', $html);
        // Ve tükendiği METİNLE bellidir; yalnız renkle değil (WCAG 1.4.1).
        self::assertMatchesRegularExpression(
            '#<span class="qr-menu-item-sold-out-note">Bugün tükendi</span>#u',
            $html
        );

        // Tükenmeyen ürünlerde not YOK.
        self::assertSame(
            1,
            substr_count($html, 'qr-menu-item-sold-out-note">'),
            'STOCK-GUEST-VISIBLE-01: yalnız işaretlenen ürün tükenmiş görünmeli.'
        );

        // YAYIN SNAPSHOT'I DEĞİŞMEDİ: tükendi, donmuş menünün üstüne konan
        // bir tebeşir notudur.
        $current = $this->api($s['owner'])
            ->getJson("/api/workspaces/{$s['workspaceId']}/menu/{$s['menuId']}/publications/current")->json();

        self::assertSame($published['snapshot'], $current['snapshot']);
        self::assertSame($published['version'], $current['version']);
    }

    // --- STOCK-INDEPENDENT-OF-VISIBILITY-01 -------------------------------

    public function test_stock_and_visibility_are_two_different_axes(): void
    {
        $s = $this->scenario('stock-axes');

        // Gizli bir ürün tükendi işaretinden ETKİLENMEZ: menüde zaten yok.
        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/menu-items/{$s['items']['Hamsi']}/visibility",
            ['isVisible' => false]
        )->assertOk();

        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/menu-items/{$s['items']['Hamsi']}/stock",
            ['outOfStock' => true]
        )->assertOk();

        $this->api($s['owner'])
            ->postJson("/api/workspaces/{$s['workspaceId']}/menu/{$s['menuId']}/publications");

        $html = $this->guestHtml($s['publicKey']);

        self::assertStringNotContainsString('Hamsi', $html);
        self::assertStringNotContainsString('qr-menu-item-sold-out-note">', $html);

        // Görünürlük bağımsız kaldı.
        self::assertSame(
            0,
            (int) DB::table('menu_items')->where('id', $s['items']['Hamsi'])->value('is_visible'),
        );
        self::assertNotNull(
            DB::table('menu_items')->where('id', $s['items']['Hamsi'])->value('out_of_stock_since'),
            'STOCK-INDEPENDENT-OF-VISIBILITY-01: iki eksen birbirini silmez.'
        );
    }

    // --- STOCK-RESETS-NEXT-DAY-01 -----------------------------------------

    public function test_yesterdays_sold_out_fish_is_back_this_morning(): void
    {
        $s = $this->scenario('stock-reset');

        $this->api($s['owner'])
            ->postJson("/api/workspaces/{$s['workspaceId']}/menu/{$s['menuId']}/publications");

        Carbon::setTestNow(Carbon::parse('2026-08-27 21:00:00', 'Europe/Istanbul'));

        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/menu-items/{$s['items']['Levrek']}/stock",
            ['outOfStock' => true]
        )->assertOk();

        self::assertStringContainsString('qr-menu-item-sold-out-note">', $this->guestHtml($s['publicKey']));

        // Ertesi sabah: işaret KENDİLİĞİNDEN düşer. Sahip altı ürünü tek
        // tek geri açmaz ve hiçbir zamanlanmış göreve güvenilmez.
        Carbon::setTestNow(Carbon::parse('2026-08-28 09:00:00', 'Europe/Istanbul'));

        self::assertStringNotContainsString(
            'qr-menu-item-sold-out-note">',
            $this->guestHtml($s['publicKey']),
            'STOCK-RESETS-NEXT-DAY-01: dün tükenen balık bugün yeniden vardır.'
        );

        Carbon::setTestNow();
    }

    // --- STOCK-BULK-01 ----------------------------------------------------

    public function test_the_owner_marks_several_dishes_from_one_screen(): void
    {
        $s = $this->scenario('stock-bulk');

        $this->api($s['owner'])
            ->postJson("/api/workspaces/{$s['workspaceId']}/menu/{$s['menuId']}/publications");

        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/menu/{$s['menuId']}/stock",
            ['outOfStock' => [$s['items']['Levrek'], $s['items']['Çipura']], 'inStock' => []]
        )->assertOk();

        self::assertSame(2, substr_count($this->guestHtml($s['publicKey']), 'qr-menu-item-sold-out-note">'));

        // Ve geri getirmek de tek ekrandan.
        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/menu/{$s['menuId']}/stock",
            ['outOfStock' => [], 'inStock' => [$s['items']['Levrek'], $s['items']['Çipura']]]
        )->assertOk();

        self::assertStringNotContainsString('qr-menu-item-sold-out-note">', $this->guestHtml($s['publicKey']));
    }

    public function test_another_restaurants_item_cannot_be_marked_here(): void
    {
        $s = $this->scenario('stock-mine');
        $other = $this->scenario('stock-theirs');

        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/menu-items/{$other['items']['Levrek']}/stock",
            ['outOfStock' => true]
        )->assertNotFound();

        self::assertNull(
            DB::table('menu_items')->where('id', $other['items']['Levrek'])->value('out_of_stock_since')
        );
    }

    // --- STOCK-AUTHZ-01 ---------------------------------------------------

    public function test_a_read_only_member_cannot_mark_a_dish_sold_out(): void
    {
        $s = $this->scenario('stock-authz');

        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $s['workspaceId'], 'user_id' => $member->id, 'role' => 'member',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api($member)->putJson(
            "/api/workspaces/{$s['workspaceId']}/menu-items/{$s['items']['Levrek']}/stock",
            ['outOfStock' => true]
        )->assertStatus(403);
    }
}
