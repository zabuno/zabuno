<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use App\Support\Localization\GuestLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\UntranslatableStringScanner;
use Tests\TestCase;

/**
 * P1-06 RED — misafir dil seçimi (`docs/85`).
 *
 * MÜŞTERİ SORUNU. Turistik bir restoranda misafirin yarısı Türkçe okumaz.
 * Misafir sayfasının arayüz metinleri Blade şablonuna SABİT Türkçe yazılmıştı
 * ve misafir için dil değiştirme yolu yoktu.
 *
 * İKİ KATMAN AYRIDIR: arayüz metinleri katalogdan gelir ve çevrilir; MENÜ
 * İÇERİĞİNİN çevirisi ayrı ve çok daha büyük bir iştir. İkisini karıştırmak,
 * arayüzü İngilizceye alan misafire menünün de İngilizce olacağını ima
 * ederdi — tutulmayacak bir söz.
 *
 * Requirement IDs: GUEST-I18N-NO-HARDCODED-01, GUEST-I18N-SWITCH-01,
 * GUEST-I18N-REMEMBERED-01, GUEST-I18N-LANG-DIR-01,
 * GUEST-I18N-CONTENT-HONEST-01.
 */
final class GuestLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function publishedMenu(string $seed, string $contentLocale = 'tr'): string
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
            'locale' => $contentLocale, 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
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

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Levrek',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 42000, 'currency_code' => 'TRY',
            'position' => 0, 'is_visible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")
            ->assertStatus(201);

        return $publicKey;
    }

    private function guest(string $publicKey, string $query = '')
    {
        return $this->withHeaders(['Accept' => 'text/html'])
            ->followingRedirects()
            ->get("/menu/{$publicKey}".$query);
    }

    // --- GUEST-I18N-NO-HARDCODED-01 ---------------------------------------

    public function test_not_one_user_facing_string_is_hardcoded_in_the_template(): void
    {
        $hits = UntranslatableStringScanner::scanFile(
            resource_path('views/public-menu.blade.php'),
        );

        self::assertSame(
            [],
            $hits,
            'GUEST-I18N-NO-HARDCODED-01: şablona sabit yazılan bir cümleyi sahip hiçbir PO '
            .'dosyasından çeviremez. Bulunanlar: '.implode(' | ', $hits)
        );

        // Betik GÖVDESİ de kullanıcı metnidir ve tarayıcı onu atlıyor;
        // burada ayrıca kontrol edilir.
        $source = (string) file_get_contents(resource_path('views/public-menu.blade.php'));

        preg_match_all('/<script\b(?![^>]*application\/(?:ld\+json|json))[^>]*>(.*?)<\/script>/is', $source, $scripts);

        foreach ($scripts[1] as $body) {
            /*
                YORUMLAR ÖNCE DÜŞER.

                Bir yorum kullanıcı metni DEĞİLDİR ve Türkçe yazılmış bir
                açıklama ihlal sayılamaz — aksi hâlde kural, kararın
                gerekçesini yazmayı cezalandırırdı. Aynı ders bu depoda iki
                mimari kapıda daha öğrenildi (`docs/82`).
            */
            $code = (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $body);

            self::assertDoesNotMatchRegularExpression(
                // Türkçeye özgü harf taşıyan bir DİZE, çevrilmemiş bir
                // kullanıcı metninin en güvenilir işareti.
                '/[\'"][^\'"\n]*[çğıöşüÇĞİÖŞÜ][^\'"\n]*[\'"]/u',
                $code,
                'GUEST-I18N-NO-HARDCODED-01: betik gövdesinde çevrilmemiş metin var.'
            );
        }
    }

    // --- GUEST-I18N-SWITCH-01 / REMEMBERED-01 / LANG-DIR-01 ---------------

    public function test_the_guest_switches_language_and_the_choice_is_remembered(): void
    {
        $key = $this->publishedMenu('lang-switch');

        $turkish = $this->guest($key);
        $turkish->assertOk();
        self::assertStringContainsString('Menüde ara', $turkish->getContent());
        self::assertMatchesRegularExpression('#<html lang="tr" dir="ltr"#', $turkish->getContent());

        // Dil seçimi düz BAĞLANTIDIR: JavaScript çalışmasa da çalışır.
        self::assertStringContainsString('?lang=en', $turkish->getContent());

        $english = $this->guest($key, '?lang=en');
        $english->assertOk();
        self::assertStringContainsString('Search the menu', $english->getContent());
        self::assertMatchesRegularExpression('#<html lang="en" dir="ltr"#', $english->getContent());

        // Seçim AYNI CİHAZDA hatırlanır: misafir her açılışta yeniden
        // seçmemeli.
        $english->assertCookie(GuestLocale::COOKIE, 'en');

        $remembered = $this->withCookie(GuestLocale::COOKIE, 'en')
            ->withHeaders(['Accept' => 'text/html'])
            ->followingRedirects()
            ->get("/menu/{$key}");

        self::assertStringContainsString('Search the menu', $remembered->getContent());
    }

    public function test_an_unsupported_language_falls_back_instead_of_breaking(): void
    {
        $key = $this->publishedMenu('lang-unknown');

        $response = $this->guest($key, '?lang=klingon');

        $response->assertOk();
        self::assertMatchesRegularExpression('#<html lang="tr"#', $response->getContent());
    }

    // --- GUEST-I18N-CONTENT-HONEST-01 -------------------------------------

    public function test_the_product_does_not_promise_a_translated_menu(): void
    {
        $key = $this->publishedMenu('lang-honest');

        $english = $this->guest($key, '?lang=en');

        // Ürün adı ÇEVRİLMEZ ve olduğu gibi kalır.
        self::assertStringContainsString('Levrek', $english->getContent());

        // Ve bu durum SÖYLENİR: söylememek, tutulmayacak bir söz vermek olurdu.
        self::assertMatchesRegularExpression(
            '#<p class="qr-menu-content-notice">\s*Dish names are in the restaurant’s own language\.#u',
            $english->getContent(),
            'GUEST-I18N-CONTENT-HONEST-01: içerik çevirisinin yokluğu açıkça söylenmeli.'
        );

        // Menü içeriği KENDİ dilini taşır: ekran okuyucu ürün adlarını
        // restoranın dilinde telaffuz etmeli.
        self::assertMatchesRegularExpression('#<section class="qr-menu-category"[^>]+lang="tr"#', $english->getContent());

        /*
            Arayüz zaten içerik diliyle aynıyken uyarı GÖSTERİLMEZ: söylenecek
            bir şey yok ve her sayfada duran bir not okunmaz hâle gelir.

            Dil AÇIKÇA istenir: aynı test içinde bir önceki istek çerezi
            bıraktı ve "varsayılan" artık İngilizce — bu doğru davranış,
            ama burada ölçmek istediğimiz şey o değil.
        */
        /*
            İddia ELEMENTE bakar: sınıf adı stil bloğunda da geçiyor ve ham
            metin araması "misafir bunu OKUYOR MU" sorusunu cevaplamıyor.
        */
        self::assertDoesNotMatchRegularExpression(
            '#<p class="qr-menu-content-notice">#',
            $this->guest($key, '?lang=tr')->getContent(),
        );
    }
}
