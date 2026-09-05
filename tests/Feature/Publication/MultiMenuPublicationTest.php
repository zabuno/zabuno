<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\MenuCatalog\Support\MultiMenuScaffold;
use Tests\TestCase;

/**
 * YAYINLAMA MENÜ BAŞINA ÇALIŞIR — çoklu menü, FF-137'nin zamanlanmış
 * yayınını ve imzalı önizlemesini BOZMADAN.
 *
 * Sahibin kararı (`docs/109` §7.1): *"Yayınlama ve QR çözümü menü başına
 * çalışmak zorunda."* Bu dosya, çoklu menü açıldıktan sonra da:
 *
 * - her menünün KENDİ sürüm sayacı ve KENDİ canlı işaretçisi olduğunu,
 * - bir menüyü yayınlamanın ötekinin canlı sürümünü kımıldatmadığını,
 * - "Planla" (zamanlanmış yayın) ve "Telefonda önizle" (imzalı adres)
 *   yollarının menü başına çalıştığını
 *
 * kanıtlar. Gereksinimler: `PUB-PER-MENU-01`, `PUB-PER-MENU-ISOLATED-01`,
 * `PUB-PER-MENU-SCHEDULE-01`, `PUB-PER-MENU-PREVIEW-01`.
 */
final class MultiMenuPublicationTest extends TestCase
{
    use MultiMenuScaffold;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array{0:User,1:int,2:int,3:int} [owner, workspaceId, mainId, breakfastId] */
    private function twoMenus(string $slugSeed): array
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, $slugSeed);

        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);

        $this->fillMenu($workspaceId, $mainId, 'Kebaplar', 'Adana kebap', 42000);
        $this->fillMenu($workspaceId, $breakfastId, 'Kahvaltılıklar', 'Menemen', 18000);

        return [$owner, $workspaceId, $mainId, $breakfastId];
    }

    /** @return TestResponse */
    private function publish(User $owner, int $workspaceId, int $menuId)
    {
        return $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications");
    }

    // --- PUB-PER-MENU-01 ----------------------------------------------------

    public function test_each_menu_of_a_location_keeps_its_own_version_counter_and_live_pointer(): void
    {
        [$owner, $workspaceId, $mainId, $breakfastId] = $this->twoMenus('pub-per-menu');

        $this->publish($owner, $workspaceId, $mainId)->assertStatus(201)->assertJsonPath('version', 1);
        $this->publish($owner, $workspaceId, $mainId)->assertStatus(201)->assertJsonPath('version', 2);

        $breakfastFirst = $this->publish($owner, $workspaceId, $breakfastId);
        $breakfastFirst->assertStatus(201);
        $breakfastFirst->assertJsonPath(
            'version',
            1,
            'PUB-PER-MENU-01: kahvaltı menüsünün sürüm sayacı ana menüninkinden AYRIDIR.'
        );

        self::assertSame(2, DB::table('menu_publication_current_pointers')->count());
        self::assertSame(1, DB::table('menu_publication_current_pointers')->where('menu_id', $mainId)->count());
        self::assertSame(1, DB::table('menu_publication_current_pointers')->where('menu_id', $breakfastId)->count());
    }

    // --- PUB-PER-MENU-ISOLATED-01 ------------------------------------------

    public function test_publishing_one_menu_does_not_move_the_other_ones_live_version(): void
    {
        [$owner, $workspaceId, $mainId, $breakfastId] = $this->twoMenus('pub-isolated');

        $mainPublication = $this->publish($owner, $workspaceId, $mainId)->json('id');
        $this->publish($owner, $workspaceId, $breakfastId);
        $this->publish($owner, $workspaceId, $breakfastId);

        self::assertSame(
            $mainPublication,
            (int) DB::table('menu_publication_current_pointers')->where('menu_id', $mainId)->value('current_publication_id'),
            'PUB-PER-MENU-ISOLATED-01: kahvaltıyı yayınlamak ana menünün canlı sürümünü değiştirmemeli.'
        );
        self::assertSame(
            'published',
            (string) DB::table('menu_publications')->where('id', $mainPublication)->value('state'),
            'Ana menünün canlı sürümü "superseded" olmamalı: sayaç menü başınadır.'
        );
    }

    // --- PUB-PER-MENU-SCHEDULE-01 ------------------------------------------

    public function test_scheduled_publishing_still_works_and_stays_on_its_own_menu(): void
    {
        [$owner, $workspaceId, $mainId, $breakfastId] = $this->twoMenus('pub-schedule');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$breakfastId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()],
        );

        $response->assertStatus(201);

        self::assertSame(
            1,
            DB::table('menu_publication_schedules')->where('menu_id', $breakfastId)->count(),
            'PUB-PER-MENU-SCHEDULE-01: plan yalnız kendi menüsüne yazılmalı.'
        );
        self::assertSame(0, DB::table('menu_publication_schedules')->where('menu_id', $mainId)->count());
    }

    // --- PUB-PER-MENU-PREVIEW-01 -------------------------------------------

    public function test_the_signed_phone_preview_opens_the_menu_it_was_signed_for(): void
    {
        [$owner, $workspaceId, , $breakfastId] = $this->twoMenus('pub-preview');

        $url = (string) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$breakfastId}/draft-preview-link"
        )->json('url');

        self::assertNotSame('', $url);

        $page = $this->get($url);

        $page->assertOk();
        self::assertStringContainsString(
            'Menemen',
            (string) $page->getContent(),
            'PUB-PER-MENU-PREVIEW-01: önizleme, imzalandığı MENÜNÜN taslağını göstermeli.'
        );
    }
}
