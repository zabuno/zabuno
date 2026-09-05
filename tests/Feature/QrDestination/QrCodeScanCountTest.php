<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\Entitlement\Entitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * QRLIST-SCAN-COUNT-01 — panel v3 kanonik kaynağı (`docs/109` §6.7).
 *
 * Kaynağın QR ekranında her masa kartının üstünde bir TARAMA SAYISI var ve
 * taraması sıfır olan masa ayırt ediliyor. Bu sayı bir süs değil, ekranın tek
 * teşhis aracı: kırk masalık bir restoranda "Masa 17'nin kartı düşmüş ya da
 * masanın altında kalmış" bilgisini başka hiçbir yerden okuyamazsınız —
 * misafir şikâyet etmez, sadece menüye bakmadan sipariş verir.
 *
 * ÜÇ ŞEY BURADA KİLİTLENİYOR:
 *
 *   1. **Sayı UYDURULMAZ.** `analytics_events` tablosunda o koda ait
 *      `qr_resolve` satırları neyse odur. Rastgele bir yer tutucu, sahibin
 *      gerçek bir kararı (kartı yenile / masayı kapat) yanlış veriyle
 *      vermesine yol açar.
 *   2. **Sıfır bir cevaptır, boşluk değildir.** Hiç taranmamış kod `0` döner;
 *      `null` dönmek "bilmiyoruz" demektir ve ekran o iki hâli farklı çizmek
 *      zorundadır.
 *   3. **Ölçüm PLANA BAĞLIDIR.** Analitik raporlama CORE-04 kapsamında
 *      ücretli bir yetenektir (`ShowAnalyticsSummaryController` 402 döner).
 *      QR listesi üzerinden aynı veriyi ücretsiz sızdırmak, ödeme duvarını
 *      arka kapıdan açmak olurdu. Yetenek yoksa alan `null`'dır — sıfır
 *      DEĞİL, çünkü kodun taranmadığını değil, sayının bize kapalı olduğunu
 *      söylüyoruz.
 */
final class QrCodeScanCountTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /**
     * @param  list<Entitlement>|null  $entitlements
     * @return array{0: int, 1: int, 2: int} [workspaceId, locationId, menuId]
     */
    private function workspaceWithCurrentPublication(
        User $owner,
        string $slugSeed,
        ?array $entitlements = null,
    ): array {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$slugSeed,
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
            'name' => 'Marka '.$slugSeed,
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
            'display_name' => 'Şube '.$slugSeed,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$slugSeed,
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

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => []]),
            'published_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId,
            'current_publication_id' => $publicationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->grantEntitlements($workspaceId, $entitlements);

        return [$workspaceId, $locationId, $menuId];
    }

    private function recordScan(int $workspaceId, int $locationId, int $menuId, int $qrCodeId): void
    {
        DB::table('analytics_events')->insert([
            'event_id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'qr_code_id' => $qrCodeId,
            'menu_id' => $menuId,
            'event_type' => AnalyticsEventType::QrResolve->value,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_list_reports_the_real_scan_count_per_code(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qrscan-real');

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk",
            ['menuId' => $menuId, 'areaSectionCount' => 1, 'tableCount' => 2, 'seatCountPerTable' => 4]
        )->assertStatus(201);

        $codeIds = DB::table('qr_codes')->where('location_id', $locationId)->orderBy('id')->pluck('id')->all();
        self::assertCount(2, $codeIds);

        // İlk masa üç kez tarandı, ikincisi hiç.
        $this->recordScan($workspaceId, $locationId, $menuId, (int) $codeIds[0]);
        $this->recordScan($workspaceId, $locationId, $menuId, (int) $codeIds[0]);
        $this->recordScan($workspaceId, $locationId, $menuId, (int) $codeIds[0]);

        // Menü açılışı TARAMA DEĞİLDİR: aynı ziyaretçinin ikinci olayıdır ve
        // ikisini toplamak aynı misafiri iki kez saymak olurdu.
        DB::table('analytics_events')->insert([
            'event_id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'qr_code_id' => (int) $codeIds[0],
            'menu_id' => $menuId,
            'event_type' => AnalyticsEventType::MenuOpen->value,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $items = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->getJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes"
        )->assertStatus(200)->json();

        $countsById = [];

        foreach ($items as $item) {
            self::assertArrayHasKey('scanCount', $item, 'QRLIST-SCAN-COUNT-01: liste her kaydın tarama sayısını taşımalı.');
            $countsById[(int) $item['id']] = $item['scanCount'];
        }

        self::assertSame(3, $countsById[(int) $codeIds[0]], 'QRLIST-SCAN-COUNT-01: sayı gerçek qr_resolve satırlarından gelmeli.');
        self::assertSame(0, $countsById[(int) $codeIds[1]], 'QRLIST-SCAN-COUNT-01: hiç taranmamış kod 0 döner — sıfır bir cevaptır.');
    }

    public function test_scan_count_is_null_when_the_plan_does_not_include_analytics(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        // Analitik raporlama DIŞINDA her yetenek verilir: toplu üretim çalışsın
        // ama ölçüm kapalı olsun.
        $withoutAnalytics = array_values(array_filter(
            Entitlement::cases(),
            static fn (Entitlement $entitlement): bool => $entitlement !== Entitlement::AnalyticsReporting,
        ));

        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication(
            $owner,
            'qrscan-noplan',
            $withoutAnalytics,
        );

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk",
            ['menuId' => $menuId, 'areaSectionCount' => 1, 'tableCount' => 1, 'seatCountPerTable' => 4]
        )->assertStatus(201);

        $qrCodeId = (int) DB::table('qr_codes')->where('location_id', $locationId)->value('id');
        $this->recordScan($workspaceId, $locationId, $menuId, $qrCodeId);

        $items = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->getJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes"
        )->assertStatus(200)->json();

        self::assertCount(1, $items);
        self::assertNull(
            $items[0]['scanCount'],
            'QRLIST-SCAN-COUNT-01: plan ölçümü içermiyorsa sayı `null` döner — 0 demek, kodun taranmadığını söylemek olurdu.',
        );
    }

    public function test_scan_count_never_counts_another_tenants_events(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qrscan-tenant-a');
        [$otherWorkspaceId, $otherLocationId, $otherMenuId] = $this->workspaceWithCurrentPublication($other, 'qrscan-tenant-b');

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        )->assertStatus(201);

        $this->actingAs($other)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$otherWorkspaceId}/brand/locations/{$otherLocationId}/qr-codes",
            ['menuId' => $otherMenuId]
        )->assertStatus(201);

        $otherCodeId = (int) DB::table('qr_codes')->where('location_id', $otherLocationId)->value('id');
        $this->recordScan($otherWorkspaceId, $otherLocationId, $otherMenuId, $otherCodeId);
        $this->recordScan($otherWorkspaceId, $otherLocationId, $otherMenuId, $otherCodeId);

        $items = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->getJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes"
        )->assertStatus(200)->json();

        self::assertCount(1, $items);
        self::assertSame(
            0,
            $items[0]['scanCount'],
            'QRLIST-SCAN-COUNT-01: komşu kiracının taramaları bu listeye sızmamalı.',
        );
    }
}
