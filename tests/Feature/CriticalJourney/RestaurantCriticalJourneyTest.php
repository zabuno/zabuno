<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalJourney;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * S1 MVP kritik restoran yolculuğu — modüller arası bileşim kanıtı.
 *
 * Külliyattaki her `...JourneyTest` tek bir modülü kanıtlar ve ön koşullarını
 * ham `DB::table()->insertGetId()` ile kurar. Bu yüzden hiçbiri modüllerin
 * birbirine gerçekten bağlandığını göstermez: MenuCatalog'un gerçek çıktısı
 * Publication'ın gerçek girdisi mi, o snapshot QrDestination'ın gerçek girdisi
 * mi, hiç sınanmamıştır. Örneğin
 * QrDestinationPublicResolverTest::test_after_a_new_publication_supersedes_current...
 * snapshot JSON'unu elle yazar; gerçek publish endpoint'ini hiç çağırmaz.
 *
 * Bu dosya boşluğu kapatır: kullanıcı hesabı dışındaki HER adım ürünün kendi
 * HTTP uçlarından geçer, hiçbir fixture elle kurulmaz.
 *
 *   POST /register + imzalı verification link
 *   POST /api/workspaces
 *   POST /api/workspaces/{ws}/brand
 *   POST /api/workspaces/{ws}/brand/locations
 *   POST /api/workspaces/{ws}/brand/locations/{loc}/menu
 *   POST /api/workspaces/{ws}/menu/{menu}/categories
 *   POST /api/workspaces/{ws}/menu-categories/{cat}/products
 *   POST /api/workspaces/{ws}/menu-categories/{cat}/menu-items
 *   POST /api/workspaces/{ws}/menu/{menu}/publications
 *   POST /api/workspaces/{ws}/brand/locations/{loc}/qr-codes
 *   GET  /q/{token} -> 302 -> GET /menu/{token}
 *
 * Ürün semantiği (bu dosyanın dondurduğu sözleşme): public menü yayınlanmış
 * snapshot'ı sunar. Fiyat değişikliği misafire ancak yeniden publish ile ulaşır
 * ve yeniden publish QR token'ını DEĞİŞTİRMEZ — yani fiyat değişince basılı
 * QR'lar yeniden bastırılmaz. İş değeri tam olarak budur.
 *
 * Requirement ID'leri: CRIT-JOURNEY-01, CRIT-JOURNEY-REPUBLISH-01,
 * CRIT-JOURNEY-SNAPSHOT-INTEGRITY-01, CRIT-JOURNEY-VISIBILITY-01.
 */
