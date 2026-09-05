<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PLANLA — ZAMANLANMIŞ YAYIN (kanonik kaynak
 * `docs/reference/panel-v3/panel.dc.html`, "Yayınlama" ekranındaki "Planla"
 * düğmesi; sahibin 2026-09-05 kararı: çizilecek VE çalışacak).
 *
 * NEDEN BU TESTLER ÖNCE YAZILDI: bugün depoda yayını ileri bir zamana kuran
 * hiçbir şey yok — ne tablo, ne rota, ne komut. Ekrana çalışmayan bir
 * "Planla" düğmesi koymak, restoran sahibinin gece 03:00'te yeni fiyatların
 * yayına gireceğini SANMASINA yol açardı; sabah misafir hâlâ eski fiyatı
 * okurken sahip bunu ancak kasada fark ederdi. Bu yüzden düğmeden önce
 * altyapı gelir.
 *
 * Sahibin dört kuralı, bire bir bu dosyada kanıtlanır:
 *   1. Zamanlanmış yayın da BİR YAYINDIR: yeni sürüm numarası alır ve
 *      QR/kalıcı adres aynı kalır.
 *   2. İPTAL EDİLEBİLİR.
 *   3. İKİ KEZ ÇALIŞMAZ (idempotent): komut iki kez koşarsa ikinci sürüm
 *      doğmaz.
 *   4. Zaman dilimi `Europe/Istanbul` ve hangi ana kurulduğu açıkça döner.
 */
final class ScheduledPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * @return array{0: int, 1: int, 2: int} [workspaceId, locationId, menuId]
     */
    private function workspaceWithReadyMenu(User $owner, string $slugSeed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
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
            'name' => 'Zeytin Restoranları',
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
            'display_name' => 'Kadıköy Şubesi',
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
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

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Kebaplar',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Adana Kebap',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 32000,
            'currency_code' => 'TRY',
            'position' => 0,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId, $menuId];
    }

    public function test_schedule_options_are_offered_in_the_istanbul_time_zone(): void
    {
        /*
            Saat seçenekleri SUNUCUDA üretilir. Tarayıcıda üretilseydi,
            Almanya'daki bir ortaktan panele giren kişi "bu gece 03:00"
            dediğinde Türkiye'de saat 04:00 olurdu; sahip menüsünün ne zaman
            değişeceğini bilemezdi.
        */
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-opt');

        $response = $this->actingAs($owner)->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('pending', null);
        $response->assertJsonPath('timeZone', 'Europe/Istanbul');

        $options = $response->json('options');
        self::assertIsArray($options);
        self::assertNotEmpty($options);

        foreach ($options as $option) {
            self::assertArrayHasKey('key', $option);
            self::assertArrayHasKey('scheduledFor', $option);
            self::assertTrue(
                Carbon::parse($option['scheduledFor'])->isFuture(),
                'Geçmişe kurulabilen bir seçenek, hiç çalışmayacak bir yayın demektir.'
            );
        }
    }

    public function test_scheduling_freezes_the_snapshot_and_reports_the_istanbul_moment(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-store');

        $scheduledFor = Carbon::now()->addHours(3);

        $response = $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => $scheduledFor->toIso8601String()]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('state', 'pending');
        $response->assertJsonPath('timeZone', 'Europe/Istanbul');

        // Henüz YAYIN YOK: planlamak yayınlamak değildir.
        $this->assertDatabaseCount('menu_publications', 0);
        self::assertSame(1, DB::table('menu_publication_schedules')->count());
    }

    public function test_scheduling_a_draft_that_is_not_ready_is_refused_at_scheduling_time(): void
    {
        /*
            Hazır olmayan taslak GECE 03:00'te değil, ŞİMDİ reddedilir.
            Aksi hâlde sahip düğmeye basar, "kuruldu" yazısını görür, uyur ve
            sabah menüsünün değişmediğini müşteriden öğrenirdi.
        */
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-unready');

        DB::table('menu_items')->update(['is_visible' => false]);

        $response = $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()]
        );

        $response->assertStatus(422);
        self::assertSame(0, DB::table('menu_publication_schedules')->count());
    }

    public function test_a_past_moment_is_refused(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-past');

        $response = $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->subMinute()->toIso8601String()]
        );

        $response->assertStatus(422);
    }

    public function test_a_pending_schedule_can_be_cancelled(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-cancel');

        $scheduleId = (int) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()]
        )->json('id');

        $this->actingAs($owner)->deleteJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule/{$scheduleId}"
        )->assertStatus(200);

        self::assertSame(
            'cancelled',
            (string) DB::table('menu_publication_schedules')->where('id', $scheduleId)->value('state')
        );

        // İptal edilmiş bir plan vakti gelince YAYINLANMAZ.
        Carbon::setTestNow(Carbon::now()->addHours(4));
        $this->artisan('zabuno:publish-scheduled-menus')->assertExitCode(0);
        Carbon::setTestNow();

        $this->assertDatabaseCount('menu_publications', 0);
    }

    public function test_the_due_command_publishes_a_new_version_and_never_publishes_it_twice(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-run');

        // Önce elle bir yayın: zamanlanmış yayının SÜRÜM ARTIRDIĞINI ancak
        // önceki bir sürümün varlığında kanıtlayabiliriz.
        $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications"
        )->assertStatus(201);

        $publicKeyBefore = (string) DB::table('menus')->where('id', $menuId)->value('public_key');

        $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()]
        )->assertStatus(201);

        Carbon::setTestNow(Carbon::now()->addHours(4));

        $this->artisan('zabuno:publish-scheduled-menus')->assertExitCode(0);
        // İKİNCİ KOŞU: dakikada bir çalışan bir zamanlayıcıda bu sıradan bir
        // olaydır ve ikinci bir sürüm doğurmamalıdır.
        $this->artisan('zabuno:publish-scheduled-menus')->assertExitCode(0);

        Carbon::setTestNow();

        $versions = DB::table('menu_publications')
            ->where('menu_id', $menuId)
            ->orderBy('version')
            ->pluck('version')
            ->all();

        self::assertSame([1, 2], $versions, 'Zamanlanmış yayın tek bir yeni sürüm doğurur.');

        // QR/kalıcı adres AYNI KALIR: basılı kart yayından etkilenmez.
        self::assertSame(
            $publicKeyBefore,
            (string) DB::table('menus')->where('id', $menuId)->value('public_key')
        );

        self::assertSame(
            'published',
            (string) DB::table('menu_publication_schedules')->where('menu_id', $menuId)->value('state')
        );
    }

    public function test_another_workspace_cannot_see_or_cancel_a_schedule(): void
    {
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-iso');

        $scheduleId = (int) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()]
        )->json('id');

        $this->actingAs($stranger)->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule"
        )->assertStatus(404);

        $this->actingAs($stranger)->deleteJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule/{$scheduleId}"
        )->assertStatus(404);
    }
}
