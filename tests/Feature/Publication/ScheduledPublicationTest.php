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
 *   4. Zaman dilimi ŞUBENİNDİR (`locations.timezone`, `docs/62`) ve hangi
 *      ana kurulduğu açıkça döner. Sabit bir dilim, aynı markanın Berlin
 *      şubesi açılır açılmaz sessizce yanlış olurdu.
 */
final class ScheduledPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * ŞUBENİN SAAT DİLİMİ PARAMETREDİR, sabit değil (`docs/62`). Aynı
     * markanın İstanbul ve Berlin şubesi olabilir; testin bunu kuramaması,
     * ürünün de kuramamasına göz yummak olurdu.
     *
     * @return array{0: int, 1: int, 2: int} [workspaceId, locationId, menuId]
     */
    private function workspaceWithReadyMenu(
        User $owner,
        string $slugSeed,
        string $locationTimezone = 'Europe/Istanbul',
    ): array {
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
            'timezone' => $locationTimezone,
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

    public function test_schedule_options_are_offered_in_the_branch_time_zone(): void
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
        $response->assertJsonPath('plan', null);
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

    // --- SCHEDULE-TZ-BELONGS-TO-BRANCH-01 ---------------------------------

    /**
     * BERLİN ŞUBESİ "BU GECE 03:00" DEDİĞİNDE MENÜ BERLİN'DE 03:00'TE
     * DEĞİŞİR (`docs/62`).
     *
     * MÜŞTERİ SORUNU. Saat dilimi markanın değil ŞUBENİN alanıdır: aynı
     * markanın İstanbul, Dubai ve Berlin şubesi olabilir. Seçenekler sabit
     * `Europe/Istanbul` ile üretildiği sürece Berlin şubesinin "gece 03:00"
     * düğmesi kışın Berlin'de 01:00'i kurar — servis kapanmadan, hâlâ
     * masada oturan misafirin menüsü elinde değişir. Hatanın en kötü yanı
     * görünmezliğidir: tek şubeli bir işletmede sabit dilim doğru görünmeye
     * devam eder ve yanlışlığı ancak ikinci şube açılınca ortaya çıkar.
     */
    public function test_a_berlin_branch_schedules_in_berlin_time_not_istanbul_time(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu(
            $owner,
            'zeytin-sched-berlin',
            'Europe/Berlin',
        );

        $response = $this->actingAs($owner)->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('timeZone', 'Europe/Berlin');

        $options = collect($response->json('options'))->keyBy('key');

        self::assertSame(
            '03:00',
            Carbon::parse($options['tonight']['scheduledFor'])->setTimezone('Europe/Berlin')->format('H:i'),
            'SCHEDULE-TZ-BELONGS-TO-BRANCH-01: "bu gece 03:00" Berlin şubesinde Berlin saatiyle 03:00 olmalı.'
        );
        self::assertSame(
            '09:00',
            Carbon::parse($options['tomorrowMorning']['scheduledFor'])->setTimezone('Europe/Berlin')->format('H:i'),
            'Kapılar açılmadan önceki saat de şubenin kendi sabahıdır.'
        );
        self::assertTrue(
            Carbon::parse($options['nextMonday']['scheduledFor'])->setTimezone('Europe/Berlin')->isMonday(),
            '"Gelecek Pazartesi" şubenin takviminde Pazartesi olmalı.'
        );
    }

    /**
     * ŞUBENİN SAATİ OKUNAMIYORSA SAAT SEÇTİRİLMEZ.
     *
     * Sessizce İstanbul'a (ya da sunucunun saatine) düşmek, düzeltilen
     * hatayı yeni bir yerde tekrar etmek olurdu: sahip ekranda "03:00"
     * okur, menü başka bir 03:00'te değişirdi. Boş liste dürüsttür ve
     * "hemen yayınla" yolunu kapatmaz.
     *
     * İki ayrı bozulma da aynı cevabı verir: saat dilimi hiç yazılmamış bir
     * şube, ve tanınmayan bir kimlik taşıyan şube (elle düzeltilmiş bir
     * satır, emekliye ayrılmış bir dilim).
     */
    public function test_a_branch_without_a_readable_time_zone_is_offered_no_hours(): void
    {
        $owner = $this->verifiedUser();

        foreach (['' => 'zeytin-sched-tz-bos', 'Mars/Olympus' => 'zeytin-sched-tz-bozuk'] as $timezone => $slug) {
            [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, $slug, (string) $timezone);

            $response = $this->actingAs($owner)->getJson(
                "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule"
            );

            $response->assertStatus(200);
            self::assertSame(
                [],
                $response->json('options'),
                "Saat dilimi [{$timezone}] okunamıyorken seçenek çizmek, tutulamayacak bir söz vermektir."
            );
            self::assertNull(
                $response->json('timeZone'),
                "Ekrana [{$timezone}] gönderilirse tarayıcı hiçbir anı biçimlendiremez; sahip planını göremez."
            );
        }
    }

    /**
     * SAKLAMA UTC KALIR. Şubenin saati YALNIZ seçeneklerin üretildiği ve
     * gösterildiği yerde konuşur; kaydedilen an mutlak bir andır.
     *
     * Yerel saat saklansaydı, yaz saati uygulaması biten bir gecede aynı
     * duvar saati iki kez yaşanır ve yayın hangi geçişte çıkacağını kimse
     * söyleyemezdi.
     */
    public function test_the_stored_moment_stays_utc_even_for_a_branch_abroad(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu(
            $owner,
            'zeytin-sched-berlin-utc',
            'Europe/Berlin',
        );

        $chosen = Carbon::parse(
            (string) $this->actingAs($owner)
                ->getJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule")
                ->json('options.0.scheduledFor')
        );

        $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => $chosen->toIso8601String()]
        )->assertStatus(201);

        $stored = (string) DB::table('menu_publication_schedules')->value('scheduled_for');

        self::assertTrue(
            Carbon::parse($stored, 'UTC')->equalTo($chosen),
            'Ekranda seçilen AN ile saklanan an aynı olmalı; dilim çevirisi yalnız gösterimdedir.'
        );
    }

    public function test_scheduling_freezes_the_snapshot_and_reports_the_branch_moment(): void
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

    /**
     * VAKTİ GEÇTİ AMA YAYIN ÇIKMADI — planın sessizce ölmesi.
     *
     * Zamanlayıcı ölürse (kap yeniden başlatılırken, ya da `schedule:work`
     * süreci olmayan bir barındırmada) kayıt `pending` kalır ve saat geçer.
     * O ana kadar ekran "yarın 09:00 için zamanlandı" yazıyordu; o an
     * geçtikten sonra AYNI CÜMLEYİ yazmaya devam etmesi düpedüz yalandır.
     * Sahip menüsünün değiştiğini sanır, misafir eski fiyatı okur ve sahip
     * bunu ancak kasada fark eder.
     */
    public function test_a_plan_whose_moment_passed_without_publishing_is_reported_as_overdue(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-overdue');

        $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()]
        )->assertStatus(201);

        // Komut KOŞMUYOR: zamanlayıcının olmadığı gerçek dünya.
        Carbon::setTestNow(Carbon::now()->addHours(4));

        $response = $this->actingAs($owner)->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule"
        );

        Carbon::setTestNow();

        $response->assertStatus(200);
        $response->assertJsonPath('plan.status', 'overdue');

        // Yayın gerçekten çıkmadı: misafir hâlâ önceki sürümü görüyor.
        $this->assertDatabaseCount('menu_publications', 0);
    }

    /**
     * Vakti YENİ geçmiş bir plan telaş sebebi değildir.
     *
     * Zamanlayıcı dakikada bir çalışır. 09:00 planını 09:00:30'da "çıkmadı"
     * diye işaretlemek, her plan için bir yalancı alarm demekti — ve yalancı
     * alarm, gerçek alarmı görünmez yapar.
     */
    public function test_a_plan_within_the_scheduler_grace_window_is_still_reported_as_scheduled(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-grace');

        $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()]
        )->assertStatus(201);

        Carbon::setTestNow(Carbon::now()->addHours(3)->addMinute());

        $response = $this->actingAs($owner)->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule"
        );

        Carbon::setTestNow();

        $response->assertJsonPath('plan.status', 'scheduled');
    }

    /**
     * YAYIN BAŞLADI AMA BİTMEDİ — kayıt `publishing` hâlinde asılı kalır.
     *
     * Komut kaydı `claim()` ile sahiplenir; süreç o an ölürse (dağıtım,
     * OOM, kap yeniden başlatma) kayıt ne `published` ne `failed` olur.
     * Eski davranışta bu kayıt sahibin ekranından TAMAMEN kayboluyordu:
     * plan yok, yayın yok, açıklama yok.
     */
    public function test_a_plan_stuck_mid_publish_is_reported_as_interrupted(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-stuck');

        $scheduleId = (int) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()]
        )->json('id');

        DB::table('menu_publication_schedules')->where('id', $scheduleId)->update([
            'state' => 'publishing',
            'updated_at' => Carbon::now()->subHour(),
        ]);

        $this->actingAs($owner)->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule"
        )->assertJsonPath('plan.status', 'interrupted');
    }

    /**
     * BAŞARISIZ YAYIN SAHİBİN EKRANINDA KALIR.
     *
     * `markFailed` kaydı `failed` yapar ve komut bir daha denemez — bu
     * doğrudur. Ama eski okuma yalnız `pending` kayıtları görüyordu: sahip
     * sabah paneli açtığında hiçbir plan göremez, menüsünün neden
     * değişmediğini de öğrenemezdi.
     */
    public function test_a_failed_plan_stays_visible_until_the_owner_sees_it(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-failed');

        $scheduleId = (int) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()]
        )->json('id');

        DB::table('menu_publication_schedules')->where('id', $scheduleId)->update(['state' => 'failed']);

        $this->actingAs($owner)->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule"
        )->assertJsonPath('plan.status', 'failed');
    }

    /**
     * Sahip uyarıyı KAPATABİLİR ama "o gece ne oldu" cevabı SİLİNMEZ.
     *
     * Kapatılamayan bir uyarı, birkaç gün sonra okunmayan bir süse döner —
     * ve okunmayan uyarı, olmayan uyarıdır. Kaydın `failed` hâli yerinde
     * durur; yalnız görünürlükten düşer.
     */
    public function test_a_failed_plan_can_be_dismissed_without_erasing_what_happened(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , $menuId] = $this->workspaceWithReadyMenu($owner, 'zeytin-sched-ack');

        $scheduleId = (int) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule",
            ['scheduledFor' => Carbon::now()->addHours(3)->toIso8601String()]
        )->json('id');

        DB::table('menu_publication_schedules')->where('id', $scheduleId)->update(['state' => 'failed']);

        $this->actingAs($owner)->deleteJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule/{$scheduleId}"
        )->assertStatus(200);

        $row = DB::table('menu_publication_schedules')->where('id', $scheduleId)->first();

        self::assertNotNull($row);
        self::assertSame('failed', (string) $row->state, 'Başarısızlık kaydı iptale çevrilmez.');
        self::assertNotNull($row->acknowledged_at);

        $this->actingAs($owner)->getJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/schedule"
        )->assertJsonPath('plan', null);
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
