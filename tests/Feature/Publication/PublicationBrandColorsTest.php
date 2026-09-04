<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FF-89 — marka renkleri misafir menüsüne ULAŞIR.
 *
 * Sahibin isteği (2026-09-04) marka renklerini düzenlenebilir yaptı (FF-88).
 * Ama düzenlenen bir değer hiçbir yerde görünmüyorsa, o ekran bir söz verip
 * tutmuyor demektir: profil ekranı "bu iki renk yayınlanan menünüzde
 * kullanılır" yazıyor. Bu testler o cümleyi doğru kılar.
 *
 * Renk KİMLİKLE BİRLİKTE DONAR (`docs/75`). Marka rengi yarın değişirse
 * dünkü yayın değişmez — yayın, sahibin "bunu onayladım" dediği hâldir.
 *
 * Requirement ID'leri: PUB-BRANDCOLOR-SNAPSHOT-01, PUB-BRANDCOLOR-FROZEN-02,
 * PUB-BRANDCOLOR-RENDER-03, PUB-BRANDCOLOR-ABSENT-04.
 */
final class PublicationBrandColorsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:int,1:int,2:int,3:string} [workspaceId, brandId, menuId, publicKey] */
    private function readyMenu(User $owner, string $seed, ?string $primary, ?string $secondary): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları', 'slug' => $seed, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin Restoranları',
            'slug' => $seed.'-brand', 'locale' => 'tr', 'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY', 'primary_color' => $primary, 'secondary_color' => $secondary,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy Şubesi', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1', 'postal_code' => '34710',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicKey = Str::lower(Str::random(10));

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => $publicKey, 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => 'Başlangıçlar', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Kahve',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 4250, 'currency_code' => 'TRY', 'position' => 0,
            'is_visible' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$workspaceId, $brandId, $menuId, $publicKey];
    }

    private function publish(User $owner, int $workspaceId, int $menuId): array
    {
        return $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")
            ->json();
    }

    // --- PUB-BRANDCOLOR-SNAPSHOT-01 ---------------------------------------

    public function test_the_publication_snapshot_carries_the_brand_colours(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, , $menuId] = $this->readyMenu($owner, 'renk-1', '#C8102E', '#1B4332');

        $identity = $this->publish($owner, $workspaceId, $menuId)['snapshot']['identity'] ?? null;

        self::assertIsArray($identity);
        self::assertSame('#c8102e', $identity['primaryColor'] ?? null, 'PUB-BRANDCOLOR-SNAPSHOT-01: birincil renk yayında taşınmalı.');
        self::assertSame('#1b4332', $identity['secondaryColor'] ?? null);
    }

    // --- PUB-BRANDCOLOR-FROZEN-02 -----------------------------------------

    public function test_changing_the_brand_colour_does_not_repaint_an_existing_publication(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $brandId, $menuId] = $this->readyMenu($owner, 'renk-2', '#C8102E', null);

        $this->publish($owner, $workspaceId, $menuId);

        DB::table('brands')->where('id', $brandId)->update(['primary_color' => '#0000FF']);

        $stored = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current")
            ->json();

        self::assertSame(
            '#c8102e',
            $stored['snapshot']['identity']['primaryColor'] ?? null,
            'PUB-BRANDCOLOR-FROZEN-02: yayınlanmış menü, sonradan değişen rengi almamalı.'
        );
    }

    // --- PUB-BRANDCOLOR-RENDER-03 -----------------------------------------

    public function test_the_guest_page_paints_the_accent_with_the_published_brand_colour(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, , $menuId, $publicKey] = $this->readyMenu($owner, 'renk-3', '#C8102E', '#1B4332');

        $this->publish($owner, $workspaceId, $menuId);

        // Slugsuz adres kanonik adrese taşınır (`MenuPublicAddress`).
        $page = $this->followingRedirects()->get("/menu/{$publicKey}");
        $page->assertStatus(200);

        $html = $page->getContent();
        self::assertStringContainsString('--qr-brand: #c8102e', $html, 'PUB-BRANDCOLOR-RENDER-03: misafir sayfası marka rengini kullanmalı.');
        self::assertStringContainsString('--qr-brand-secondary: #1b4332', $html);
    }

    // --- PUB-BRANDCOLOR-ABSENT-04 -----------------------------------------

    public function test_a_brand_without_colours_keeps_the_neutral_default(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, , $menuId, $publicKey] = $this->readyMenu($owner, 'renk-4', null, null);

        $this->publish($owner, $workspaceId, $menuId);

        $page = $this->followingRedirects()->get("/menu/{$publicKey}");
        $page->assertStatus(200);

        /*
            Renk seçmemiş restoran, seçmiş gibi gösterilmez. Boş bir değeri
            CSS'e yazmak, sayfanın vurgu rengini tarayıcının insafına
            bırakırdı.
        */
        self::assertStringNotContainsString('--qr-brand:', $page->getContent(), 'PUB-BRANDCOLOR-ABSENT-04: renk yoksa marka değişkeni hiç yazılmamalı.');
    }
}
