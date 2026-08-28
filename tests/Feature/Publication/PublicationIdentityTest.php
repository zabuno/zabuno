<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0-03 RED — misafir menüsünde restoran kimliği (`docs/75`).
 *
 * MÜŞTERİ SORUNU. Misafir masadaki karekodu okutur ve gördüğü ilk kelime
 * "Menü"dür. Restoranın adı yok, adresi yok, telefonu yok. Sahip için bu
 * bir marka kaybı; misafir için "doğru yere mi geldim?" sorusu; paylaşılan
 * bir bağlantıda ise sayfa kimsenin tanımadığı bir sayfa.
 *
 * KİMLİK SNAPSHOT'A YAZILIR, canlı sorguyla çekilmez. Aksi hâlde şubenin
 * adı ya da telefonu değiştiği anda GEÇMİŞ bir yayın da sessizce değişirdi
 * — oysa yayın, sahibin "bunu onayladım" dediği donmuş hâldir.
 *
 * Requirement IDs: PUB-IDENTITY-SNAPSHOT-01, PUB-IDENTITY-FROZEN-01,
 * PUB-IDENTITY-HEADING-01, PUB-IDENTITY-TEL-01, PUB-IDENTITY-ABSENT-01.
 */
final class PublicationIdentityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:int,1:int,2:int,3:int} [workspaceId, brandId, locationId, menuId] */
    private function readyMenu(User $owner, string $seed, ?string $phone = '+90 216 555 12 34'): array
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
            'currency' => 'TRY', 'contact_phone' => $phone,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy Şubesi', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1', 'postal_code' => '34710',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId,
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

        return [$workspaceId, $brandId, $locationId, $menuId];
    }

    private function publish(User $owner, int $workspaceId, int $menuId): array
    {
        return $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")
            ->json();
    }

    // --- PUB-IDENTITY-SNAPSHOT-01 -----------------------------------------

    public function test_the_publication_snapshot_carries_the_restaurant_identity(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, , , $menuId] = $this->readyMenu($owner, 'kimlik-1');

        $identity = $this->publish($owner, $workspaceId, $menuId)['snapshot']['identity'] ?? null;

        self::assertIsArray($identity, 'PUB-IDENTITY-SNAPSHOT-01: yayın snapshot\'ı kimlik taşımalı.');
        self::assertSame('Zeytin Restoranları', $identity['brandName'] ?? null);
        self::assertSame('Kadıköy Şubesi', $identity['locationName'] ?? null);
        self::assertSame('+90 216 555 12 34', $identity['phone'] ?? null);

        // Adres TEK satırda kurulur: misafirin okuduğu şey bir form değil,
        // bir adres. Şehir ve posta kodu da içinde olmalı.
        $address = (string) ($identity['addressLine'] ?? '');
        self::assertStringContainsString('Bahariye Cd. No:1', $address);
        self::assertStringContainsString('İstanbul', $address);
        self::assertStringContainsString('34710', $address);
    }

    // --- PUB-IDENTITY-FROZEN-01 -------------------------------------------

    public function test_renaming_the_brand_does_not_change_an_existing_publication(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $brandId, , $menuId] = $this->readyMenu($owner, 'kimlik-2');

        $first = $this->publish($owner, $workspaceId, $menuId);

        DB::table('brands')->where('id', $brandId)->update([
            'name' => 'Zeytin Kebap Evi',
            'contact_phone' => '+90 216 555 99 99',
        ]);

        // Aynı yayın, sunucudan yeniden okunduğunda hâlâ ESKİ adı taşır.
        $stored = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current")
            ->json();

        self::assertSame(
            'Zeytin Restoranları',
            $stored['snapshot']['identity']['brandName'] ?? null,
            'PUB-IDENTITY-FROZEN-01: yayınlanmış bir menü, sonradan değişen marka adını göstermemeli.'
        );
        self::assertSame('+90 216 555 12 34', $stored['snapshot']['identity']['phone'] ?? null);

        // YENİ yayın yeni adı alır.
        $second = $this->publish($owner, $workspaceId, $menuId);
        self::assertSame('Zeytin Kebap Evi', $second['snapshot']['identity']['brandName'] ?? null);
        self::assertSame('+90 216 555 99 99', $second['snapshot']['identity']['phone'] ?? null);
        self::assertGreaterThan((int) $first['version'], (int) $second['version']);
    }

    // --- PUB-IDENTITY-HEADING-01 / PUB-IDENTITY-TEL-01 --------------------

    public function test_the_guest_page_headline_is_the_restaurant_name_and_the_phone_is_callable(): void
    {
        $html = view('public-menu', ['snapshot' => [
            'identity' => [
                'brandName' => 'Zeytin Restoranları',
                'locationName' => 'Kadıköy Şubesi',
                'addressLine' => 'Bahariye Cd. No:1, 34710 İstanbul',
                'phone' => '+90 216 555 12 34',
            ],
            'categories' => [[
                'name' => 'Başlangıçlar',
                'menuItems' => [[
                    'productName' => 'Kahve', 'priceMinorAmount' => 4250,
                    'currencyCode' => 'TRY', 'allergens' => [],
                ]],
            ]],
        ]])->render();

        self::assertMatchesRegularExpression(
            '#<h1[^>]*>\s*Zeytin Restoranları\s*</h1>#u',
            $html,
            'PUB-IDENTITY-HEADING-01: sayfanın başlığı restoranın gerçek adı olmalı.'
        );
        self::assertDoesNotMatchRegularExpression(
            '#<h1[^>]*>\s*Menü\s*</h1>#u',
            $html,
            'PUB-IDENTITY-HEADING-01: sabit "Menü" metni artık başlık değildir.'
        );

        self::assertStringContainsString('Kadıköy Şubesi', $html);
        self::assertStringContainsString('Bahariye Cd. No:1, 34710 İstanbul', $html);

        // Telefon TIKLANABİLİR. Misafir masada numarayı elle yazmaz;
        // `tel:` içinde boşluk bırakmak bazı telefonlarda çağrıyı bozar.
        self::assertMatchesRegularExpression(
            '#<a[^>]+href="tel:\+902165551234"#',
            $html,
            'PUB-IDENTITY-TEL-01: telefon tel: bağlantısı olmalı ve boşluksuz normalize edilmeli.'
        );
    }

    // --- PUB-IDENTITY-ABSENT-01 -------------------------------------------

    public function test_a_snapshot_without_identity_still_renders(): void
    {
        // Kimlik alanı EKLENMEDEN önce yayınlanmış menüler hâlâ vardır.
        // Onlar için sayfa bozulmaz; başlık, sunucunun canlı olarak
        // bildiği ada düşer. Donmuş bir değer YOKSA donmuşluk ihlali de
        // yoktur.
        $html = view('public-menu', [
            'snapshot' => ['categories' => []],
            'fallbackBrandName' => 'Eski Marka',
        ])->render();

        self::assertMatchesRegularExpression('#<h1[^>]*>\s*Eski Marka\s*</h1>#u', $html);
        self::assertStringNotContainsString('tel:', $html);

        // Hiçbir ad bilinmiyorsa sayfa yine de ayakta kalır.
        $bare = view('public-menu', ['snapshot' => ['categories' => []]])->render();
        self::assertStringContainsString('<h1', $bare);
    }
}