final class RestaurantCriticalJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_URI = '/register';

    private const VALID_PASSWORD = 'correct-horse-battery-staple-1';

    /**
     * Sabit, gizli olmayan test-local anahtar — IdentitySessionJourneyTest ile
     * aynı sözleşme. phpunit.xml APP_KEY taşımaz; 'web' middleware grubunu
     * uçtan uca çalıştırmak için session/cookie şifrelemesi bir anahtar ister.
     */
    private const TEST_APP_KEY = 'base64:KioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKio=';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => self::TEST_APP_KEY]);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * Ürünün kendi kayıt + imzalı e-posta doğrulama uçlarından geçen tek
     * gerçek hesap. Yalnız CRIT-JOURNEY-01 bunu kullanır; kalan testler
     * kayıt halkasını yeniden kanıtlamaz (IdentitySessionJourneyTest kapsar)
     * ve /register üzerindeki throttle'ı gereksiz yere tüketmez.
     */
    private function registeredAndVerifiedOwner(string $email): User
    {
        $this->withHeaders($this->jsonHeaders())->post(self::REGISTER_URI, [
            'name' => 'Ada Lovelace',
            'email' => $email,
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ])->assertSuccessful();

        $user = User::where('email', $email)->firstOrFail();

        self::assertFalse(
            $user->hasVerifiedEmail(),
            'CRIT-JOURNEY-01: kayıt hemen doğrulanmış hesap üretmemeli.'
        );

        $this->actingAs($user)->withHeaders($this->jsonHeaders())->get(URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        ));

        $verified = $user->fresh();

        self::assertTrue(
            $verified->hasVerifiedEmail(),
            'CRIT-JOURNEY-01: imzalı doğrulama linki hesabı doğrulanmış hâle getirmeli.'
        );

        return $verified;
    }

    private function verifiedOwner(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * Workspace'ten tek menü kalemine kadar her adımı gerçek HTTP ucundan kurar.
     *
     * @return array{workspaceId: int, locationId: int, menuId: int, categoryId: int, menuItemId: int}
     */
    private function buildRestaurantThroughApi(User $owner, string $brandName, string $price = '45.00'): array
    {
        $workspaceId = (int) $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson('/api/workspaces', ['name' => $brandName])
            ->assertStatus(201)->json('id');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand", [
                'name' => $brandName,
                'timezone' => 'Europe/Istanbul',
                'currency' => 'TRY',
            ])->assertStatus(201);

        $locationId = (int) $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand/locations", [
                'display_name' => 'Kadıköy Şubesi',
                'country_code' => 'TR',
                'city' => 'İstanbul',
                'address_line1' => 'Bahariye Cd. No:1',
            ])->assertStatus(201)->json('id');

        $menuId = (int) $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu", ['name' => 'Ana Menü'])
            ->assertStatus(201)->json('id');

        $categoryId = (int) $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/categories", ['name' => 'Çorbalar'])
            ->assertStatus(201)->json('id');

        $menuItemId = $this->addMenuItemThroughApi($owner, $workspaceId, $categoryId, 'Mercimek Çorbası', $price);

        return [
            'workspaceId' => $workspaceId,
            'locationId' => $locationId,
            'menuId' => $menuId,
            'categoryId' => $categoryId,
            'menuItemId' => $menuItemId,
        ];
    }

    private function addMenuItemThroughApi(
        User $owner,
        int $workspaceId,
        int $categoryId,
        string $productName,
        string $price
    ): int {
        $productId = (int) $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/products", ['name' => $productName])
            ->assertStatus(201)->json('id');

        $created = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/menu-categories/{$categoryId}/menu-items", [
                'productId' => $productId,
                'price' => $price,
                'currency' => 'TRY',
            ])->assertStatus(201);

        $menuItemId = (int) $created->json('id');

        // Dondurulan sözleşme: kalem oluşturma ucu isVisible girdisi almaz ve
        // menu_items.is_visible migration'da default(false)'tur. Yani yeni kalem
        // GİZLİ doğar; sahibi görünür yapmadan menü yayınlanamaz (publish 422
        // UnreadyDraftException::noVisibleItem). "Ürün ekle" tek başına misafire
        // asla ulaşmaz — gerçek yolculuk açık bir görünürlük adımı içerir.
        $created->assertJsonPath('isVisible', false);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson("/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/visibility", ['isVisible' => true])
            ->assertStatus(200)
            ->assertJsonPath('isVisible', true);

        return $menuItemId;
    }

    private function publishThroughApi(User $owner, int $workspaceId, int $menuId): int
    {
        return (int) $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")
            ->assertStatus(201)->json('version');
    }

    private function createQrTokenThroughApi(User $owner, int $workspaceId, int $locationId, int $menuId): string
    {
        return (string) $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes", ['menuId' => $menuId])
            ->assertStatus(201)->json('token');
    }

    // --- CRIT-JOURNEY-01 --------------------------------------------------

    public function test_one_real_account_reaches_a_scannable_public_menu_entirely_through_the_product_http_surface(): void
    {
        $owner = $this->registeredAndVerifiedOwner('ada@example.com');

        $r = $this->buildRestaurantThroughApi($owner, 'Zeytin Restoranları');

        $version = $this->publishThroughApi($owner, $r['workspaceId'], $r['menuId']);
        self::assertSame(1, $version, 'CRIT-JOURNEY-01: ilk yayın version=1 olmalı.');

        $token = $this->createQrTokenThroughApi($owner, $r['workspaceId'], $r['locationId'], $r['menuId']);
        self::assertNotSame('', $token, 'CRIT-JOURNEY-01: QR oluşturma bir token döndürmeli.');

        $this->get("/q/{$token}")
            ->assertStatus(302, 'CRIT-JOURNEY-01: /q/{token} public menüye yönlendirmeli.')
            ->assertRedirect("/menu/{$token}");

        $public = $this->get("/menu/{$token}");
        $public->assertStatus(200, 'CRIT-JOURNEY-01: misafirin gördüğü menü 200 dönmeli.');

        $html = (string) $public->getContent();
        self::assertStringContainsString('Mercimek Çorbası', $html, 'CRIT-JOURNEY-01: gerçek ürün adı misafire ulaşmalı.');
        // Biçim, belgenin dilinden gelir (CORE-12 × `docs/13` §4): `lang="en"`
        // bir belgede `TRY 45.00`, `lang="tr"` bir belgede `₺45,00`. Donan
        // sözleşme fiyatın DOĞRU olmasıdır, belirli bir yazımı değil.
        //
        // Aradaki boşluk bilerek kırılmaz boşluktur (U+00A0): para birimi
        // kodu tutardan ayrı satıra düşmemelidir.
        self::assertStringContainsString("TRY\u{00A0}45.00", $html, 'CRIT-JOURNEY-01: gerçek fiyat misafire ulaşmalı.');
    }

    // --- CRIT-JOURNEY-REPUBLISH-01 ---------------------------------------

    public function test_a_price_change_reaches_the_diner_on_the_very_same_qr_token_after_republish(): void
    {
        $owner = $this->verifiedOwner();
        $r = $this->buildRestaurantThroughApi($owner, 'Zeytin Republish');

        $this->publishThroughApi($owner, $r['workspaceId'], $r['menuId']);
        $token = $this->createQrTokenThroughApi($owner, $r['workspaceId'], $r['locationId'], $r['menuId']);

        self::assertStringContainsString(
            "TRY\u{00A0}45.00",
            (string) $this->get("/menu/{$token}")->getContent(),
            'CRIT-JOURNEY-REPUBLISH-01: yeniden yayından önce eski fiyat görünmeli.'
        );

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson("/api/workspaces/{$r['workspaceId']}/menu-items/{$r['menuItemId']}/price", [
                'price' => '52.50',
                'currency' => 'TRY',
            ])
            ->assertStatus(200)
            ->assertJsonPath('priceMinorAmount', 5250);

        $newVersion = $this->publishThroughApi($owner, $r['workspaceId'], $r['menuId']);
        self::assertSame(2, $newVersion, 'CRIT-JOURNEY-REPUBLISH-01: yeniden yayın version=2 üretmeli.');

        $public = $this->get("/menu/{$token}");
        $public->assertStatus(200, 'CRIT-JOURNEY-REPUBLISH-01: aynı token yeniden yayından sonra da çalışmalı.');

        $html = (string) $public->getContent();
        self::assertStringContainsString(
            "TRY\u{00A0}52.50",
            $html,
            'CRIT-JOURNEY-REPUBLISH-01: yeni fiyat AYNI QR token üzerinden misafire ulaşmalı — QR yeniden bastırılmamalı.'
        );
        self::assertStringNotContainsString(
            "TRY\u{00A0}45.00",
            $html,
            'CRIT-JOURNEY-REPUBLISH-01: eski fiyat yeniden yayından sonra misafire görünmemeli.'
        );
    }

    // --- CRIT-JOURNEY-SNAPSHOT-INTEGRITY-01 ------------------------------

    public function test_a_price_change_alone_never_alters_the_published_menu_until_it_is_republished(): void
    {
        $owner = $this->verifiedOwner();
        $r = $this->buildRestaurantThroughApi($owner, 'Zeytin Snapshot');

        $this->publishThroughApi($owner, $r['workspaceId'], $r['menuId']);
        $token = $this->createQrTokenThroughApi($owner, $r['workspaceId'], $r['locationId'], $r['menuId']);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson("/api/workspaces/{$r['workspaceId']}/menu-items/{$r['menuItemId']}/price", [
                'price' => '52.50',
                'currency' => 'TRY',
            ])->assertStatus(200);

        $html = (string) $this->get("/menu/{$token}")->getContent();

        self::assertStringContainsString(
            "TRY\u{00A0}45.00",
            $html,
            'CRIT-JOURNEY-SNAPSHOT-INTEGRITY-01: yayınlanmamış taslak fiyatı misafiri etkilememeli.'
        );
        self::assertStringNotContainsString(
            "TRY\u{00A0}52.50",
            $html,
            'CRIT-JOURNEY-SNAPSHOT-INTEGRITY-01: yeni fiyat publish edilmeden misafire sızmamalı.'
        );
    }

    // --- CRIT-JOURNEY-VISIBILITY-01 --------------------------------------

    public function test_an_item_hidden_through_the_api_never_reaches_the_public_menu_after_republish(): void
    {
        $owner = $this->verifiedOwner();
        $r = $this->buildRestaurantThroughApi($owner, 'Zeytin Visibility');

        $secretItemId = $this->addMenuItemThroughApi(
            $owner,
            $r['workspaceId'],
            $r['categoryId'],
            'Gizli Tarif',
            '99.00'
        );

        $this->publishThroughApi($owner, $r['workspaceId'], $r['menuId']);
        $token = $this->createQrTokenThroughApi($owner, $r['workspaceId'], $r['locationId'], $r['menuId']);

        self::assertStringContainsString(
            'Gizli Tarif',
            (string) $this->get("/menu/{$token}")->getContent(),
            'CRIT-JOURNEY-VISIBILITY-01: gizlenmeden önce ürün misafire görünmeli.'
        );

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson("/api/workspaces/{$r['workspaceId']}/menu-items/{$secretItemId}/visibility", ['isVisible' => false])
            ->assertStatus(200)
            ->assertJsonPath('isVisible', false);

        $this->publishThroughApi($owner, $r['workspaceId'], $r['menuId']);

        $html = (string) $this->get("/menu/{$token}")->getContent();

        self::assertStringNotContainsString(
            'Gizli Tarif',
            $html,
            'CRIT-JOURNEY-VISIBILITY-01: API üzerinden gizlenen ürün gerçek publish yolundan geçtikten sonra misafire ulaşmamalı.'
        );
        self::assertStringContainsString(
            'Mercimek Çorbası',
            $html,
            'CRIT-JOURNEY-VISIBILITY-01: görünür ürün gizleme sonrası da misafire ulaşmalı.'
        );
    }
}
