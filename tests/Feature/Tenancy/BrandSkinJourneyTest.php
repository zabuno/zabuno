<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Branding\BrandRampRole;
use App\Domain\Branding\BrandSkin;
use App\Domain\Branding\SkinSurface;
use App\Domain\Branding\SrgbColor;
use App\Domain\Entitlement\Entitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FF-174 — restoran kendi marka kimliğini KURAR, ürün onu OKUNUR TUTAR.
 *
 * Sahibin isteği açıktı: Kadıköy'deki kebapçı kendi bordosunu ve kendi
 * köşe biçimini seçebilmeli. Deponun kısıtı da haklıydı: kontrastı kiracıya
 * bırakmak, masadaki misafire okunmayan bir menü göstermektir. Bu testler
 * ikisinin uzlaştığı yeri dondurur:
 *
 *   · Ton seçmek plana bağlı bir EK yetkidir; yoksa ürün bunu DÜRÜSTÇE
 *     söyler (402 + hangi yeteneğin eksik olduğu), sessizce yok saymaz.
 *   · Yetenek yokken bile temel yolculuk açıktır: isim, saat dilimi ve para
 *     birimi düzenlenmeye devam eder.
 *   · Seçilen ton yayına RAMPASI VE ÖLÇÜLMÜŞ ORANLARIYLA donar; sonradan
 *     yapılan bir renk değişikliği geçmiş yayını boyamaz.
 *
 * Requirement ID'leri: BRAND-SKIN-ENTITLEMENT-01, BRAND-SKIN-BASELINE-02,
 * BRAND-SKIN-PERSIST-03, BRAND-SKIN-VARIANT-04, BRAND-SKIN-FROZEN-05.
 */
