<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ŞUBE KARTINDAKİ MASA SAYISI — `docs/109` §6.4 ve kaynak `panel.dc.html`
 * (`data-screen-label="Şubeler"`).
 *
 * NEDEN KIRMIZI: kaynağın şube kartı üç sayıyı yan yana gösteriyor ve
 * birincisi "N masa". Bugün şube listesi ucu (`GET
 * /api/workspaces/{w}/brand/locations`) yalnız adres alanlarını döndürüyor;
 * masa sayısı hiçbir yerde yok. Ekranın onu ÇİZMESİ için tek yol, her kart
 * için ayrı bir QR listesi isteği atmak olurdu — beş şubeli bir markada beş
 * ek istek, beş ayrı yükleniyor durumu ve beş ayrı hata yolu.
 *
 * Sayı UYDURULMAZ: `dining_tables` tablosu gerçek satırları tutuyor
 * (`2026_08_22_000006_create_dining_areas_and_tables`), ölçüm ordan okunur.
 * Sıfır masa gerçek bir cevaptır ve "0" olarak döner — alanın yokluğu ile
 * "henüz masa girilmedi" aynı şey değildir.
 */
final class LocationTableCountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceOwnedBy(User $owner, string $name, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => $name,
            'slug' => $slug,
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

        return $workspaceId;
    }

    /**
     * @return array<string, string>
     */
    private function validBrandPayload(string $name = 'Zeytin Restoranları'): array
    {
        return [
            'name' => $name,
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validLocationPayload(string $displayName): array
    {
        return [
            'display_name' => $displayName,
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Bağdat Caddesi No:1',
        ];
    }

    private function seedTables(int $workspaceId, int $locationId, int $count): void
    {
        $areaId = (int) DB::table('dining_areas')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'label' => 'Salon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($index = 1; $index <= $count; $index++) {
            DB::table('dining_tables')->insert([
                'workspace_id' => $workspaceId,
                'location_id' => $locationId,
                'area_id' => $areaId,
                'name' => "Masa {$index}",
                'seat_count' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // --- LOCATION-TABLE-COUNT-01 ------------------------------------------

    public function test_location_list_carries_the_real_table_count_per_location(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-table-count-01');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand", $this->validBrandPayload())
            ->assertStatus(201);

        $kadikoy = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand/locations", $this->validLocationPayload('Zeytin Kadıköy'));
        $kadikoy->assertStatus(201);

        $besiktas = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand/locations", $this->validLocationPayload('Zeytin Beşiktaş'));
        $besiktas->assertStatus(201);

        $kadikoyId = (int) $kadikoy->json('id');
        $besiktasId = (int) $besiktas->json('id');

        $this->seedTables($workspaceId, $kadikoyId, 3);
        // Beşiktaş BİLEREK boş bırakılır: henüz kurulumdaki bir şube.

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations");

        $response->assertStatus(200);

        $byId = [];

        foreach ((array) $response->json() as $item) {
            $byId[(int) $item['id']] = $item;
        }

        self::assertArrayHasKey(
            'table_count',
            $byId[$kadikoyId],
            'LOCATION-TABLE-COUNT-01: şube listesi masa sayısını taşımalı; kart onu ayrı bir istek atmadan çizebilmeli.'
        );
        self::assertSame(
            3,
            $byId[$kadikoyId]['table_count'],
            'LOCATION-TABLE-COUNT-01: masa sayısı `dining_tables` satırlarından okunmalı.'
        );
        self::assertSame(
            0,
            $byId[$besiktasId]['table_count'],
            'LOCATION-TABLE-COUNT-01: masası olmayan şube için 0 döner — alanın yokluğu değil, gerçek sıfır.'
        );
    }

    // --- LOCATION-TABLE-COUNT-TENANT-01 -----------------------------------

    public function test_table_count_never_leaks_another_locations_tables(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-table-count-tenant');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand", $this->validBrandPayload())
            ->assertStatus(201);

        $mine = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand/locations", $this->validLocationPayload('Zeytin Kadıköy'));
        $mine->assertStatus(201);
        $mineId = (int) $mine->json('id');

        $otherOwner = $this->verifiedUser();
        $otherWorkspaceId = $this->workspaceOwnedBy($otherOwner, 'Deniz Kebap', 'deniz-table-count-tenant');
        $this->actingAs($otherOwner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$otherWorkspaceId}/brand", $this->validBrandPayload('Deniz Kebap'))
            ->assertStatus(201);
        $other = $this->actingAs($otherOwner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$otherWorkspaceId}/brand/locations", $this->validLocationPayload('Deniz Şubesi'));
        $other->assertStatus(201);

        $this->seedTables($otherWorkspaceId, (int) $other->json('id'), 7);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations");

        $response->assertStatus(200);

        $body = (array) $response->json();

        self::assertSame(
            0,
            $body[0]['table_count'] ?? null,
            'LOCATION-TABLE-COUNT-TENANT-01: sayım şubeye bağlanmalı; başka bir kiracının masaları kartta görünemez.'
        );
        self::assertSame($mineId, (int) $body[0]['id']);
    }
}
