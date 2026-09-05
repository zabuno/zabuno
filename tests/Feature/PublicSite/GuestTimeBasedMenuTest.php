<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\MenuCatalog\Support\MultiMenuScaffold;
use Tests\TestCase;

/**
 * MİSAFİRİN YOLCULUĞU — aynı karekod, saate göre doğru menü.
 *
 * Sahibin kararı (`docs/109-PANEL-V3.md` §7.1): *"QR AYNI KALIR. Misafir
 * aynı kodu okutur; saate göre doğru menü açılır. Bu, 'basılı kod hiç
 * değişmez' kuralının doğal devamıdır."*
 *
 * Bu dosyanın işi tam olarak bunu KANITLAMAKTIR: masaya yapıştırılmış
 * karekodun jetonu ve şubenin genel adresi, ikinci bir menü doğduğunda ve
 * saat aralıkları değiştiğinde bile bir karakter bile kımıldamaz.
 *
 * Gereksinimler: `GUEST-TIME-SWITCH-01`, `QR-TOKEN-UNCHANGED-01`,
 * `PUBLIC-ADDRESS-UNCHANGED-01`, `GUEST-NEVER-BLANK-01`.
 */
final class GuestTimeBasedMenuTest extends TestCase
{
    use MultiMenuScaffold;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Kahvaltılı bir şube kurar ve yayınlar.
     *
     * @return array{0:User,1:int,2:int,3:int,4:int,5:string,6:string} [owner, workspaceId, locationId, mainId, breakfastId, token, publicKey]
     */
    private function restaurantWithTwoMenus(string $slugSeed): array
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, $slugSeed);

        $publicKey = $this->newPublicKey();
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $publicKey, 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);

        $this->fillMenu($workspaceId, $mainId, 'Kebaplar', 'Adana kebap', 42000);
        $this->fillMenu($workspaceId, $breakfastId, 'Kahvaltılıklar', 'Menemen', 18000);

        $token = Str::random(43);

        $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
            'workspace_id' => $workspaceId, 'location_id' => $locationId, 'token' => $token,
            'state' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $destinationId = (int) DB::table('qr_destinations')->insertGetId([
            'qr_code_id' => $qrCodeId, 'destination_type' => 'published_menu', 'menu_id' => $mainId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('qr_code_current_destinations')->insert([
            'qr_code_id' => $qrCodeId, 'qr_destination_id' => $destinationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // İKİ MENÜ AYRI AYRI YAYINLANIR. Yayın işaretçisi menü başına
        // tutulduğu için ikisi birbirinin sürümünü ezmez.
        foreach ([$mainId, $breakfastId] as $menuId) {
            $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
                ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")
                ->assertStatus(201);
        }

        $schedule = app(MenuSchedulePort::class);
        $schedule->setServiceWindow($workspaceId, $mainId, 0, 0);
        $schedule->setServiceWindow($workspaceId, $breakfastId, 7 * 60, 11 * 60);

        return [$owner, $workspaceId, $locationId, $mainId, $breakfastId, $token, $publicKey];
    }

    private function nowAt(string $clock): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 '.$clock, 'Europe/Istanbul'));
    }

    // --- GUEST-TIME-SWITCH-01 ----------------------------------------------

    public function test_the_same_printed_code_opens_breakfast_at_eight_and_kebabs_at_eight_in_the_evening(): void
    {
        [, , , , , $token] = $this->restaurantWithTwoMenus('guest-switch');

        $this->nowAt('08:00');
        $morning = (string) $this->get("/menu/{$token}")->getContent();

        self::assertStringContainsString('Menemen', $morning, 'GUEST-TIME-SWITCH-01: 08:00\'de kahvaltı menüsü açılmalı.');
        self::assertStringNotContainsString('Adana kebap', $morning);

        $this->nowAt('20:00');
        $evening = (string) $this->get("/menu/{$token}")->getContent();

        self::assertStringContainsString('Adana kebap', $evening, 'GUEST-TIME-SWITCH-01: akşam ana menü açılmalı.');
        self::assertStringNotContainsString('Menemen', $evening);
    }

    // --- GUEST-TIME-SWITCH-01 (kalıcı adres de saate bakar) ----------------

    public function test_the_permanent_public_address_switches_with_the_clock_too(): void
    {
        [, , , , , , $publicKey] = $this->restaurantWithTwoMenus('guest-switch-key');

        $this->nowAt('08:00');
        $morning = (string) $this->followingRedirects()->get("/menu/{$publicKey}")->getContent();
        self::assertStringContainsString('Menemen', $morning);

        $this->nowAt('20:00');
        $evening = (string) $this->followingRedirects()->get("/menu/{$publicKey}")->getContent();
        self::assertStringContainsString('Adana kebap', $evening);
    }

    // --- QR-TOKEN-UNCHANGED-01 / PUBLIC-ADDRESS-UNCHANGED-01 ---------------

    public function test_adding_a_second_menu_never_moves_the_printed_code_or_the_public_address(): void
    {
        [$owner, $workspaceId, $locationId, $mainId, , $token, $publicKey] = $this->restaurantWithTwoMenus('guest-unchanged');

        $destinationsBefore = DB::table('qr_destinations')->orderBy('id')->get()->toArray();

        // Üçüncü bir menü doğuyor ve saat alıyor.
        $lateNightId = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu", ['name' => 'Gece'])
            ->assertStatus(201)
            ->json('id');

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->putJson(
            "/api/workspaces/{$workspaceId}/menu/{$lateNightId}/service-window",
            ['startsAt' => '22:00', 'endsAt' => '02:00'],
        )->assertOk();

        self::assertSame(
            $token,
            (string) DB::table('qr_codes')->where('location_id', $locationId)->value('token'),
            'QR-TOKEN-UNCHANGED-01: basılı kodun jetonu asla değişmez.'
        );
        self::assertSame(
            $publicKey,
            (string) DB::table('menus')->where('id', $mainId)->value('public_key'),
            'PUBLIC-ADDRESS-UNCHANGED-01: şubenin genel adresi asla değişmez.'
        );
        self::assertNull(
            DB::table('menus')->where('id', $lateNightId)->value('public_key'),
            'PUBLIC-ADDRESS-UNCHANGED-01: yeni menü İKİNCİ bir genel adres açamaz.'
        );
        self::assertEquals(
            $destinationsBefore,
            DB::table('qr_destinations')->orderBy('id')->get()->toArray(),
            'QR-TOKEN-UNCHANGED-01: karekod hedef kayıtları da kımıldamamalı.'
        );
    }

    // --- GUEST-NEVER-BLANK-01 ----------------------------------------------

    public function test_a_location_whose_menus_were_never_scheduled_still_answers(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'guest-unscheduled');

        $publicKey = $this->newPublicKey();
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $publicKey, 0);
        $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);
        $this->fillMenu($workspaceId, $mainId, 'Kebaplar', 'Adana kebap', 42000);

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$mainId}/publications")
            ->assertStatus(201);

        $this->nowAt('08:00');

        $html = (string) $this->followingRedirects()->get("/menu/{$publicKey}")->getContent();

        self::assertStringContainsString(
            'Adana kebap',
            $html,
            'GUEST-NEVER-BLANK-01: hiç saat verilmemişse gün ÇIPA menüsüne aittir — misafir boş sayfa görmez.'
        );
    }
}