final class BrandSkinJourneyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * @param  list<string>  $entitlements
     */
    private function workspaceOn(User $owner, array $entitlements): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları', 'slug' => 'skin-'.Str::lower(Str::random(8)),
            'state' => 'active', 'created_by' => $owner->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($entitlements !== []) {
            $planId = (int) DB::table('plans')->insertGetId([
                'name' => 'Restaurant', 'code' => 'plan-'.Str::lower(Str::random(8)), 'version' => 1,
                'is_active' => true, 'sort_order' => 0,
                'entitlements' => json_encode($entitlements),
                'amount_minor' => 49900, 'currency' => 'TRY',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('subscriptions')->insert([
                'workspace_id' => $workspaceId, 'plan_id' => $planId, 'state' => 'active',
                'ends_at' => now()->addDays(30),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $workspaceId;
    }

    private function brandFor(int $workspaceId): int
    {
        return (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin Restoranları',
            'slug' => 'zeytin-'.Str::lower(Str::random(8)), 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function brandPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Zeytin Restoranları',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ], $overrides);
    }

    private function brandUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/brand";
    }

    // --- BRAND-SKIN-ENTITLEMENT-01 ----------------------------------------

    public function test_without_the_entitlement_the_product_says_so_instead_of_ignoring_the_choice(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOn($owner, []);
        $brandId = $this->brandFor($workspaceId);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson($this->brandUri($workspaceId), $this->brandPayload([
                'primary_color' => '#7b1e3a',
            ]));

        /*
            402, 403 DEĞİL: kullanıcı yetkisiz değil, planı bu yeteneği
            içermiyor. Çıkış yolu farklıdır — biri erişim talebi, diğeri plan
            yükseltmesidir (`StoreTeamInvitationController` aynı ayrımı
            yapıyor).
        */
        $response->assertStatus(402)
            ->assertJsonPath('entitlement', Entitlement::BrandingCustom->value);

        self::assertNull(
            DB::table('brands')->where('id', $brandId)->value('primary_color'),
            'BRAND-SKIN-ENTITLEMENT-01: reddedilen bir ton yazılmamalı.',
        );
    }

    // --- BRAND-SKIN-BASELINE-02 -------------------------------------------

    public function test_the_basic_journey_stays_open_without_the_entitlement(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOn($owner, []);
        $this->brandFor($workspaceId);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson($this->brandUri($workspaceId), $this->brandPayload(['name' => 'Zeytin Kebap']))
            ->assertOk()
            ->assertJsonPath('name', 'Zeytin Kebap');
    }

    // --- BRAND-SKIN-PERSIST-03 --------------------------------------------

    /**
     * Bu test bir kusuru da kapatıyor: `Brand` modelinin doldurulabilir alan
     * listesinde renk sütunları YOKTU, yani ekrandan girilen ton sessizce
     * düşüyordu. İstek 200 dönüyordu ve hiçbir şey kaydedilmiyordu.
     */
    public function test_with_the_entitlement_the_chosen_tone_is_actually_stored(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOn($owner, [Entitlement::BrandingCustom->value]);
        $brandId = $this->brandFor($workspaceId);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson($this->brandUri($workspaceId), $this->brandPayload([
                'primary_color' => '#7B1E3A',
                'skin_variant' => 'b',
            ]))
            ->assertOk()
            ->assertJsonPath('primary_color', '#7b1e3a')
            ->assertJsonPath('skin_variant', 'b');

        $row = DB::table('brands')->where('id', $brandId)->first();

        self::assertSame('#7b1e3a', $row?->primary_color);
        self::assertSame('b', $row?->skin_variant);
    }

    // --- BRAND-SKIN-VARIANT-04 --------------------------------------------

    public function test_a_form_variant_the_token_layer_does_not_define_is_refused(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOn($owner, [Entitlement::BrandingCustom->value]);
        $this->brandFor($workspaceId);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson($this->brandUri($workspaceId), $this->brandPayload(['skin_variant' => 'z']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('skin_variant');
    }

    // --- BRAND-SKIN-FROZEN-05 ---------------------------------------------

    public function test_the_publication_freezes_the_derived_ramp_and_its_measured_ratios(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOn($owner, [Entitlement::BrandingCustom->value]);
        $brandId = $this->brandFor($workspaceId);

        // Ürünün korktuğu ton: açık sarı. Kiracı seçebilmeli, misafir
        // okuyabilmeli.
        DB::table('brands')->where('id', $brandId)
            ->update(['primary_color' => '#ffe066', 'skin_variant' => 'c']);

        $menuId = $this->readyMenu($workspaceId, $brandId);

        $snapshot = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")
            ->assertCreated()
            ->json('snapshot');

        $skin = BrandSkin::fromSnapshot($snapshot['identity']['skin'] ?? []);

        self::assertNotNull($skin, 'BRAND-SKIN-FROZEN-05: rampa yayına yazılmalı.');
        self::assertSame('#ffe066', $skin->seedHex, 'Kiracının verdiği ton girdi olarak da saklanır.');
        self::assertSame('c', $skin->variant->value);
        self::assertTrue($skin->meetsContrastFloor());

        // ORAN İDDİA DEĞİL, ÖLÇÜM: donan renkten yeniden hesaplanınca aynı
        // çıkmak zorunda.
        foreach (SkinSurface::cases() as $surface) {
            foreach ($skin->ramp($surface)->values() as $value) {
                self::assertEqualsWithDelta(
                    SrgbColor::fromHex($value->hex)->contrastRatio(SrgbColor::fromHex($value->againstHex)),
                    $value->ratio,
                    0.01,
                );
                self::assertGreaterThanOrEqual($value->floor, $value->ratio);
            }
        }

        // Açık sarı METİN olarak kullanılamaz; ürün onu koyulaştırmış olmalı.
        $ink = $skin->ramp(SkinSurface::Light)->role(BrandRampRole::AccentInk);
        self::assertTrue($ink->adjusted);
        self::assertNotSame('#ffe066', $ink->hex);

        // Sonradan değişen marka, geçmiş yayını boyamaz.
        DB::table('brands')->where('id', $brandId)->update(['primary_color' => '#0000ff']);

        $stored = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/current")
            ->json('snapshot.identity.skin.seed');

        self::assertSame('#ffe066', $stored, 'Yayın, sahibin onayladığı hâldir.');
    }

    public function test_a_brand_without_a_tone_publishes_without_a_skin_block(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOn($owner, [Entitlement::BrandingCustom->value]);
        $brandId = $this->brandFor($workspaceId);
        $menuId = $this->readyMenu($workspaceId, $brandId);

        $snapshot = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")
            ->assertCreated()
            ->json('snapshot');

        self::assertArrayNotHasKey(
            'skin',
            $snapshot['identity'],
            'Seçmeyen restoran, seçmiş gibi gösterilmez.',
        );
    }

    private function readyMenu(int $workspaceId, int $brandId): int
    {
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

        return $menuId;
    }
}
