<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1-03 RED — basılı kod ölmez, taşınır (`docs/81`).
 *
 * MÜŞTERİ SORUNU. Sahip 40 masa için karekod bastırdı, sonra menüsünü
 * yeniden düzenledi ve kodların YENİ menüye bakmasını istiyor — yapamıyor.
 * Ya da bir kodu yanlışlıkla devre dışı bıraktı; geri açamıyor ve masadaki
 * kâğıt KALICI olarak ölü. Her iki durumda tek çare yeniden bastırmak —
 * yani bu ürünün temel vaadinin ihlali.
 *
 * Requirement IDs: QR-RETARGET-TOKEN-STABLE-01, QR-RETARGET-HISTORY-01,
 * QR-RETARGET-LOCATION-01, QR-ENABLE-01, QR-DISABLED-DEAD-END-01,
 * QR-LIFECYCLE-AUTHZ-01.
 */
final class QrCodeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{owner:User,workspaceId:int,locationId:int,menuA:int,menuB:int,qrId:int,token:string} */
    private function scenario(string $seed): array
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
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ŞEMA: şube başına TEK menü (`menus.location_id` tekil).
        // Dolayısıyla ikinci menü ikinci şubeye aittir — ve hedef
        // değiştirmenin gerçek anlamı da budur: basılı bir kod artık başka
        // bir şubenin menüsünü gösterir.
        $secondLocationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Beşiktaş', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Barbaros Bulvarı 1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuIds = [];

        foreach ([[$locationId, 'Kadıköy Menüsü'], [$secondLocationId, 'Beşiktaş Menüsü']] as [$forLocation, $name]) {
            $menuIds[] = (int) DB::table('menus')->insertGetId([
                'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId,
                'location_id' => $forLocation, 'name' => $name, 'state' => 'draft',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $token = Str::random(43);

        $qrId = (int) DB::table('qr_codes')->insertGetId([
            'workspace_id' => $workspaceId, 'location_id' => $locationId,
            'token' => $token, 'state' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $destinationId = (int) DB::table('qr_destinations')->insertGetId([
            'qr_code_id' => $qrId, 'destination_type' => 'published_menu',
            'menu_id' => $menuIds[0], 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('qr_code_current_destinations')->insert([
            'qr_code_id' => $qrId, 'qr_destination_id' => $destinationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'owner' => $owner, 'workspaceId' => $workspaceId, 'locationId' => $locationId,
            'menuA' => $menuIds[0], 'menuB' => $menuIds[1], 'qrId' => $qrId, 'token' => $token,
        ];
    }

    private function api(User $user)
    {
        return $this->actingAs($user)->withHeaders(['Accept' => 'application/json']);
    }

    // --- QR-RETARGET-TOKEN-STABLE-01 / QR-RETARGET-HISTORY-01 -------------

    public function test_the_printed_code_keeps_its_token_when_the_menu_changes(): void
    {
        $s = $this->scenario('qr-retarget');

        $response = $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/qr-codes/{$s['qrId']}/destination",
            ['menuId' => $s['menuB']]
        );

        $response->assertOk();
        $response->assertJsonPath('menuId', $s['menuB']);
        $response->assertJsonPath('token', $s['token']);

        self::assertSame(
            $s['token'],
            (string) DB::table('qr_codes')->where('id', $s['qrId'])->value('token'),
            'QR-RETARGET-TOKEN-STABLE-01: masadaki kâğıt aynı kâğıttır.'
        );

        // Geçmiş korunur: eski hedef satırı durur, yenisi eklenir.
        self::assertSame(2, DB::table('qr_destinations')->where('qr_code_id', $s['qrId'])->count());

        // "Şu an geçerli hedef" tek satırdır.
        self::assertSame(1, DB::table('qr_code_current_destinations')->where('qr_code_id', $s['qrId'])->count());

        $currentDestination = (int) DB::table('qr_code_current_destinations')
            ->where('qr_code_id', $s['qrId'])->value('qr_destination_id');

        self::assertSame(
            $s['menuB'],
            (int) DB::table('qr_destinations')->where('id', $currentDestination)->value('menu_id')
        );
    }

    // --- QR-RETARGET-TENANT-01 --------------------------------------------

    public function test_a_code_cannot_be_pointed_at_another_restaurants_menu(): void
    {
        $s = $this->scenario('qr-branch');
        $other = $this->scenario('qr-branch-other');

        // Başka bir RESTORANIN menüsü: varlığı bile sızmaz.
        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/qr-codes/{$s['qrId']}/destination",
            ['menuId' => $other['menuA']]
        )->assertNotFound();

        // Hedef DEĞİŞMEDİ.
        self::assertSame(1, DB::table('qr_destinations')->where('qr_code_id', $s['qrId'])->count());
    }

    public function test_moving_a_code_to_another_branch_moves_its_measurement_too(): void
    {
        // Şema şube başına TEK menü tutuyor; "aynı şubede başka bir menü"
        // diye bir şey yok. Hedef değiştirmenin tek gerçek anlamı, basılı
        // bir kodun BAŞKA BİR ŞUBENİN menüsünü göstermesi.
        $s = $this->scenario('qr-branch-move');

        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/qr-codes/{$s['qrId']}/destination",
            ['menuId' => $s['menuB']]
        )->assertOk();

        $newLocationId = (int) DB::table('menus')->where('id', $s['menuB'])->value('location_id');

        self::assertSame(
            $newLocationId,
            (int) DB::table('qr_codes')->where('id', $s['qrId'])->value('location_id'),
            'QR-RETARGET-LOCATION-FOLLOWS-01: ölçüm, kodun artık göstermediği şubeye yazılmamalı.'
        );
    }

    public function test_a_code_can_be_moved_by_naming_the_branch_instead_of_the_menu(): void
    {
        // Ekranın elinde şube listesi var, menü kimlikleri değil; her şube
        // için ayrı bir menü isteği atmak "kodu taşı" gibi tek bir işi N
        // isteğe çevirirdi (`docs/98` FF-64). Şube başına tek menü olduğu
        // için iki ad aynı hedefi gösterir.
        $s = $this->scenario('qr-branch-move-by-location');
        $locationB = (int) DB::table('menus')->where('id', $s['menuB'])->value('location_id');

        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/qr-codes/{$s['qrId']}/destination",
            ['locationId' => $locationB]
        )->assertOk();

        self::assertSame(
            $s['menuB'],
            (int) DB::table('qr_destinations')->where('qr_code_id', $s['qrId'])->orderByDesc('id')->value('menu_id'),
        );

        // İkisi de yoksa 422 — "hedefsiz taşıma" diye bir şey yok.
        $this->api($s['owner'])->putJson(
            "/api/workspaces/{$s['workspaceId']}/qr-codes/{$s['qrId']}/destination",
            []
        )->assertStatus(422);
    }

    // --- QR-ENABLE-01 / QR-DISABLED-DEAD-END-01 ---------------------------

    public function test_a_disabled_code_can_be_brought_back(): void
    {
        $s = $this->scenario('qr-enable');

        $this->api($s['owner'])->putJson("/api/workspaces/{$s['workspaceId']}/qr-codes/{$s['qrId']}/disable")
            ->assertOk();

        // Kapalıyken çıkmaz sokak davranışı korunur ve rota şekli ifşa
        // edilmez.
        $disabled = $this->get("/q/{$s['token']}");
        self::assertSame(404, $disabled->getStatusCode());
        self::assertStringNotContainsString('qr_destinations', $disabled->getContent());

        $this->api($s['owner'])->putJson("/api/workspaces/{$s['workspaceId']}/qr-codes/{$s['qrId']}/enable")
            ->assertOk()
            ->assertJsonPath('state', 'active');

        self::assertSame(
            'active',
            (string) DB::table('qr_codes')->where('id', $s['qrId'])->value('state'),
            'QR-ENABLE-01: yanlışlıkla kapatılan kod geri açılabilmeli.'
        );

        // Ve adres yeniden ÇALIŞIR: 404 değil.
        self::assertNotSame(404, $this->get("/q/{$s['token']}")->getStatusCode());
    }

    // --- QR-LIFECYCLE-AUTHZ-01 --------------------------------------------

    public function test_a_read_only_member_can_do_neither(): void
    {
        $s = $this->scenario('qr-authz');

        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $s['workspaceId'], 'user_id' => $member->id, 'role' => 'member',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api($member)->putJson("/api/workspaces/{$s['workspaceId']}/qr-codes/{$s['qrId']}/enable")
            ->assertStatus(403);

        $this->api($member)->putJson(
            "/api/workspaces/{$s['workspaceId']}/qr-codes/{$s['qrId']}/destination",
            ['menuId' => $s['menuB']]
        )->assertStatus(403);
    }
}
