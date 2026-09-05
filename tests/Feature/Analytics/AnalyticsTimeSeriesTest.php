<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * ZAMAN SERİSİ — `docs/109` §1 (Insights) ve §6.5.
 *
 * NEDEN BU TEST VAR
 * =================
 *
 * Depodaki analitik bugün yalnız ARALIK TOPLAMI üretiyor: "son 7 günde 214
 * tarama". Bu sayı bir restoran sahibinin sorduğu hiçbir soruyu
 * cevaplamıyor. Sahibin gerçek yolculuğu şu:
 *
 *   Cumartesi akşamı kasanın başında telefonu açıyor. Bilmek istediği şey
 *   "214" değil; "bu hafta geçen haftadan iyi mi?", "hangi gün çöktü?",
 *   "öğle mi akşam mı yoğun?", "Kadıköy mü Beşiktaş mı çekiyor?". Tek bir
 *   toplam, bu dört sorunun DÖRDÜNÜ birden gizler — bir haftanın tamamı
 *   düz bir sayıya ezildiğinde, salı günü hiç tarama olmadığı görünmez.
 *
 * Kaynak (`docs/reference/panel-v3/panel.dc.html`, `data-screen-label=
 * "Insights"`) tam olarak bu dört şeyi çiziyor: çubuk+çizgi grafik, saat ısı
 * haritası, şube halkası ve bir önceki dönemle karşılaştırma. Bunların
 * HİÇBİRİ bugün üretilemiyor — ekranı çizmek için gereken veri yok.
 *
 * Bu test o veriyi bir SÖZLEŞME hâline getirir. Var olan `summary` ve
 * `menu-engineering` uçlarının davranışına dokunulmaz; bu yeni bir uçtur.
 *
 * GİZLİLİK
 * ========
 *
 * `ShowMenuEngineeringController` bir eşik kuralı taşıyor: yeterli veri
 * yoksa sayı GÖSTERİLMEZ. Aynı kural burada iki kere geçerlidir ve ikinci
 * uygulaması yeni:
 *
 *   1. Pencere eşiği — pencerede yeterince farklı ziyaretçi yoksa seri hiç
 *      yayımlanmaz. Yeni açılmış bir restoranda üç kişilik bir günün saat
 *      kırılımı, o üç kişinin ne zaman geldiğini gösterirdi.
 *   2. HÜCRE eşiği — ısı haritasının tek bir ziyaretçiye dayanan hücresi
 *      hiç yayımlanmaz. "Salı 03:00 · 1 tarama" bir sayı değil, BİR KİŞİNİN
 *      o gece oraya girdiğinin kaydıdır. Kovaya inince gizlilik sorusu
 *      toplamdakinden farklıdır: toplam kalabalığı gizler, kova gizlemez.
 *
 * GÜN SINIRI
 * ==========
 *
 * Kovalar ŞUBENİN saatiyle çizilir, sunucunun saatiyle değil. İstanbul'da
 * 00:30'da okutulan bir karekod, sunucu UTC'de olduğu için bir önceki günün
 * kovasına düşerse sahibin "cumartesi gecesi" dediği şey pazartesi
 * raporunda görünür ve grafiğin tamamı bir gün kayar.
 *
 * Requirement ID'leri: ANALYTICS-TIMESERIES-DAILY-01,
 * ANALYTICS-TIMESERIES-TZ-02, ANALYTICS-TIMESERIES-COMPARISON-03,
 * ANALYTICS-TIMESERIES-HOURLY-04, ANALYTICS-TIMESERIES-CELL-PRIVACY-05,
 * ANALYTICS-TIMESERIES-LOCATION-SHARE-06,
 * ANALYTICS-TIMESERIES-THRESHOLD-07, ANALYTICS-TIMESERIES-SCOPE-404-08,
 * ANALYTICS-TIMESERIES-RANGE-422-09, ANALYTICS-TIMESERIES-PLAN-402-10,
 * ANALYTICS-TIMESERIES-SUMMARY-UNCHANGED-11.
 */
final class AnalyticsTimeSeriesTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /**
     * Sabit "şimdi": 2026-09-05 09:00 UTC = İstanbul'da CUMARTESİ 12:00.
     *
     * Saat farkı bilerek sıfır değil. UTC'de yazılmış bir testin geçmesi,
     * gün sınırının doğru çizildiğini KANITLAMAZ — yalnız iki saatin aynı
     * olduğu bir günde hata yapmadığını gösterir.
     */
    private const NOW = '2026-09-05T09:00:00+00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * Bir çalışma alanı + marka + ilk şube + menü.
     *
     * @return array{workspaceId:int, brandId:int, locationId:int, menuId:int}
     */
    private function workspace(User $owner, string $seed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$seed,
            'slug' => $seed,
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
            'name' => 'Marka '.$seed,
            'slug' => $seed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = $this->location($workspaceId, $brandId, 'Kadıköy');

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->grantEntitlements($workspaceId);

        return compact('workspaceId', 'brandId', 'locationId', 'menuId');
    }

    private function location(int $workspaceId, int $brandId, string $name): int
    {
        return (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => $name,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => $name.' 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Ham olay satırı — uç, ölçümün KENDİSİNDEN okumalı, bir özet tablodan değil. */
    private function event(
        int $workspaceId,
        int $locationId,
        int $menuId,
        string $type,
        string $occurredAtUtc,
        ?string $visitorKey,
    ): void {
        $at = Carbon::parse($occurredAtUtc);

        DB::table('analytics_events')->insert([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'qr_code_id' => null,
            'menu_id' => $menuId,
            'event_type' => $type,
            'visitor_key' => $visitorKey,
            'occurred_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function url(int $workspaceId, ?int $locationId, string $range): string
    {
        return $locationId === null
            ? "/api/workspaces/{$workspaceId}/analytics/time-series?range={$range}"
            : "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/analytics/time-series?range={$range}";
    }

    /**
     * Eşiği aşan, gün ve saat kırılımı BİLİNEN bir pencere kurar.
     *
     * 2026-09-01 (SALI) 10:00 UTC = İstanbul 13:00 → altı farklı ziyaretçi.
     * Bu hem gün kovasını hem de saat hücresini eşiğin üstüne çıkarır.
     *
     * @param  array{workspaceId:int, locationId:int, menuId:int}  $ctx
     */
    private function seedBusyTuesday(array $ctx): void
    {
        foreach (range(1, 6) as $index) {
            $this->event(
                $ctx['workspaceId'],
                $ctx['locationId'],
                $ctx['menuId'],
                'qr_resolve',
                '2026-09-01T10:00:00+00:00',
                'visitor-'.$index,
            );
        }

        // Menü açılışı ayrı bir seridir: kaynağın grafiğinde çubuk TARAMA,
        // çizgi MENÜ AÇILIŞIDIR. İkisi aynı kovada ama ayrı sayılır.
        foreach (range(1, 4) as $index) {
            $this->event(
                $ctx['workspaceId'],
                $ctx['locationId'],
                $ctx['menuId'],
                'menu_open',
                '2026-09-01T10:05:00+00:00',
                'visitor-'.$index,
            );
        }
    }

    // --- ANALYTICS-TIMESERIES-DAILY-01 -------------------------------------

    public function test_daily_buckets_cover_every_day_in_the_window_including_empty_ones(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-gunluk');
        $this->seedBusyTuesday($ctx);

        $body = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertOk()
            ->json();

        self::assertSame('ready', $body['state']);

        $dates = array_column($body['buckets'], 'date');

        /*
            BOŞ GÜN DE BİR KOVADIR.

            Yalnız olayı olan günleri döndüren bir seri, salı ile perşembeyi
            yan yana çizer ve aradaki çarşamba yokmuş gibi görünür. Grafiğin
            söylediği şey "çarşamba düştü" değil, "çarşamba hiç olmadı"
            olurdu — sahip de düşüşü hiç görmezdi.
        */
        self::assertSame([
            '2026-08-29',
            '2026-08-30',
            '2026-08-31',
            '2026-09-01',
            '2026-09-02',
            '2026-09-03',
            '2026-09-04',
            '2026-09-05',
        ], $dates, 'ANALYTICS-TIMESERIES-DAILY-01: pencerenin her günü kovalanmalı.');

        $byDate = array_column($body['buckets'], null, 'date');

        self::assertSame(6, $byDate['2026-09-01']['qrResolveCount']);
        self::assertSame(4, $byDate['2026-09-01']['menuOpenCount']);
        self::assertSame(0, $byDate['2026-08-30']['qrResolveCount']);
        self::assertSame(0, $byDate['2026-08-30']['menuOpenCount']);
    }

    public function test_bucket_totals_reconcile_with_the_existing_summary_endpoint(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-mutabakat');
        $this->seedBusyTuesday($ctx);

        $summary = $this->actingAs($owner)
            ->getJson("/api/workspaces/{$ctx['workspaceId']}/brand/locations/{$ctx['locationId']}/analytics/summary?range=7d")
            ->assertOk()
            ->json();

        $series = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertOk()
            ->json();

        /*
            İKİ UÇ AYNI PENCEREDEN KONUŞUR.

            Ayrı bir aralık hesabı yazmak, ekranda "toplam 214, günlerin
            toplamı 209" gibi görünürdü — ve bir kez yanlış çıkan rapor bir
            daha okunmaz.
        */
        self::assertSame(
            $summary['qrResolveCount'],
            array_sum(array_column($series['buckets'], 'qrResolveCount')),
            'ANALYTICS-TIMESERIES-DAILY-01: kovaların toplamı özetle uyuşmalı.',
        );

        self::assertSame(
            $summary['menuOpenCount'],
            array_sum(array_column($series['buckets'], 'menuOpenCount')),
        );
    }

    // --- ANALYTICS-TIMESERIES-TZ-02 ----------------------------------------

    public function test_day_boundary_follows_the_location_timezone_not_the_server(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-saat-dilimi');
        $this->seedBusyTuesday($ctx);

        /*
            2026-09-02 21:30 UTC = İstanbul'da 2026-09-03 00:30.

            Sunucunun günüyle çizilseydi bu tarama 2 Eylül'e düşerdi. Sahip
            "çarşamba gecesi" diyor, rapor "salı" diyor — ve iki taraf aynı
            grafiğe bakarken farklı bir gün konuşuyor.
        */
        $this->event(
            $ctx['workspaceId'],
            $ctx['locationId'],
            $ctx['menuId'],
            'qr_resolve',
            '2026-09-02T21:30:00+00:00',
            'visitor-1',
        );

        $body = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertOk()
            ->json();

        self::assertSame('Europe/Istanbul', $body['timezone']);

        $byDate = array_column($body['buckets'], null, 'date');

        self::assertSame(
            1,
            $byDate['2026-09-03']['qrResolveCount'],
            'ANALYTICS-TIMESERIES-TZ-02: gece yarısını geçen tarama ŞUBENİN gününe düşmeli.',
        );
        self::assertSame(0, $byDate['2026-09-02']['qrResolveCount']);
    }

    // --- ANALYTICS-TIMESERIES-COMPARISON-03 --------------------------------

    public function test_comparison_reports_the_previous_window_of_equal_length(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-karsilastirma');
        $this->seedBusyTuesday($ctx);

        // Önceki 7 gün: [şimdi-14g, şimdi-7g). Üç tarama.
        foreach (range(1, 3) as $index) {
            $this->event(
                $ctx['workspaceId'],
                $ctx['locationId'],
                $ctx['menuId'],
                'qr_resolve',
                '2026-08-25T10:00:00+00:00',
                'onceki-'.$index,
            );
        }

        $body = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertOk()
            ->json();

        self::assertSame('previous_period', $body['comparison']['basis']);
        self::assertSame(6, $body['comparison']['currentQrResolveCount']);
        self::assertSame(3, $body['comparison']['previousQrResolveCount']);
        // JSON, tam sayıya oturan bir ondalığı `1` olarak taşır; sözleşme
        // DEĞER üzerinedir, taşıma biçimi üzerine değil.
        self::assertEqualsWithDelta(1.0, $body['comparison']['deltaRatio'], 0.0001);
    }

    public function test_comparison_has_no_ratio_when_the_previous_window_is_empty(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-sifirdan');
        $this->seedBusyTuesday($ctx);

        $body = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertOk()
            ->json();

        /*
            SIFIRDAN YÜZDE ARTIŞ YOKTUR.

            `%∞ arttı` ya da `%100 arttı` demek matematiksel olarak
            uydurmadır: bölen sıfırdır. `openRate`'in aynı disiplini burada
            da geçerli — hesaplanamayan oran `null`'dur, sıfır değil.
        */
        self::assertSame(0, $body['comparison']['previousQrResolveCount']);
        self::assertNull(
            $body['comparison']['deltaRatio'],
            'ANALYTICS-TIMESERIES-COMPARISON-03: bölen sıfırken oran uydurulmaz.',
        );
    }

    public function test_today_compares_against_the_same_weekday_up_to_the_same_clock_time(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-bugun');

        // BUGÜN (cumartesi, İstanbul 12:00'a kadar): beş farklı ziyaretçi.
        foreach (range(1, 5) as $index) {
            $this->event(
                $ctx['workspaceId'],
                $ctx['locationId'],
                $ctx['menuId'],
                'qr_resolve',
                '2026-09-05T07:00:00+00:00',
                'bugun-'.$index,
            );
        }

        // GEÇEN CUMARTESİ, İstanbul 09:00 — kıyas penceresinin İÇİNDE.
        $this->event($ctx['workspaceId'], $ctx['locationId'], $ctx['menuId'], 'qr_resolve', '2026-08-29T06:00:00+00:00', 'gecen-1');

        /*
            GEÇEN CUMARTESİ, İstanbul 16:00 — kıyas penceresinin DIŞINDA.

            Saat 12:00'da "bugün geçen haftadan %40 kötü" diyen bir rapor,
            aslında yarım günü tam günle karşılaştırıyordur. Sahip öğle
            arasında paniğe kapılır ve akşam veriler eşitlenir. Karşılaştırma
            AYNI SAATE kadar yapılır.
        */
        $this->event($ctx['workspaceId'], $ctx['locationId'], $ctx['menuId'], 'qr_resolve', '2026-08-29T13:00:00+00:00', 'gecen-2');

        $body = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], 'today'))
            ->assertOk()
            ->json();

        self::assertSame('same_weekday_last_week', $body['comparison']['basis']);
        self::assertSame(5, $body['comparison']['currentQrResolveCount']);
        self::assertSame(
            1,
            $body['comparison']['previousQrResolveCount'],
            'ANALYTICS-TIMESERIES-COMPARISON-03: kıyas aynı saate kadar olmalı.',
        );
    }

    // --- ANALYTICS-TIMESERIES-HOURLY-04 & CELL-PRIVACY-05 ------------------

    public function test_hourly_heatmap_reports_weekday_and_local_hour(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-isi');
        $this->seedBusyTuesday($ctx);

        $body = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertOk()
            ->json();

        /*
            2026-09-01 SALI, İstanbul 13:00. ISO-8601 hafta günü: pazartesi 1.

            Saat YEREL olmalı: sahip "öğle yoğun" derken kendi saatini kasteder.
            UTC saatiyle çizilen bir ısı haritası öğle yoğunluğunu sabah
            10:00'a taşır ve sahip vardiya planını yanlış kurar.
        */
        self::assertContains(
            ['weekday' => 2, 'hour' => 13, 'qrResolveCount' => 6],
            $body['hourly'],
            'ANALYTICS-TIMESERIES-HOURLY-04: yoğun hücre yerel gün ve saatle yayımlanmalı.',
        );
    }

    public function test_hour_cells_backed_by_a_single_visitor_are_withheld_and_counted(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-hucre-gizlilik');
        $this->seedBusyTuesday($ctx);

        /*
            TEK KİŞİLİK HÜCRE YAYIMLANMAZ.

            "Perşembe 03:00 · 1 tarama" bir istatistik değil, bir kişinin o
            gece oraya girdiğinin kaydıdır. Toplamda kalabalığın içinde
            kaybolan bir ziyaret, saat kovasına inince tek başına kalır —
            eşik kuralı bu yüzden kovada da uygulanır.

            Hücre SESSİZCE düşürülmez: kaç hücrenin gizlendiği söylenir,
            yoksa ekran "o saatte kimse yoktu" der ve bu yanlıştır.
        */
        $this->event($ctx['workspaceId'], $ctx['locationId'], $ctx['menuId'], 'qr_resolve', '2026-09-03T00:15:00+00:00', 'yalniz-1');

        $body = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertOk()
            ->json();

        $cells = array_map(
            static fn (array $cell): string => $cell['weekday'].'-'.$cell['hour'],
            $body['hourly'],
        );

        self::assertNotContains('4-3', $cells, 'ANALYTICS-TIMESERIES-CELL-PRIVACY-05: tek ziyaretçili hücre yayımlanmamalı.');
        self::assertSame(1, $body['suppressedHourCells']);

        /*
            GÜNLÜK KOVA GİZLENMEZ.

            Gün, bir kişinin ziyaret SAATİNİ açığa çıkarmaz; grafiğin şeklini
            ise taşır. Gizlilik kuralını güne de uygulamak, kimseyi korumadan
            ürünün tek işini bozardı.
        */
        $byDate = array_column($body['buckets'], null, 'date');
        self::assertSame(1, $byDate['2026-09-03']['qrResolveCount']);
    }

    // --- ANALYTICS-TIMESERIES-LOCATION-SHARE-06 ----------------------------

    public function test_location_share_covers_the_whole_brand_even_when_one_location_is_selected(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-sube-payi');
        $this->seedBusyTuesday($ctx);

        $second = $this->location($ctx['workspaceId'], $ctx['brandId'], 'Beşiktaş');

        foreach (range(1, 2) as $index) {
            $this->event($ctx['workspaceId'], $second, $ctx['menuId'], 'qr_resolve', '2026-09-01T11:00:00+00:00', 'besiktas-'.$index);
        }

        $body = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertOk()
            ->json();

        /*
            "ŞUBE PAYI" MARKA SORUSUDUR.

            Tek şubeye süzülmüş bir ekranda pay halkasını da o şubeye
            süzmek, halkayı her zaman %100 çizerdi — yani hiçbir şey
            söylemeyen bir daire. Sorunun kendisi "bu şube markanın ne
            kadarı?" olduğu için pay HER ZAMAN markanın tamamından okunur ve
            kapsamı yanıtta açıkça yazar.
        */
        self::assertSame('workspace', $body['locationShareScope']);

        $share = array_column($body['locationShare'], null, 'label');

        self::assertSame(6, $share['Kadıköy']['qrResolveCount']);
        self::assertSame(2, $share['Beşiktaş']['qrResolveCount']);
        self::assertEqualsWithDelta(75.0, $share['Kadıköy']['sharePercent'], 0.01);
        self::assertEqualsWithDelta(25.0, $share['Beşiktaş']['sharePercent'], 0.01);

        // Çubuk serisi ise seçili şubeye AİTTİR; ikisi karışırsa sahip
        // Beşiktaş'ın taramasını Kadıköy'ün grafiğinde görür.
        self::assertSame(6, array_sum(array_column($body['buckets'], 'qrResolveCount')));
    }

    // --- ANALYTICS-TIMESERIES-THRESHOLD-07 ---------------------------------

    public function test_series_is_withheld_entirely_below_the_visitor_threshold(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-yetersiz');

        foreach (range(1, 2) as $index) {
            $this->event($ctx['workspaceId'], $ctx['locationId'], $ctx['menuId'], 'qr_resolve', '2026-09-01T10:00:00+00:00', 'az-'.$index);
        }

        $body = $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertOk()
            ->json();

        /*
            Boş bir grafik sahibe "ürünüm bozuk" dedirtir. Sebep ve EŞİK
            açıkça söylenir (`docs/66` disiplini, `menu-engineering` ile aynı
            kelimeler): kaç ziyaretçi gerektiğini bilmeyen biri, ne kadar
            bekleyeceğini de bilemez.
        */
        self::assertSame('not_enough_data', $body['state']);
        self::assertSame(5, $body['threshold']);
        self::assertSame(2, $body['observedVisitors']);
        self::assertSame([], $body['buckets']);
        self::assertSame([], $body['hourly']);
        self::assertSame([], $body['locationShare']);
        self::assertNull($body['comparison']);
    }

    // --- ANALYTICS-TIMESERIES-SCOPE-404-08 ---------------------------------

    public function test_outsider_and_cross_tenant_location_both_get_an_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-sahip');

        $stranger = $this->verifiedUser();
        $otherCtx = $this->workspace($stranger, 'seri-yabanci');

        $this->actingAs($stranger)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertNotFound();

        // Kendi çalışma alanı, BAŞKASININ şubesi: 403 değil 404 — 403 o
        // şubenin VAR OLDUĞUNU söylerdi.
        $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $otherCtx['locationId'], '7d'))
            ->assertNotFound();
    }

    // --- ANALYTICS-TIMESERIES-RANGE-422-09 ---------------------------------

    public function test_unknown_range_is_rejected(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-aralik');

        $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '90d'))
            ->assertStatus(422);
    }

    // --- ANALYTICS-TIMESERIES-PLAN-402-10 ----------------------------------

    public function test_plan_without_reporting_gets_402_not_403(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-plan');

        // Planı raporlama İÇERMEYEN bir aboneliğe indir.
        DB::table('subscriptions')->where('workspace_id', $ctx['workspaceId'])->delete();

        $this->actingAs($owner)
            ->getJson($this->url($ctx['workspaceId'], $ctx['locationId'], '7d'))
            ->assertStatus(402);
    }

    // --- ANALYTICS-TIMESERIES-SUMMARY-UNCHANGED-11 -------------------------

    public function test_existing_summary_endpoint_keeps_its_shape(): void
    {
        $owner = $this->verifiedUser();
        $ctx = $this->workspace($owner, 'seri-regresyon');
        $this->seedBusyTuesday($ctx);

        /*
            Yeni uç eskisini DEĞİŞTİRMEZ. `summary` bugün panonun sayaçlarını
            besliyor; alan adı ya da anlamı kayarsa sahip bir sabah dört
            sayacın da sıfır olduğunu görür.
        */
        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$ctx['workspaceId']}/brand/locations/{$ctx['locationId']}/analytics/summary?range=7d")
            ->assertOk()
            ->assertJsonStructure([
                'range', 'qrResolveCount', 'menuOpenCount', 'uniqueVisitorCount',
                'openRate', 'locations', 'qrCodes', 'generatedAt',
            ]);
    }
}
