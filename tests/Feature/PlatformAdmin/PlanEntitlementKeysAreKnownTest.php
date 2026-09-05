<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Domain\Entitlement\Entitlement;
use App\Models\User;
use Database\Seeders\PlanCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * YAZIM HATASI EKRANDA GÖRÜNÜR — `docs/113` §10.2.
 *
 * `plans.entitlements` serbest metin kabul ediyordu ve `Entitlement` enum'u
 * tanımadığı anahtarı doğru biçimde YOK SAYIYORDU ("bilinmeyen asla yetki
 * vermez"). İki davranış tek başına doğru, birlikte sinsiydi: superadmin
 * `brandng.custom` yazdığında plan 201 dönüyor, ekranda yetenek yazıyor,
 * ama restoran o yeteneği hiç alamıyordu. Kimse bir hata görmüyordu.
 *
 * `branding.custom` eklenirken doğrulama enum'a bağlanır; aksi hâlde yeni
 * değer ilk yazım hatasında sessizce hiçbir şey açmaz.
 */
final class PlanEntitlementKeysAreKnownTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role' => 'super_admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Growth',
            'code' => 'growth-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => [Entitlement::BrandingCustom->value],
            'amount_minor' => 4990,
            'currency' => 'TRY',
            'sort_order' => 3,
        ], $overrides);
    }

    public function test_a_mistyped_entitlement_is_refused_instead_of_silently_granting_nothing(): void
    {
        $this->actingAs($this->superAdmin())
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/admin/plans', $this->payload([
                'entitlements' => ['brandng.custom'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('entitlements.0');

        self::assertSame(0, DB::table('plans')->count(), 'Reddedilen bir plan yazılmamalı.');
    }

    public function test_a_known_entitlement_is_accepted(): void
    {
        $this->actingAs($this->superAdmin())
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/admin/plans', $this->payload())
            ->assertCreated()
            ->assertJsonPath('entitlements', [Entitlement::BrandingCustom->value]);
    }

    /**
     * Marka özelleştirmesi bir EK yetkidir; temel yolculuğu kapatmaz.
     * Ücretsiz kademe menüsünü yayınlamaya ve karekod basmaya devam eder,
     * yalnız kendi tonunu seçemez.
     */
    public function test_the_free_tier_does_not_carry_brand_customisation_but_the_paid_tiers_do(): void
    {
        $catalogue = PlanCatalogueSeeder::catalogue();

        self::assertNotContains(Entitlement::BrandingCustom->value, $catalogue['starter']['entitlements']);
        self::assertContains(Entitlement::BrandingCustom->value, $catalogue['restaurant']['entitlements']);
        self::assertContains(Entitlement::BrandingCustom->value, $catalogue['team']['entitlements']);
    }
}
