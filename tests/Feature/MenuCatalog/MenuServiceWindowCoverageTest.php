<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Application\MenuCatalog\Exception\LastMenuForLocationException;
use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Application\MenuCatalog\UseCase\ResolveServingMenu;
use App\Domain\MenuCatalog\ServiceDayTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\MenuCatalog\Support\MultiMenuScaffold;
use Tests\TestCase;

/**
 * SAAT BAZLI MENÜ GEÇİŞİNİN KAPSAMA KURALI — sahibin 2026-09-05 kararı,
 * `docs/109-PANEL-V3.md` §7.1: *"Aralıklar ÇAKIŞAMAZ ve boşluk bırakılamaz:
 * gün 24 saattir ve hiçbir saatte 'hangi menü' sorusu cevapsız kalamaz.
 * Cevapsız kalırsa misafir boş bir sayfa görür."*
 *
 * MODEL KARARI VE GEREKÇESİ — neden (başlangıç, bitiş) çifti saklamıyoruz
 *
 * Her menüye bir (başlangıç, bitiş) çifti verseydik, "boşluk yok + çakışma
 * yok" kuralı her yazma yolunda YENİDEN doğrulanmak zorunda kalırdı: menü
 * ekleme, düzenleme, silme, devre dışı bırakma, CSV aktarımı, ileride
 * eklenecek her yol. Bir yol o doğrulamayı unuttuğunda kimse fark etmez;
 * fark eden, sabah 07:05'te boş bir sayfaya bakan misafir olur.
 *
 * Bunun yerine gün, GEÇİŞ ANLARIYLA bölünür (`menu_service_switches`):
 * her satır "şu dakikadan itibaren şu menü" der. Bir andaki menü, o andan
 * önceki EN SON geçiştir; gün başındaki an ise günün SON geçişine bağlanır
 * (gece yarısını aşan aralık böyle doğal olarak çalışır). Bu modelde:
 *
 * - ÇAKIŞMA imkânsızdır: `unique(location_id, start_minute)` bir dakikaya
 *   iki menü koymayı veritabanı düzeyinde reddeder ve bir an her zaman TEK
 *   bir geçişe düşer.
 * - BOŞLUK imkânsızdır: geçişler günü döngüsel olarak böler; bir geçiş
 *   eklemek bir aralığı ikiye böler, silmek iki aralığı birleştirir.
 * - Hiç geçiş yoksa gün bütünüyle ŞUBENİN ÇIPA MENÜSÜNE aittir — bugünkü
 *   tek menülü şubelerin davranışı budur ve değişmez.
 */
final class MenuServiceWindowCoverageTest extends TestCase
{
    use MultiMenuScaffold;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function schedule(): MenuSchedulePort
    {
        return app(MenuSchedulePort::class);
    }

    private function resolver(): ResolveServingMenu
    {
        return app(ResolveServingMenu::class);
    }

