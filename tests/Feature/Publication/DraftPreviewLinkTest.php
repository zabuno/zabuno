<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TELEFONDA ÖNİZLE — taslağın KISA ÖMÜRLÜ, İMZALI adresi (sahibin
 * 2026-09-05 kararı; kanonik kaynakta "Telefonda önizle" düğmesi).
 *
 * NEDEN BU TESTLER ÖNCE YAZILDI: bugün taslağı misafirin göreceği biçimde
 * gösteren hiçbir adres yok. Restoran sahibinin gerçek yolculuğu şudur:
 * fiyatları düzeltir, telefonu eline alır ve "masadaki misafir bunu nasıl
 * görecek?" diye bakmak ister. Bunu yapmanın tek yolu yayınlamaktı — yani
 * kontrol etmek için önce riski almak. Bu adres o sırayı tersine çevirir.
 *
 * Üç kural bu dosyada kanıtlanır:
 *   1. Adres YAYIN ADRESİ DEĞİLDİR: misafirin kalıcı adresi değişmez ve
 *      önizleme, yayınlanmış sürümü değil TASLAĞI gösterir.
 *   2. SÜRESİ DOLAR: imza geçersizken sayfa açılmaz.
 *   3. ARAMA MOTORUNA KAPALIDIR (`noindex`) ve sayfanın kendisi bunun bir
 *      önizleme olduğunu YAZAR.
 */
final class DraftPreviewLinkTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * @return array{0: int, 1: int, 2: int} [workspaceId, locationId, menuId]
     */
    private function workspaceWithReadyMenu(User $owner, string $slugSeed): array
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

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Kebaplar',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Adana Kebap',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 32000,
            'currency_code' => 'TRY',
            'position' => 0,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId, $menuId];
    }

    public function test_owner_gets_a_short_lived_signed_preview_address(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-prev-mint');

        $response = $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/draft-preview-link"
        );

        $response->assertStatus(201);

        $url = (string) $response->json('url');
        self::assertNotSame('', $url);
        self::assertStringContainsString('signature=', $url, 'Önizleme adresi İMZALIDIR.');
        self::assertStringContainsString('expires=', $url, 'Önizleme adresinin bir SON KULLANMA anı vardır.');
        self::assertNotEmpty($response->json('expiresAt'));

        // Misafirin KALICI adresi bu değildir ve onunla karıştırılamaz.
        $publicKey = (string) DB::table('menus')->where('id', $menuId)->value('public_key');
        self::assertStringNotContainsString("/menu/{$publicKey}", $url);
    }

    public function test_the_preview_shows_the_draft_and_says_it_is_not_public(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-prev-show');

        $url = (string) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/draft-preview-link"
        )->json('url');

        $page = $this->get($url);

        $page->assertStatus(200);
        $page->assertSee('Adana Kebap');
        // Arama motoruna KAPALI: bu sayfa hiçbir zaman indekslenmemeli.
        $page->assertSee('noindex', false);
        // Sayfanın KENDİSİ ne olduğunu söyler; adresi paylaşan kişi de,
        // gösterilen kişi de bunun canlı menü olmadığını görür.
        $page->assertSee('preview', false);
    }

    public function test_an_expired_signature_no_longer_opens_the_preview(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-prev-expire');

        $url = (string) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/draft-preview-link"
        )->json('url');

        Carbon::setTestNow(Carbon::now()->addDay());
        $page = $this->get($url);
        Carbon::setTestNow();

        $page->assertStatus(403);
    }

    public function test_a_tampered_address_is_refused(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-prev-tamper');
        [, , $otherMenuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-prev-tamper-2');

        $url = (string) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/draft-preview-link"
        )->json('url');

        /*
            İmza YETKİDİR: adresteki menü kimliğini elle değiştirip başka bir
            menünün taslağını okumak mümkün olmamalı.
        */
        $tampered = str_replace("/{$menuId}?", "/{$otherMenuId}?", $url);

        $this->get($tampered)->assertStatus(403);
    }

    public function test_a_stranger_cannot_mint_a_preview_link(): void
    {
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-prev-authz');

        $this->actingAs($stranger)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/draft-preview-link"
        )->assertStatus(404);
    }
}
