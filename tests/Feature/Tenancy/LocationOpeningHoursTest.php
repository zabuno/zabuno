<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ŞUBENİN ÇALIŞMA SAATLERİ — `docs/109` §6.4 ve kaynak `panel.dc.html`
 * (`data-screen-label="Şubeler"`).
 *
 * NEDEN KIRMIZI: kaynağın şube kartı üç ölçüyü yan yana gösteriyor —
 * "N masa · N tarama/hafta · 09:00–23:00". İlk ikisi artık gerçek veriden
 * geliyor; ÜÇÜNCÜSÜ hiç yok. Depoda ne sütun, ne uç, ne de giriş yüzeyi
 * var. Bu test o boşluğu tarif eder.
 *
 * ---------------------------------------------------------------------
 * VERİ TASARIMI KARARI VE GEREKÇESİ
 * ---------------------------------------------------------------------
 *
 * 1. **GÜN GÜN saklanır, kartta ÖZETLENİR.** Kaynak kartta tek bir aralık
 *    yazıyor ama bu bir SUNUM biçimidir, verinin şekli değil. Gerçek bir
 *    restoranın haftası tek aralık değildir: pazartesi kapalıdır, cuma
 *    gece ikiye kadar açıktır. Tek aralık saklanırsa o restoran sisteme
 *    ya yalan söyler ya hiç girmez — ve yalan söylediği an "şu anda açık
 *    mıyız" sorusunun cevabı da bozulur. Tersi (gün gün saklayıp kartta
 *    özetlemek) hiçbir şey kaybettirmez: hafta tek tipse kart yine tek
 *    aralık gösterir.
 *
 * 2. **HAFTA BÜTÜN GİRİLİR: ya 7 gün ya hiç.** "Pazartesi–Cuma girilmiş,
 *    cumartesi hiç söylenmemiş" diye bir hâl, ekranda "cumartesi kapalı"
 *    ile ayırt edilemez. Bir haftanın yedi günü vardır; eksik gün bir
 *    veri değil, bir belirsizliktir. Bu yüzden yazma yolu 7 günün
 *    tamamını ister; boş dizi ise saatleri TAMAMEN siler.
 *
 * 3. **GECE YARISI AŞIMI dakikayla ifade edilir.** Saat, günün
 *    başlangıcından itibaren DAKİKA olarak saklanır (09:00 → 540).
 *    Kapanış aynı günün gece yarısını aşabilir ve bu yüzden 1440'ı
 *    geçebilir: "10:00–00:00" → 600/1440, "18:00–02:00" → 1080/1560.
 *    İki ayrı satır ("18:00–23:59" + "00:00–02:00") yazmak, tek bir
 *    servisi iki güne bölerdi ve "salı gecesi kaça kadar açıksınız"
 *    sorusunun cevabı iki satırdan toplanmak zorunda kalırdı. Aynı
 *    birim menü servis aralıklarında da kullanılıyor
 *    (`MenuSchedulePort`), yani depo tek bir saat dili konuşur.
 *
 * 4. **KAPALI GÜN AÇIKÇA yazılır.** `closed: true` bir olgudur; saat
 *    alanlarının boş bırakılmasıyla karıştırılamaz. "Pazartesi kapalıyız"
 *    ile "pazartesiyi hiç girmedim" aynı şey değildir ve misafire
 *    verilecek cevap da aynı değildir.
 *
 * 5. **SAAT DİLİMİ ŞUBENİNKİDİR.** `locations.timezone` zaten var
 *    (`docs/62`); saatler ona göre okunur. Sunucunun saatine göre
 *    yorumlanırsa aynı marka İstanbul ve Berlin şubesinde farklı
 *    saatlerde "açık" görünürdü.
 *
 * 6. **GERİYE UYUM: yokluk sessizdir.** Saati girilmemiş şube bozulmadan
 *    çalışır ve `opening_hours` BOŞ DİZİ döner. Uydurma bir "09:00–23:00"
 *    varsayılanı yazılsaydı, sahibin hiç söylemediği bir iddia ekranda
 *    doğruymuş gibi görünürdü. Alanı hiç göndermeyen eski bir istemci de
 *    kayıtlı saatleri SİLEMEZ.
 */
