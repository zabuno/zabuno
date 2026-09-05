<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * P1-08 RED — menü mühendisliği (`docs/84`).
 *
 * MÜŞTERİ SORUNU. Sahip "menümde ne işe yarıyor?" diye sorar. Bugün alınan
 * cevap "menün 214 kez açıldı"dır. Hangi ürüne bakıldığı, hangi ürüne hiç
 * bakılmadığı, hangi ürünün arandığı ama bulunamadığı bilinmiyor — oysa bu,
 * menü mühendisliği için gereken tek bilgidir.
 *
 * Requirement IDs: ITEM-VIEW-RECORDED-01, ITEM-VIEW-DEDUPED-01,
 * ITEM-VIEW-ONLY-PUBLISHED-01, SEARCH-NO-RESULTS-01,
 * MENU-ENGINEERING-REPORT-01, MENU-ENGINEERING-TENANT-01,
 * MENU-ENGINEERING-THRESHOLD-01, MENU-ENGINEERING-THRESHOLD-VISITORS-02,
 * MENU-ENGINEERING-SEARCH-VISITORS-02, MENU-ENGINEERING-SEARCH-WINDOW-02,
 * MENU-ENGINEERING-PLAN-402-02.
 */
final class MenuEngineeringTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @return array{owner:User,workspaceId:int,menuId:int,publicKey:string,items:array<string,int>} */
    private function publishedMenu(string $seed): array
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

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")
            ->assertStatus(201);

        // CORE-04: bu rapor analitik RAPORLAMADIR ve plana bağlıdır. Testler
        // yazıldığında uçta kapı yoktu; artık planı açıkça kurmak gerekiyor.
        $this->grantEntitlements($workspaceId);

        return compact('owner', 'workspaceId', 'menuId', 'publicKey', 'items');
    }

    /** @param list<array<string,mixed>> $events */
    private function report(string $publicKey, array $events, string $agent = 'misafir-a')
    {
        return $this->withHeaders(['Accept' => 'application/json', 'User-Agent' => $agent])
            ->postJson('/q/events', ['menuKey' => $publicKey, 'events' => $events]);
    }

    // --- ITEM-VIEW-RECORDED-01 / ITEM-VIEW-DEDUPED-01 ---------------------

    public function test_an_item_that_was_actually_seen_is_counted_once_per_visitor(): void
    {
        $s = $this->publishedMenu('me-view');

        $this->report($s['publicKey'], [
            ['type' => 'item_view', 'menuItemId' => $s['items']['Levrek']],
            ['type' => 'item_view', 'menuItemId' => $s['items']['Çipura']],
        ])->assertOk();

        // Aynı ziyaretçi aynı ürünü yeniden bildirir: sayılan şey İLGİ,
        // kaydırma alışkanlığı değil.
        $this->report($s['publicKey'], [
            ['type' => 'item_view', 'menuItemId' => $s['items']['Levrek']],
        ])->assertOk();

        self::assertSame(
            1,
            DB::table('analytics_events')
                ->where('event_type', 'item_view')
                ->where('menu_item_id', $s['items']['Levrek'])
                ->count(),
            'ITEM-VIEW-DEDUPED-01: aynı ziyaretçi aynı gün bir kere sayılır.'
        );

        // Başka bir ziyaretçi AYRI sayılır.
        $this->report($s['publicKey'], [
            ['type' => 'item_view', 'menuItemId' => $s['items']['Levrek']],
        ], 'misafir-b')->assertOk();

        self::assertSame(
            2,
            DB::table('analytics_events')
                ->where('event_type', 'item_view')
                ->where('menu_item_id', $s['items']['Levrek'])
                ->count(),
        );
    }

    // --- ITEM-VIEW-ONLY-PUBLISHED-01 --------------------------------------

    public function test_an_id_that_is_not_in_the_published_menu_is_dropped(): void
    {
        $s = $this->publishedMenu('me-forgery');
        $other = $this->publishedMenu('me-forgery-other');

        $this->report($s['publicKey'], [
            ['type' => 'item_view', 'menuItemId' => $other['items']['Levrek']],
            ['type' => 'item_view', 'menuItemId' => 999999],
        ])->assertOk();

        self::assertSame(
            0,
            DB::table('analytics_events')->where('event_type', 'item_view')->count(),
            'ITEM-VIEW-ONLY-PUBLISHED-01: başka bir menünün satırı buraya yazılamaz.'
        );
    }

    // --- SEARCH-NO-RESULTS-01 ---------------------------------------------

    public function test_what_the_guest_looked_for_and_could_not_find_is_recorded(): void
    {
        $s = $this->publishedMenu('me-search');

        $this->report($s['publicKey'], [
            ['type' => 'search_no_results', 'term' => '  Karides Güveç  '],
        ])->assertOk();

        $row = DB::table('analytics_events')->where('event_type', 'search_no_results')->first();

        self::assertNotNull($row);
        self::assertSame('karides güveç', (string) $row->search_term);
        self::assertSame($s['workspaceId'], (int) $row->workspace_id);
        self::assertNull($row->menu_item_id);

        // Misafir KİMLİĞİ kurulmaz: ham IP ya da tarayıcı bilgisi saklanmaz.
        self::assertNotEmpty((string) $row->visitor_key);
        self::assertSame(64, strlen((string) $row->visitor_key));
    }

    public function test_an_absurdly_long_search_term_is_trimmed_not_refused(): void
    {
        $s = $this->publishedMenu('me-search-long');

        $this->report($s['publicKey'], [
            ['type' => 'search_no_results', 'term' => str_repeat('a', 500)],
        ])->assertOk();

        $term = (string) DB::table('analytics_events')
            ->where('event_type', 'search_no_results')->value('search_term');

        self::assertLessThanOrEqual(80, strlen($term));
        self::assertNotSame('', $term);
    }

    // --- MENU-ENGINEERING-REPORT-01 / THRESHOLD-01 ------------------------

    public function test_the_owner_sees_what_is_looked_at_and_what_never_is(): void
    {
        $s = $this->publishedMenu('me-report');

        // Eşik BEŞ farklı ziyaretçi: üç kişinin baktığı bir ürünü "en çok
        // bakılan" diye sunmak, sahibi gürültüye göre menü düzenlettirirdi.
        foreach (['a', 'b', 'c', 'd', 'e'] as $visitor) {
            $this->report($s['publicKey'], [
                ['type' => 'item_view', 'menuItemId' => $s['items']['Levrek']],
            ], 'misafir-'.$visitor)->assertOk();
        }

        $this->report($s['publicKey'], [
            ['type' => 'item_view', 'menuItemId' => $s['items']['Çipura']],
        ], 'misafir-a')->assertOk();

        $response = $this->actingAs($s['owner'])->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$s['workspaceId']}/analytics/menu-engineering?range=30d");

        $response->assertOk();

        $mostViewed = $response->json('mostViewed');
        self::assertSame('Levrek', $mostViewed[0]['productName']);
        self::assertSame(5, $mostViewed[0]['viewers']);
        self::assertSame('Çipura', $mostViewed[1]['productName']);
        self::assertSame(1, $mostViewed[1]['viewers']);

        // Hiç bakılmayanlar: olayın YOKLUĞU da bir cevaptır.
        self::assertSame(
            ['Hamsi'],
            array_column($response->json('neverViewed'), 'productName'),
            'MENU-ENGINEERING-REPORT-01: yayındaki ama hiç bakılmamış ürün listelenmeli.'
        );
    }

    public function test_thin_data_says_why_instead_of_showing_an_empty_table(): void
    {
        $s = $this->publishedMenu('me-thin');

        $response = $this->actingAs($s['owner'])->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$s['workspaceId']}/analytics/menu-engineering?range=30d");

        $response->assertOk();

        // Boş bir tablo, sahibe "ürünüm bozuk" dedirtir. Eşik ve sebep
        // AÇIKÇA söylenir (`docs/66` disiplini).
        self::assertSame([], $response->json('mostViewed'));
        self::assertSame('not_enough_data', $response->json('state'));
        self::assertGreaterThan(0, (int) $response->json('threshold'));
    }

    // --- MENU-ENGINEERING-TENANT-01 ---------------------------------------

    public function test_one_restaurants_numbers_never_appear_in_anothers(): void
    {
        $mine = $this->publishedMenu('me-mine');
        $theirs = $this->publishedMenu('me-theirs');

        $this->report($theirs['publicKey'], [
            ['type' => 'item_view', 'menuItemId' => $theirs['items']['Levrek']],
        ])->assertOk();

        $response = $this->actingAs($mine['owner'])->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$mine['workspaceId']}/analytics/menu-engineering?range=30d");

        self::assertSame([], $response->json('mostViewed'));

        // Ve başkasının raporunu okuyamaz.
        $this->actingAs($mine['owner'])->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$theirs['workspaceId']}/analytics/menu-engineering?range=30d")
            ->assertNotFound();
    }

    // --- MENU-ENGINEERING-THRESHOLD-VISITORS-02 ---------------------------

    /**
     * EŞİK KİŞİ SAYAR, SATIR TOPLAMI DEĞİL.
     *
     * MÜŞTERİ SORUNU. Kadıköy'deki balıkçının menüsünü akşam üç misafir
     * açıyor; üçü de listenin başındaki iki ürünü görüyor, aşağı inmiyor.
     * Eşik "ürün başına ziyaretçi sayılarının toplamı" ile hesaplanınca
     * 3 × 2 = 6 çıkıyor, beşi geçiyor ve rapor AÇILIYOR. Sahip ertesi
     * sabah panosunda "Hamsi son 30 günde bir kez bile açılmadı" cümlesini
     * okuyup hamsiyi menüden çıkarıyor — oysa hamsiye kimsenin bakmadığı
     * ölçülmedi, YALNIZ ÜÇ KİŞİ ölçüldü.
     *
     * "Henüz ölçmedim" ile "ölçtüm, sıfır çıktı" farklı cümlelerdir ve
     * yalnız ikincisi bir öneriyi hak eder.
     */
    public function test_three_visitors_who_read_two_dishes_do_not_unlock_never_viewed_advice(): void
    {
        $s = $this->publishedMenu('me-esik-kisi');

        foreach (['a', 'b', 'c'] as $visitor) {
            $this->report($s['publicKey'], [
                ['type' => 'item_view', 'menuItemId' => $s['items']['Levrek']],
                ['type' => 'item_view', 'menuItemId' => $s['items']['Çipura']],
            ], 'misafir-'.$visitor)->assertOk();
        }

        // Satır toplamı 6 (iki üründe üçer kişi) — beş eşiğini AŞAR.
        self::assertSame(
            6,
            DB::table('analytics_events')->where('event_type', 'item_view')->count(),
        );

        $response = $this->actingAs($s['owner'])->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$s['workspaceId']}/analytics/menu-engineering?range=30d");

        $response->assertOk();

        self::assertSame(
            'not_enough_data',
            $response->json('state'),
            'MENU-ENGINEERING-THRESHOLD-VISITORS-02: üç kişilik ölçüm raporu açmamalı.'
        );

        // Ekranın boş-durum cümlesi ZİYARETÇİ dilinde yazılı
        // ("{observed}/{threshold} ziyaretçi"); sayı da kişi olmalı.
        self::assertSame(3, (int) $response->json('observedViewers'));

        // Ve en önemlisi: hiçbir "hiç görüntülenmedi" önerisi doğmamalı.
        self::assertSame([], $response->json('neverViewed'));
    }

    // --- MENU-ENGINEERING-SEARCH-VISITORS-02 ------------------------------

    /**
     * ARAMA SAYISI KİŞİDİR, VURUŞ DEĞİL.
     *
     * Panodaki cümle "kaç kişi aradı" der ve uç da kişi sayar. Aynı
     * misafirin arama kutusuna ikinci kez dokunması yeni bir talep değildir;
     * öyle sayılsaydı herkese açık bir uçtan gelen sayı, ucuz bir betikle
     * şişirilebilir ve sahibi olmayan bir talebe göre menü değiştirtirdi.
     */
    public function test_the_same_guest_searching_twice_is_still_one_person(): void
    {
        $s = $this->publishedMenu('me-arama-kisi');

        $this->seedFiveViewers($s);

        $this->report($s['publicKey'], [['type' => 'search_no_results', 'term' => 'Vejetaryen']])->assertOk();
        $this->report($s['publicKey'], [['type' => 'search_no_results', 'term' => 'Vejetaryen']])->assertOk();
        $this->report($s['publicKey'], [['type' => 'search_no_results', 'term' => 'Vejetaryen']], 'misafir-z')->assertOk();

        // Üç satır YAZILDI: ham kayıt kırpılmıyor, yorum raporda yapılıyor.
        self::assertSame(
            3,
            DB::table('analytics_events')->where('event_type', 'search_no_results')->count(),
        );

        $searches = $this->actingAs($s['owner'])->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$s['workspaceId']}/analytics/menu-engineering?range=30d")
            ->json('searchesWithNoResults');

        self::assertSame(
            [['term' => 'vejetaryen', 'searches' => 2]],
            $searches,
            'MENU-ENGINEERING-SEARCH-VISITORS-02: üç vuruş, iki kişi.'
        );
    }

    // --- MENU-ENGINEERING-SEARCH-WINDOW-02 --------------------------------

    /**
     * "SON 30 GÜN" METİNDE DEĞİL SORGUDA.
     *
     * Öneri satırının gerekçesi "Sonuçsuz aramalar · son 30 gün" diyor.
     * Pencere uygulanmazsa sahip, iki yıl önce bir kez aranmış bir terim
     * için bugün menüsüne ürün ekler.
     */
    public function test_a_search_from_before_the_window_is_not_reported(): void
    {
        $s = $this->publishedMenu('me-arama-pencere');

        $this->seedFiveViewers($s);

        $this->recordSearch($s, 'dun', 'ziyaretci-yakin', now()->subDay());
        $this->recordSearch($s, 'gecen sene', 'ziyaretci-uzak', now()->subDays(400));

        $searches = $this->actingAs($s['owner'])->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$s['workspaceId']}/analytics/menu-engineering?range=30d")
            ->json('searchesWithNoResults');

        self::assertSame(
            ['dun'],
            array_column($searches, 'term'),
            'MENU-ENGINEERING-SEARCH-WINDOW-02: pencere dışındaki terim rapora giremez.'
        );
    }

    // --- MENU-ENGINEERING-PLAN-402-02 -------------------------------------

    /**
     * BU RAPOR PLANA BAĞLIDIR (CORE-04, owner kararı 2026-08-26).
     *
     * Kapı buraya unutulmuştu: özet ve zaman serisi uçları 402 dönerken bu
     * uç, planı raporlama içermeyen bir sahibe ürün başına ziyaretçi
     * sayılarını, hiç bakılmayan ürün listesini ve sonuçsuz arama
     * terimlerini panosunda göstermeye devam ediyordu.
     *
     * 402, 403 değil: kullanıcı yetkisiz DEĞİL, planı bu yeteneği
     * içermiyor — biri erişim talebi, diğeri plan yükseltmesidir.
     */
    public function test_a_plan_without_reporting_gets_402_not_a_free_report(): void
    {
        $s = $this->publishedMenu('me-plan');

        $this->seedFiveViewers($s);

        DB::table('subscriptions')->where('workspace_id', $s['workspaceId'])->delete();

        $this->actingAs($s['owner'])->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$s['workspaceId']}/analytics/menu-engineering?range=30d")
            ->assertStatus(402)
            ->assertJsonPath('entitlement', 'analytics.reporting');
    }

    /**
     * Raporu açacak kadar GERÇEK ziyaretçi: beş ayrı misafir.
     *
     * @param  array{publicKey:string, items:array<string,int>}  $s
     */
    private function seedFiveViewers(array $s): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $visitor) {
            $this->report($s['publicKey'], [
                ['type' => 'item_view', 'menuItemId' => $s['items']['Levrek']],
            ], 'misafir-'.$visitor)->assertOk();
        }
    }

    /**
     * Ham arama satırı: pencere sınavı GEÇMİŞE yazmayı gerektirir ve misafir
     * ucundan geçmişe olay bildirilemez.
     *
     * @param  array{workspaceId:int, menuId:int}  $s
     */
    private function recordSearch(array $s, string $term, string $visitorKey, \DateTimeInterface $occurredAt): void
    {
        DB::table('analytics_events')->insert([
            'workspace_id' => $s['workspaceId'],
            'location_id' => (int) DB::table('menus')->where('id', $s['menuId'])->value('location_id'),
            'qr_code_id' => null,
            'menu_id' => $s['menuId'],
            'menu_item_id' => null,
            'search_term' => $term,
            'event_type' => 'search_no_results',
            'visitor_key' => $visitorKey,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }
}