    /** Şubenin saatinde belirli bir saati "şimdi" yapar. */
    private function nowAt(string $clock, string $timezone = 'Europe/Istanbul'): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 '.$clock, $timezone));
    }

    // --- COVERAGE-SINGLE-MENU-01 -------------------------------------------

    public function test_a_location_with_one_untimed_menu_answers_every_hour_exactly_as_before(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'coverage-single');
        $menuId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);

        foreach (['00:00', '07:30', '12:00', '23:59'] as $clock) {
            $this->nowAt($clock);

            self::assertSame(
                $menuId,
                $this->resolver()->forMenu($menuId),
                "COVERAGE-SINGLE-MENU-01: tek menülü şube {$clock}'te de aynı menüyü vermeli (geriye uyum)."
            );
        }
    }

    // --- COVERAGE-BREAKFAST-01 ---------------------------------------------

    public function test_the_guest_gets_the_breakfast_menu_at_eight_and_the_main_menu_at_noon(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'coverage-breakfast');
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);
        $this->schedule()->setServiceWindow($workspaceId, $breakfastId, 7 * 60, 11 * 60);

        $this->nowAt('08:00');
        self::assertSame($breakfastId, $this->resolver()->forMenu($mainId), 'COVERAGE-BREAKFAST-01: 08:00 kahvaltıdır.');

        $this->nowAt('12:00');
        self::assertSame($mainId, $this->resolver()->forMenu($mainId), 'COVERAGE-BREAKFAST-01: 11:00 sonrası ana menü GERİ GELİR.');

        $this->nowAt('06:00');
        self::assertSame($mainId, $this->resolver()->forMenu($mainId));

        $this->nowAt('07:00');
        self::assertSame($breakfastId, $this->resolver()->forMenu($mainId), 'Aralığın ilk dakikası KAHVALTIYA aittir.');

        $this->nowAt('11:00');
        self::assertSame($mainId, $this->resolver()->forMenu($mainId), 'Bitiş dakikası artık kahvaltıya ait DEĞİLDİR.');
    }

    // --- COVERAGE-MIDNIGHT-01 ----------------------------------------------

    public function test_a_window_that_crosses_midnight_works(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'coverage-midnight');
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);
        $nightId = $this->insertMenu($workspaceId, $locationId, 'Gece menüsü', null, 1);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);
        $this->schedule()->setServiceWindow($workspaceId, $nightId, 22 * 60, 2 * 60);

        $this->nowAt('23:00');
        self::assertSame($nightId, $this->resolver()->forMenu($mainId), 'COVERAGE-MIDNIGHT-01: 23:00 gece menüsüdür.');

        $this->nowAt('01:00');
        self::assertSame($nightId, $this->resolver()->forMenu($mainId), 'COVERAGE-MIDNIGHT-01: gece yarısı aralığı KESMEZ.');

        $this->nowAt('03:00');
        self::assertSame($mainId, $this->resolver()->forMenu($mainId));

        $this->nowAt('15:00');
        self::assertSame($mainId, $this->resolver()->forMenu($mainId));
    }

    // --- COVERAGE-NO-GAP-01 ------------------------------------------------

    public function test_no_minute_of_the_day_is_left_without_a_menu(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'coverage-no-gap');
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);
        $nightId = $this->insertMenu($workspaceId, $locationId, 'Gece', null, 2);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);
        $this->schedule()->setServiceWindow($workspaceId, $breakfastId, 7 * 60, 11 * 60);
        $this->schedule()->setServiceWindow($workspaceId, $nightId, 22 * 60, 2 * 60);

        $timeline = ServiceDayTimeline::fromSwitches($this->schedule()->switchesForLocation($locationId));

        $answered = [];

        for ($minute = 0; $minute < ServiceDayTimeline::MINUTES_PER_DAY; $minute++) {
            $answered[$minute] = $timeline->menuIdAt($minute);
        }

        self::assertNotContains(
            null,
            $answered,
            'COVERAGE-NO-GAP-01: günün hiçbir dakikası cevapsız kalamaz — kalırsa misafir boş sayfa görür.'
        );
        self::assertSame($breakfastId, $answered[8 * 60]);
        self::assertSame($nightId, $answered[23 * 60]);
        self::assertSame($nightId, $answered[30]);
        self::assertSame($mainId, $answered[13 * 60]);
    }

    // --- COVERAGE-AFTER-DELETE-01 ------------------------------------------

    public function test_deleting_a_timed_menu_leaves_no_hole_in_the_day(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'coverage-delete');
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);
        $this->schedule()->setServiceWindow($workspaceId, $breakfastId, 7 * 60, 11 * 60);

        $this->schedule()->delete($workspaceId, $breakfastId);

        $timeline = ServiceDayTimeline::fromSwitches($this->schedule()->switchesForLocation($locationId));

        for ($minute = 0; $minute < ServiceDayTimeline::MINUTES_PER_DAY; $minute++) {
            self::assertSame(
                $mainId,
                $timeline->menuIdAt($minute),
                "COVERAGE-AFTER-DELETE-01: {$minute}. dakika silinen menünün boşluğuna düşmemeli."
            );
        }

        self::assertSame(
            0,
            DB::table('menu_service_switches')->where('menu_id', $breakfastId)->count(),
            'Silinen menünün geçiş anları da gitmeli; yoksa var olmayan bir menüye işaret eden bir dakika kalırdı.'
        );
    }

    // --- COVERAGE-AFTER-DISABLE-01 -----------------------------------------

    public function test_disabling_a_menu_hands_its_hours_back_without_a_hole(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'coverage-disable');
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);
        $ramadanId = $this->insertMenu($workspaceId, $locationId, 'Ramazan', null, 1);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);
        $this->schedule()->setServiceWindow($workspaceId, $ramadanId, 19 * 60, 22 * 60);

        $this->nowAt('20:00');
        self::assertSame($ramadanId, $this->resolver()->forMenu($mainId));

        $this->schedule()->clearServiceWindow($workspaceId, $ramadanId);

        $this->nowAt('20:00');
        self::assertSame(
            $mainId,
            $this->resolver()->forMenu($mainId),
            'COVERAGE-AFTER-DISABLE-01: kapatılan menünün saatleri sahipsiz kalamaz.'
        );
        self::assertSame('disabled', (string) DB::table('menus')->where('id', $ramadanId)->value('state'));
    }

    // --- COVERAGE-LAST-MENU-01 ---------------------------------------------

    public function test_the_last_menu_of_a_location_cannot_be_deleted(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'coverage-last');
        $menuId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);

        $this->expectException(LastMenuForLocationException::class);

        $this->schedule()->delete($workspaceId, $menuId);
    }

    // --- COVERAGE-LOCATION-TIMEZONE-01 -------------------------------------

    public function test_the_switch_happens_in_the_locations_own_timezone_not_the_servers(): void
    {
        $owner = $this->verifiedUser();
        // Berlin: yaz saatinde İstanbul'dan bir saat geride. Aynı ANDA iki
        // şube farklı menü göstermek zorunda — "Europe/Istanbul" sabitlense
        // Berlin şubesi kahvaltıyı bir saat geç açardı.
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'coverage-tz', 'Europe/Berlin');
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $this->newPublicKey(), 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);
        $this->schedule()->setServiceWindow($workspaceId, $breakfastId, 7 * 60, 11 * 60);

        // Berlin'de 08:00.
        Carbon::setTestNow(Carbon::parse('2026-09-05 08:00', 'Europe/Berlin'));
        self::assertSame($breakfastId, $this->resolver()->forMenu($mainId));

        // Berlin'de 06:30 (İstanbul'da 07:30 — sabit saat dilimi olsaydı
        // burada yanlışlıkla kahvaltı dönerdi).
        Carbon::setTestNow(Carbon::parse('2026-09-05 06:30', 'Europe/Berlin'));
        self::assertSame(
            $mainId,
            $this->resolver()->forMenu($mainId),
            'LOCATION-TIMEZONE-01: geçiş ŞUBENİN saatinde olmalı.'
        );
    }
}
