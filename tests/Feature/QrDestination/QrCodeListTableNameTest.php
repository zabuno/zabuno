<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * QRLIST-TABLE-NAME-01 — FF-109, `docs/104` Döngü 6.
 *
 * Masanın adı veritabanına YAZILIYOR (`qr_codes.dining_table_id`, toplu
 * üretimde `EloquentBulkQrCreationRepository`) ama liste uç noktası onu
 * düşürüyordu. Sonuç: 40 masalı bir restoranın sahibi, ekranda 43 karakterlik
 * token'lardan başka bir şey görmüyor ve "masa 12'nin kartı yırtıldı, yeniden
 * bastırayım" diyemiyordu. Bu ürünün asıl işi — bir kodu bulup yeniden
 * bastırmak — fiilen imkânsızdı.
 *
 * Sözleşme: liste her kaydın yanında ait olduğu masanın adını ve alanının
 * etiketini döndürür. Masaya bağlı olmayan kod (giriş/tek kod) için ikisi de
 * `null`'dır — uydurulmuş bir ad, hiç ad olmamasından kötüdür.
 */
final class QrCodeListTableNameTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /**
     * @return array{0: int, 1: int, 2: int} [workspaceId, locationId, menuId]
     */
    private function workspaceWithCurrentPublication(User $owner, string $slugSeed): array
    {
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

        $this->grantEntitlements($workspaceId);

        return [$workspaceId, $locationId, $menuId];
    }

    public function test_list_returns_the_real_table_name_and_area_label_for_bulk_created_codes(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qrlist-name');

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk",
            [
                'menuId' => $menuId,
                'areaSectionCount' => 2,
                'tableCount' => 3,
                'seatCountPerTable' => 4,
            ]
        )->assertStatus(201);

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->getJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes"
        );

        $response->assertStatus(200);

        $items = $response->json();
        self::assertCount(3, $items, 'QRLIST-TABLE-NAME-01: üç masa, üç kod listelenmeli.');

        $namesByToken = [];

        foreach ($items as $item) {
            self::assertArrayHasKey('tableName', $item, 'QRLIST-TABLE-NAME-01: liste her kaydın masa adını taşımalı.');
            self::assertArrayHasKey('areaLabel', $item, 'QRLIST-TABLE-NAME-01: liste her kaydın alan etiketini taşımalı.');
            $namesByToken[(string) ($item['token'] ?? '')] = $item['tableName'];
        }

        $names = array_values($namesByToken);
        sort($names);

        self::assertSame(['T1', 'T2', 'T3'], $names, 'QRLIST-TABLE-NAME-01: adlar veritabanındaki gerçek masa adları olmalı.');

        $areaLabels = array_map(static fn (array $item): mixed => $item['areaLabel'], $items);

        foreach ($areaLabels as $label) {
            self::assertContains($label, ['Area 1', 'Area 2'], 'QRLIST-TABLE-NAME-01: alan etiketi gerçek dining_areas.label olmalı.');
        }
    }

    public function test_list_returns_null_names_for_a_code_that_belongs_to_no_table(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qrlist-null');

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        )->assertStatus(201);

        $items = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->getJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes"
        )->assertStatus(200)->json();

        self::assertCount(1, $items);
        self::assertNull($items[0]['tableName'], 'QRLIST-TABLE-NAME-01: masaya bağlı olmayan kodun adı uydurulmaz, null döner.');
        self::assertNull($items[0]['areaLabel'], 'QRLIST-TABLE-NAME-01: masaya bağlı olmayan kodun alanı uydurulmaz, null döner.');
    }

    public function test_table_name_never_leaks_across_workspaces(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qrlist-tenant-a');
        [$otherWorkspaceId, $otherLocationId] = $this->workspaceWithCurrentPublication($other, 'qrlist-tenant-b');

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/tables/bulk",
            ['menuId' => $menuId, 'areaSectionCount' => 1, 'tableCount' => 2, 'seatCountPerTable' => 2]
        )->assertStatus(201);

        // Komşu kiracı, kendi listesinde yalnız kendi (boş) kayıtlarını görür.
        $items = $this->actingAs($other)->withHeaders(['Accept' => 'application/json'])->getJson(
            "/api/workspaces/{$otherWorkspaceId}/brand/locations/{$otherLocationId}/qr-codes"
        )->assertStatus(200)->json();

        self::assertSame([], $items, 'QRLIST-TABLE-NAME-01: masa adı birleştirmesi kiracı sınırını delmemeli.');
    }
}