final class LocationOpeningHoursTest extends TestCase
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
    private function validLocationPayload(string $displayName = 'Zeytin Kadıköy'): array
    {
        return [
            'display_name' => $displayName,
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Moda Caddesi 12',
        ];
    }

    /**
     * Haftanın yedi günü. Varsayılan olarak 09:00–23:00; çağıran istediği
     * günü ezer, böylece testler yalnız İLGİLENDİKLERİ günü yazar.
     *
     * @param  array<int, array<string, mixed>>  $overrides
     * @return list<array<string, mixed>>
     */
    private function week(array $overrides = []): array
    {
        $days = [];

        for ($day = 1; $day <= 7; $day++) {
            $days[] = $overrides[$day] ?? [
                'day' => $day,
                'closed' => false,
                'opens_minute' => 540,
                'closes_minute' => 1380,
            ];
        }

        return $days;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedWorkspaceWithLocation(User $owner, string $slug): array
    {
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', $slug);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand", $this->validBrandPayload())
            ->assertStatus(201);

        $location = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspaceId}/brand/locations", $this->validLocationPayload());
        $location->assertStatus(201);

        return [$workspaceId, (int) $location->json('id')];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function updatePayload(array $extra = []): array
    {
        return [
            ...$this->validLocationPayload(),
            'timezone' => 'Europe/Istanbul',
            ...$extra,
        ];
    }

    /**
     * Aynı markaya İKİNCİ bir şube açar.
     *
     * "Aynı anda birinde açık, birinde kapalı" iddiası ancak iki şube aynı
     * listede yan yana dururken sınanabilir; tek şubeyle sınanan bir saat
     * dilimi kuralı, listenin tamamı için tek bir "şu an"ın okunduğunu
     * kanıtlamaz.
     */
    private function createLocation(User $owner, int $workspaceId, string $displayName): int
    {
        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson(
                "/api/workspaces/{$workspaceId}/brand/locations",
                $this->validLocationPayload($displayName),
            );
        $response->assertStatus(201);

        return (int) $response->json('id');
    }

    /**
     * Bir şubenin saat dilimini ve haftasını TEK istekle yazar.
     *
     * @param  list<array<string, mixed>>  $week
     */
    private function saveHours(User $owner, int $workspaceId, int $locationId, string $timezone, array $week): void
    {
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}",
                $this->updatePayload(['timezone' => $timezone, 'opening_hours' => $week]),
            )
            ->assertStatus(200);
    }

    // --- LOCATION-HOURS-01 -------------------------------------------------

    /**
     * Saati girilmemiş şube BOZULMADAN çalışır ve boş dizi döner.
     *
     * Sahibin yolculuğu: dün açtığı Bostancı şubesinin saatini henüz
     * girmedi. Kart o alanı hiç göstermemeli — ama bunun için ucun
     * "girilmedi"yi açıkça söylemesi gerekir. Alanın hiç bulunmaması,
     * eski bir istemcinin `undefined` okuyup patlaması demekti.
     */
    public function test_a_location_without_hours_answers_with_an_empty_list(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-hours-01');

        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations");
        $list->assertStatus(200);

        self::assertArrayHasKey(
            'opening_hours',
            (array) $list->json('0'),
            'LOCATION-HOURS-01: liste ucu çalışma saatlerini taşımalı; kart onu ayrı bir istek atmadan çizebilmeli.'
        );
        self::assertSame(
            [],
            $list->json('0.opening_hours'),
            'LOCATION-HOURS-01: saat girilmemişse BOŞ döner — uydurma bir 09:00–23:00 varsayılanı yoktur.'
        );

        $show = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}");
        $show->assertStatus(200);
        self::assertSame([], $show->json('opening_hours'));
    }

    // --- LOCATION-HOURS-02 -------------------------------------------------

    /**
     * Gece yarısını aşan aralık ve kapalı gün AYNI haftada yaşayabilir.
     *
     * Sahibin yolculuğu: hafta içi 09:00–23:00, cuma gece 02:00'ye kadar,
     * cumartesi gece yarısında kapanıyor, pazartesi kapalı. Bu dört hâlin
     * dördü de gerçek restoran hâlidir ve dördü de tek bir haftada var.
     */
    public function test_a_week_carries_midnight_crossing_ranges_and_a_closed_day(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-hours-02');

        $week = $this->week([
            // Pazartesi KAPALI: saat alanları yok, kapalılık bir olgudur.
            1 => ['day' => 1, 'closed' => true, 'opens_minute' => null, 'closes_minute' => null],
            // Cuma 18:00–02:00 → kapanış ertesi güne taşar (1080 → 1560).
            5 => ['day' => 5, 'closed' => false, 'opens_minute' => 1080, 'closes_minute' => 1560],
            // Cumartesi 10:00–00:00 → tam gece yarısı (600 → 1440).
            6 => ['day' => 6, 'closed' => false, 'opens_minute' => 600, 'closes_minute' => 1440],
        ]);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}",
                $this->updatePayload(['opening_hours' => $week]),
            )
            ->assertStatus(200);

        $show = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}");
        $show->assertStatus(200);

        /** @var list<array<string, mixed>> $hours */
        $hours = (array) $show->json('opening_hours');

        self::assertCount(7, $hours, 'LOCATION-HOURS-02: hafta bütün döner — yedi gün.');

        $byDay = [];

        foreach ($hours as $row) {
            $byDay[(int) $row['day']] = $row;
        }

        self::assertTrue(
            (bool) $byDay[1]['closed'],
            'LOCATION-HOURS-02: kapalı gün açıkça kapalı döner.'
        );
        self::assertNull($byDay[1]['opens_minute']);
        self::assertSame(
            1560,
            (int) $byDay[5]['closes_minute'],
            'LOCATION-HOURS-02: gece yarısını aşan kapanış 1440 üstünde saklanır (18:00–02:00).'
        );
        self::assertSame(
            1440,
            (int) $byDay[6]['closes_minute'],
            'LOCATION-HOURS-02: tam gece yarısı kapanış 1440 olarak saklanır (10:00–00:00), 0 değil.'
        );
        self::assertSame(540, (int) $byDay[2]['opens_minute']);
        self::assertSame(1380, (int) $byDay[2]['closes_minute']);

        // Gün gün saklama listede de görünür: kart özeti tek istekten çıkar.
        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations");
        $list->assertStatus(200);
        self::assertCount(7, (array) $list->json('0.opening_hours'));
    }

    // --- LOCATION-HOURS-03 -------------------------------------------------

    /**
     * İmkânsız hafta REDDEDİLİR.
     *
     * Her biri ekranda sessiz bir yalana dönüşürdü: kapanışı açılıştan
     * önce olan bir gün "hiç açık değil" demektir ama ekranda bir aralık
     * gibi görünür; 24 saatten uzun bir aralık ertesi günün kendi
     * aralığını yutar; eksik/çift gün, haftanın bir gününü belirsiz
     * bırakır.
     *
     * @param  list<array<string, mixed>>  $week
     */
    #[DataProvider('impossibleWeeks')]
    public function test_an_impossible_week_is_rejected(array $week, string $because): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-hours-03-'.md5($because));

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}",
                $this->updatePayload(['opening_hours' => $week]),
            )
            ->assertStatus(422, $because);
    }

    /**
     * @return array<string, array{0: list<array<string, mixed>>, 1: string}>
     */
    public static function impossibleWeeks(): array
    {
        $full = static function (array $overrides): array {
            $days = [];

            for ($day = 1; $day <= 7; $day++) {
                $days[] = $overrides[$day] ?? [
                    'day' => $day,
                    'closed' => false,
                    'opens_minute' => 540,
                    'closes_minute' => 1380,
                ];
            }

            return $days;
        };

        $sixDays = $full([]);
        array_pop($sixDays);

        $duplicated = $full([]);
        $duplicated[6] = ['day' => 6, 'closed' => false, 'opens_minute' => 540, 'closes_minute' => 1380];

        return [
            'kapanış açılıştan önce' => [
                $full([3 => ['day' => 3, 'closed' => false, 'opens_minute' => 1200, 'closes_minute' => 600]]),
                'LOCATION-HOURS-03: kapanış açılıştan önce olamaz.',
            ],
            'kapanış açılışa eşit' => [
                $full([3 => ['day' => 3, 'closed' => false, 'opens_minute' => 600, 'closes_minute' => 600]]),
                'LOCATION-HOURS-03: sıfır uzunlukta bir gün "açık" değildir; kapalı gün için `closed` vardır.',
            ],
            '24 saatten uzun' => [
                $full([3 => ['day' => 3, 'closed' => false, 'opens_minute' => 600, 'closes_minute' => 2100]]),
                'LOCATION-HOURS-03: bir gün 24 saatten uzun süremez; ertesi günün kendi aralığı vardır.',
            ],
            'açılış gün dışında' => [
                $full([3 => ['day' => 3, 'closed' => false, 'opens_minute' => 1440, 'closes_minute' => 1500]]),
                'LOCATION-HOURS-03: açılış aynı gün içinde olmalı (0–1439).',
            ],
            'eksik gün' => [
                $sixDays,
                'LOCATION-HOURS-03: eksik gün belirsizliktir; hafta bütün girilir.',
            ],
            'çift gün' => [
                $duplicated,
                'LOCATION-HOURS-03: aynı gün iki kez yazılamaz.',
            ],
            'açık gün saatsiz' => [
                $full([3 => ['day' => 3, 'closed' => false, 'opens_minute' => null, 'closes_minute' => null]]),
                'LOCATION-HOURS-03: kapalı değilse saat zorunludur.',
            ],
        ];
    }

    // --- LOCATION-HOURS-04 -------------------------------------------------

    /**
     * Alanı GÖNDERMEYEN istek kayıtlı saatleri SİLMEZ.
     *
     * Aynı koruma saat diliminde de var (`UpdateLocation`). Adresini
     * düzelten eski bir istemci, hiç dokunmadığı çalışma saatlerini
     * sessizce silseydi, misafire "kapalıyız" demenin dayanağı bir
     * adres düzeltmesiyle yok olurdu.
     */
    public function test_an_update_without_the_field_keeps_the_saved_hours(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-hours-04');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}",
                $this->updatePayload(['opening_hours' => $this->week()]),
            )
            ->assertStatus(200);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}",
                $this->updatePayload(['address_line1' => 'Moda Caddesi 14']),
            );
        $response->assertStatus(200);

        self::assertCount(
            7,
            (array) $response->json('opening_hours'),
            'LOCATION-HOURS-04: alanı taşımayan istek saatleri silmemeli.'
        );
        self::assertSame('Moda Caddesi 14', $response->json('address_line1'));
    }

    // --- LOCATION-HOURS-05 -------------------------------------------------

    /**
     * Boş dizi SİLER — "artık söylemiyorum" da bir karardır.
     *
     * Sahip yanlış saat girdiğini fark ettiğinde tek çıkışı yanlışı
     * düzeltmek olmamalı; alanı tamamen boşaltıp kartın o satırı hiç
     * göstermemesini isteyebilmeli.
     */
    public function test_an_empty_list_clears_the_hours(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-hours-05');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}",
                $this->updatePayload(['opening_hours' => $this->week()]),
            )
            ->assertStatus(200);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}",
                $this->updatePayload(['opening_hours' => []]),
            );
        $response->assertStatus(200);

        self::assertSame(
            [],
            $response->json('opening_hours'),
            'LOCATION-HOURS-05: boş dizi saatleri tamamen siler.'
        );
    }

    // --- LOCATION-HOURS-TENANT-01 -----------------------------------------

    /**
     * Saatler ŞUBEYE bağlıdır ve başka bir kiracıya sızmaz.
     *
     * Çalışma saati ticari bir bilgidir; rakip bir markanın panelinde
     * görünmesi tek başına bir sızıntıdır. Yazma yolu da aynı sınırı
     * tanır: başka kiracının şubesine saat yazılamaz.
     */
    public function test_hours_never_leak_or_land_in_another_tenant(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-hours-tenant');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}",
                $this->updatePayload(['opening_hours' => $this->week()]),
            )
            ->assertStatus(200);

        $intruder = $this->verifiedUser();
        [$otherWorkspaceId, $otherLocationId] = $this->seedWorkspaceWithLocation($intruder, 'deniz-hours-tenant');

        // Yabancının şubesi kendi saatlerini taşır — komşununkini değil.
        $otherList = $this->actingAs($intruder)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$otherWorkspaceId}/brand/locations");
        $otherList->assertStatus(200);
        self::assertSame(
            [],
            $otherList->json('0.opening_hours'),
            'LOCATION-HOURS-TENANT-01: başka kiracının saatleri bu listede görünemez.'
        );
        self::assertSame($otherLocationId, (int) $otherList->json('0.id'));

        // Yabancı, komşunun şubesine saat yazamaz.
        $this->actingAs($intruder)->withHeaders($this->jsonHeaders())
            ->putJson(
                "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}",
                $this->updatePayload(['opening_hours' => $this->week()]),
            )
            ->assertStatus(404);
    }

    // --- LOCATION-OPEN-NOW-01..04 -----------------------------------------

    /*
        ŞU AN AÇIK MIYIZ — ŞUBE KARTININ CEVAPLAYAMADIĞI SORU (FF-148).

        NEDEN KIRMIZI: `opening_hours` uçta duruyor ve kart onu ÖZETLİYOR
        ("Today 09:00–23:00"), ama özet bir tarifedir, bir DURUM değildir.
        Sahibin sorduğu soru "saatiniz kaçtan kaça" değil, "Kadıköy şu an
        açık mı" — ve bugün bu sorunun uçta hiçbir karşılığı yok.

        NEDEN CEVAP SUNUCUDAN GELİR
        ---------------------------
        Tarayıcının saati kullanıcının kendi ayarıdır: yanlış kurulmuş bir
        dizüstü, açık bir şubeye "kapalı" dedirtirdi. Şubenin saat dilimi
        ise zaten sunucuda (`locations.timezone`, `docs/62`) ve kapalılık
        kuralı da orada (`WeeklyOpeningHours::isClosedAt`). Cevabı istemcide
        yeniden hesaplamak İKİNCİ BİR HESAP kurmak olurdu; iki hesap bir gün
        aynı şube için iki farklı cevap verir ve hangisinin doğru olduğu
        ancak misafir kapalı kapıya dayandığında anlaşılır. Misafir yüzeyi
        (`ResolveGuestMenuView::closedNoticeForMenu`) bu değer nesnesini
        zaten kullanıyor; sahibin paneli de AYNI kaynağı kullanır.

        NEDEN ÜÇ DEĞERLİ (`true` / `false` / `null`)
        --------------------------------------------
        `null` "söylenmemiş"tir ve `false` ile aynı şey DEĞİLDİR. Saatini hiç
        girmemiş bir şubeye "kapalı" demek, sahibin hiç kurmadığı bir cümleyi
        onun ağzından söylemek olurdu — aynı sessizlik kuralı misafir
        tarafında da geçerli (`GuestOpeningHoursPort`).
    */

    /**
     * AÇIK saatte `open_now` doğrudur.
     *
     * Sahibin yolculuğu: pazartesi öğlen 12:00, Kadıköy 09:00–23:00 çalışıyor.
     * Şubeler ekranına baktığında kartın "şu an açık" demesi gerekir.
     */
    public function test_a_location_inside_its_hours_answers_open_now(): void
    {
        // 09:00 UTC = İstanbul'da pazartesi 12:00 — haftanın ortasında,
        // 09:00–23:00 aralığının tam içinde.
        Carbon::setTestNow(Carbon::parse('2026-09-07T09:00:00Z'));

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-open-now-01');

        $this->saveHours($owner, $workspaceId, $locationId, 'Europe/Istanbul', $this->week());

        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations");
        $list->assertStatus(200);

        self::assertArrayHasKey(
            'open_now',
            (array) $list->json('0'),
            'LOCATION-OPEN-NOW-01: liste ucu "şu an açık mı"yı taşımalı; kart bunu tarayıcı saatinden çıkaramaz.'
        );
        self::assertTrue(
            $list->json('0.open_now'),
            'LOCATION-OPEN-NOW-01: pazartesi 12:00, 09:00–23:00 aralığının içindedir.'
        );

        $show = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}");
        $show->assertStatus(200);
        self::assertTrue(
            $show->json('open_now'),
            'LOCATION-OPEN-NOW-01: tekil uç ile liste ucu aynı cevabı vermeli; iki uç ayrışırsa iki ekran ayrışır.'
        );
    }

    /**
     * KAPALI saatte `open_now` yanlıştır — ve "null" değildir.
     *
     * Sahibin yolculuğu: sabah 05:00'te panele bakıyor. Şube 09:00'da
     * açılıyor; kart "kapalı" demeli, çünkü bu KANITLANABİLİR bir olgudur.
     */
    public function test_a_location_outside_its_hours_answers_closed_now(): void
    {
        // 02:00 UTC = İstanbul'da pazartesi 05:00 — açılıştan dört saat önce.
        Carbon::setTestNow(Carbon::parse('2026-09-07T02:00:00Z'));

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-open-now-02');

        $this->saveHours($owner, $workspaceId, $locationId, 'Europe/Istanbul', $this->week());

        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations");
        $list->assertStatus(200);

        self::assertFalse(
            $list->json('0.open_now'),
            'LOCATION-OPEN-NOW-02: 05:00, 09:00–23:00 aralığının dışındadır — kapalıyız.'
        );
        self::assertNotNull(
            $list->json('0.open_now'),
            'LOCATION-OPEN-NOW-02: "kapalı" bir cevaptır; sessizlikle karıştırılamaz.'
        );
    }

    /**
     * SAATİ GİRİLMEMİŞ şubede alan boştur — "kapalı" da yazılmaz.
     *
     * Sahibin yolculuğu: dün açtığı Bostancı şubesinin saatini henüz
     * girmedi. Kart hiçbir durum rozeti çizmemeli. "Kapalı" demek, sahibin
     * söylemediği bir cümleyi ekranda doğruymuş gibi göstermek; "bilinmiyor"
     * demek ise boş bir rozetle yer kaplamak olurdu.
     */
    public function test_a_location_without_hours_never_claims_a_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07T09:00:00Z'));

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-open-now-03');

        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations");
        $list->assertStatus(200);

        self::assertArrayHasKey(
            'open_now',
            (array) $list->json('0'),
            'LOCATION-OPEN-NOW-03: alan HER ZAMAN bulunur; eksik alan istemciyi `undefined` okumaya bırakırdı.'
        );
        self::assertNull(
            $list->json('0.open_now'),
            'LOCATION-OPEN-NOW-03: saat girilmemişse cevap YOKTUR — `false` değil, `null`.'
        );

        $show = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}");
        $show->assertStatus(200);
        self::assertNull($show->json('open_now'));
    }

    /**
     * AYNI ANDA iki şube, iki farklı cevap.
     *
     * Sahibin yolculuğu: markanın bir şubesi İstanbul'da, biri Auckland'da.
     * Sahip İstanbul'dan bakıyor; saat gecenin 05:00'i. Kadıköy kapalı ama
     * Auckland'da öğleden sonra 14:00 — orası açık. Sunucunun ya da
     * tarayıcının saati kullanılsaydı iki kart AYNI durumu gösterirdi ve
     * sahip, açık olan şubesini kapalı sanırdı.
     *
     * Tarih bilerek 2026-09-07: Yeni Zelanda yaz saati 27 Eylül'de başlar,
     * yani o gün Auckland UTC+12'dir ve testin doğruluğu bir DST geçişine
     * yaslanmaz.
     */
    public function test_two_locations_in_different_timezones_answer_differently_at_the_same_instant(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07T02:00:00Z'));

        $owner = $this->verifiedUser();
        [$workspaceId, $istanbulId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-open-now-04');
        $aucklandId = $this->createLocation($owner, $workspaceId, 'Zeytin Auckland');

        // İkisi de 09:00–23:00 çalışıyor: fark saatlerde değil, DÜNYADA.
        $this->saveHours($owner, $workspaceId, $istanbulId, 'Europe/Istanbul', $this->week());
        $this->saveHours($owner, $workspaceId, $aucklandId, 'Pacific/Auckland', $this->week());

        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations");
        $list->assertStatus(200);

        $byId = [];

        foreach ((array) $list->json() as $row) {
            $byId[(int) $row['id']] = $row;
        }

        self::assertFalse(
            $byId[$istanbulId]['open_now'],
            'LOCATION-OPEN-NOW-04: İstanbul 05:00 — kapalı.'
        );
        self::assertTrue(
            $byId[$aucklandId]['open_now'],
            'LOCATION-OPEN-NOW-04: Auckland 14:00 — açık. Aynı an, iki şube, iki cevap.'
        );
    }

    /**
     * GECE YARISINI AŞAN servis "kapalı" sayılmaz.
     *
     * Sahibin yolculuğu: cuma gecesi 18:00–02:00 çalışan bir mekân. Saat
     * 01:00'de sahip panele bakıyor; salon dolu. Yalnız BUGÜNÜN satırına
     * bakan bir hesap "kapalı" derdi — çünkü cumartesi 09:00'da açılıyor.
     * Doğru cevap DÜNÜN aralığındadır ve `WeeklyOpeningHours` bunu zaten
     * biliyor; bu test o bilginin uca kadar taşındığını sabitler.
     */
    public function test_a_service_that_crosses_midnight_still_counts_as_open(): void
    {
        // 22:00 UTC cuma = İstanbul'da CUMARTESİ 01:00.
        Carbon::setTestNow(Carbon::parse('2026-09-11T22:00:00Z'));

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->seedWorkspaceWithLocation($owner, 'zeytin-open-now-05');

        $this->saveHours($owner, $workspaceId, $locationId, 'Europe/Istanbul', $this->week([
            // Cuma 18:00–02:00 → 1080 → 1560 (gece yarısı aşımı).
            5 => ['day' => 5, 'closed' => false, 'opens_minute' => 1080, 'closes_minute' => 1560],
        ]));

        $show = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceId}/brand/locations/{$locationId}");
        $show->assertStatus(200);

        self::assertTrue(
            $show->json('open_now'),
            'LOCATION-OPEN-NOW-05: cumartesi 01:00, cumanın 18:00–02:00 servisinin İÇİNDEDİR.'
        );
    }
}
