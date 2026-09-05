<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Application\MenuCatalog\Exception\LastMenuForLocationException;
use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Application\MenuCatalog\UseCase\ResolveServingMenu;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Domain\MenuCatalog\ServiceDayTimeline;
use App\Domain\Publication\MenuPublicAddress;
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

    /*
    |---------------------------------------------------------------------------
    | MİSAFİR TARAFI (FF-139)
    |---------------------------------------------------------------------------
    |
    | Yukarıdaki testler kuralın İÇ tarafını dondurur: hangi dakikada hangi
    | menü. Aşağıdakiler misafirin GÖZÜNÜ dondurur: karekodu okutan kişi o
    | saatte ekranda ne buluyor.
    |
    | İkisi ayrı sorulardır ve ayrı ayrı kırılabilir. Çözümleyici doğru menüyü
    | seçse bile, o menünün YAYINI yoksa misafir yine bir şey göremez — ve
    | gördüğü şeyin dürüst olup olmadığı yalnız HTTP ucunda ölçülebilir.
    */

    /** Şubenin kalıcı genel adresini (`public_key`) çözer. */
    private function addressFor(string $key): MenuPublicAddress
    {
        $address = app(PublicMenuAddressPort::class)->findByPublicKey($key);

        self::assertNotNull($address, 'Test öncülü: adresin çözülebilmesi gerekiyor.');

        return MenuPublicAddress::fromKeyAndSlug($address['key'], $address['slug'], $address['locale']);
    }

    // --- GUEST-SERVING-MENU-01 ---------------------------------------------

    public function test_the_public_address_shows_the_menu_that_is_being_served_right_now(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'guest-serving');
        $key = $this->newPublicKey();
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $key, 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);

        $this->publishMenu($workspaceId, $locationId, $mainId, (int) $owner->id, 'Ana yemekler', [
            ['menuItemId' => 101, 'productName' => 'Adana Kebap', 'priceMinorAmount' => 32000, 'currencyCode' => 'TRY'],
        ]);
        $this->publishMenu($workspaceId, $locationId, $breakfastId, (int) $owner->id, 'Kahvaltılıklar', [
            ['menuItemId' => 202, 'productName' => 'Menemen', 'priceMinorAmount' => 18000, 'currencyCode' => 'TRY'],
        ]);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);
        $this->schedule()->setServiceWindow($workspaceId, $breakfastId, 7 * 60, 11 * 60);

        $path = $this->addressFor($key)->path();

        $this->nowAt('08:00');
        $morning = $this->get($path);
        $morning->assertStatus(200);
        $morning->assertSee('Menemen', false);
        $morning->assertDontSee('Adana Kebap', false);

        $this->nowAt('13:00');
        $noon = $this->get($path);
        $noon->assertStatus(200);
        $noon->assertSee('Adana Kebap', false);
        // Aynı adres, aynı basılı kâğıt: değişen tek şey saat.
        $noon->assertDontSee('Menemen', false);
    }

    // --- GUEST-OUT-OF-SERVICE-01 -------------------------------------------

    public function test_a_guest_who_arrives_while_the_served_menu_has_no_published_content_is_told_the_truth(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'guest-out-of-service');
        $key = $this->newPublicKey();
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $key, 0);
        // Gece menüsü TANIMLI ve SAATLİ ama HİÇ YAYINLANMAMIŞ. Sahip onu
        // rotasyona koydu, içeriğini doldurmayı yarına bıraktı.
        $nightId = $this->insertMenu($workspaceId, $locationId, 'Gece menüsü', null, 1);

        $this->publishMenu($workspaceId, $locationId, $mainId, (int) $owner->id, 'Ana yemekler', [
            ['menuItemId' => 101, 'productName' => 'Adana Kebap', 'priceMinorAmount' => 32000, 'currencyCode' => 'TRY'],
        ]);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);
        $this->schedule()->setServiceWindow($workspaceId, $nightId, 22 * 60, 2 * 60);

        $path = $this->addressFor($key)->path();

        $this->nowAt('23:00');
        $response = $this->get($path);

        /*
            DÜRÜST DURUM. Misafir masada oturuyor ve restoran yerinde
            duruyor; ona "menü bulunamadı" demek YALANDIR — menü var, o saatte
            servis edilmiyor. Boş bir menü göstermek ise daha kötüsüdür:
            restoranın menüsünü sildiğini sandırır.
        */
        $response->assertStatus(
            200,
            'GUEST-OUT-OF-SERVICE-01: geçerli bir adres, servis dışı saatte çıkmaz sokağa düşmemeli.'
        );
        $response->assertSee('data-guest-state="out-of-service"', false);

        /*
            SONRAKİ SERVİS SAATİ GERÇEK VERİDEN gelir: gece menüsü 02:00'de
            biter ve yayınlanmış ana menü geri gelir. Uydurulmuş bir saat
            yazmaktansa hiç yazmamak gerekir; burada gerçek bir saat VAR.
        */
        $response->assertSee('02:00', false);
        $response->assertDontSee('Adana Kebap', false);
    }

    // --- GUEST-OUT-OF-SERVICE-UNIFORM-404-01 -------------------------------

    public function test_a_location_with_nothing_published_at_all_stays_an_indistinguishable_dead_end(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'guest-nothing-published');
        $key = $this->newPublicKey();
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $key, 0);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);

        $path = $this->addressFor($key)->path();

        $this->nowAt('12:00');

        /*
            QR-PUBLIC-404-UNIFORM-01 KORUNUR. Dürüst "servis dışı" sayfası
            yalnız ZATEN 200 dönen bir adres için açılır: çıpa menüsü
            yayınlanmışsa o adres başka bir saatte nasılsa sayfa gösteriyor,
            dolayısıyla bu sayfa saldırgana yeni bir bilgi vermez.

            Hiç yayını olmayan bir adres ise bugün olduğu gibi bilinmeyen bir
            anahtardan AYIRT EDİLEMEZ kalır; ayırt edilebilseydi, hangi
            anahtarların var olduğu ölçülebilir olurdu.
        */
        $this->get($path)->assertStatus(404);
        $this->get('/restoran/menu/'.$this->newPublicKey())->assertStatus(404);
    }

    // --- GUEST-ITEM-SERVING-01 ---------------------------------------------

    public function test_a_dish_of_the_currently_served_menu_opens_instead_of_a_dead_end(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'guest-item-serving');
        $key = $this->newPublicKey();
        $mainId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $key, 0);
        $breakfastId = $this->insertMenu($workspaceId, $locationId, 'Kahvaltı', null, 1);

        $this->publishMenu($workspaceId, $locationId, $mainId, (int) $owner->id, 'Ana yemekler', [
            ['menuItemId' => 101, 'productName' => 'Adana Kebap', 'priceMinorAmount' => 32000, 'currencyCode' => 'TRY'],
        ]);
        $this->publishMenu($workspaceId, $locationId, $breakfastId, (int) $owner->id, 'Kahvaltılıklar', [
            ['menuItemId' => 202, 'productName' => 'Menemen', 'priceMinorAmount' => 18000, 'currencyCode' => 'TRY'],
        ]);

        $this->schedule()->setServiceWindow($workspaceId, $mainId, 0, 0);
        $this->schedule()->setServiceWindow($workspaceId, $breakfastId, 7 * 60, 11 * 60);

        $itemPath = $this->addressFor($key)->itemPath(202, 'Menemen');

        $this->nowAt('08:00');

        /*
            Ürün sayfası menü sayfasının BAĞLANTI HEDEFİDİR. Menü sayfası
            saate göre kahvaltıyı gösterip ürün sayfası çıpa menüsüne baksaydı,
            misafirin kendi ekranındaki her bağlantı çıkmaz sokağa giderdi —
            ve bu yalnız kahvaltı saatinde olurdu, yani sahibi hiç görmezdi.
        */
        $this->get($itemPath)->assertStatus(
            200,
            'GUEST-ITEM-SERVING-01: servis edilen menünün ürünü açılmalı.'
        );
    }

    /*
    |---------------------------------------------------------------------------
    | ŞUBE KAPALIYKEN (FF-141)
    |---------------------------------------------------------------------------
    |
    | Yukarıdaki FF-139 testleri "o saatte servis edilecek menünün yayını YOK"
    | hâlini dondurur. Buradakiler BAŞKA bir soruyu dondurur: menü var, yayını
    | var, çiziliyor — ama şube o anda KAPALI.
    |
    | İKİSİ TEK DURUMA İNDİRİLMEZ. Servis dışı sayfası menüyü hiç çizmez,
    | çünkü gösterilecek bir menü yoktur. Kapalı şubede ise menü vardır ve
    | GİZLENMEZ: gece 23:00'te karekodu okutan misafir çoğu zaman yarını
    | planlıyordur ve menüyü saklamak ona hizmet etmez. Doğru davranış menünün
    | üstüne dürüst bir şerit koymaktır.
    |
    | Testler METNE değil, makineye okunur duruma bakar (`data-guest-state`,
    | `data-next-opening`): şeridin cümlesi katalogda yaşıyor ve derlenmiş
    | çeviri dosyaları bu paketin dışında üretiliyor. Durumun çizilip
    | çizilmediği, cümlenin hangi dilde olduğundan bağımsız doğrulanabilmeli.
    */

    /**
     * Şubenin haftasını DOĞRUDAN yazar.
     *
     * Panel ucundan geçmemenin sebebi öncülün kendisi: burada test edilen şey
     * yazma yolu değil, misafirin o veriyle ne gördüğüdür. Yazma yolu
     * `LocationOpeningHoursTest` içinde ayrıca dondurulmuş durumda.
     *
     * @param  list<array{day:int,closed:bool,opens?:int|null,closes?:int|null}>  $days
     */
    private function insertOpeningHours(int $workspaceId, int $locationId, array $days): void
    {
        foreach ($days as $day) {
            DB::table('location_opening_hours')->insert([
                'workspace_id' => $workspaceId,
                'location_id' => $locationId,
                'day_of_week' => $day['day'],
                'is_closed' => $day['closed'],
                'opens_minute' => $day['opens'] ?? null,
                'closes_minute' => $day['closes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Yedi günü aynı aralığa açan hafta.
     *
     * @return list<array{day:int,closed:bool,opens:int,closes:int}>
     */
    private function uniformWeek(int $opensMinute, int $closesMinute): array
    {
        return array_map(
            static fn (int $day): array => [
                'day' => $day,
                'closed' => false,
                'opens' => $opensMinute,
                'closes' => $closesMinute,
            ],
            range(1, 7),
        );
    }

    /**
     * Tek menülü, yayınlanmış bir şube kurar ve menünün genel adresini döner.
     *
     * @return array{0:int,1:int,2:string} [workspaceId, locationId, path]
     */
    private function publishedLocation(string $slugSeed, string $timezone = 'Europe/Istanbul'): array
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, $slugSeed, $timezone);
        $key = $this->newPublicKey();
        $menuId = $this->insertMenu($workspaceId, $locationId, 'Ana menü', $key, 0);

        $this->publishMenu($workspaceId, $locationId, $menuId, (int) $owner->id, 'Ana yemekler', [
            ['menuItemId' => 101, 'productName' => 'Adana Kebap', 'priceMinorAmount' => 32000, 'currencyCode' => 'TRY'],
        ]);

        return [$workspaceId, $locationId, $this->addressFor($key)->path()];
    }

    // --- GUEST-CLOSED-01 ---------------------------------------------------

    public function test_a_guest_who_arrives_while_the_location_is_closed_still_gets_the_menu_with_an_honest_banner(): void
    {
        [$workspaceId, $locationId, $path] = $this->publishedLocation('guest-closed');
        $this->insertOpeningHours($workspaceId, $locationId, $this->uniformWeek(9 * 60, 23 * 60));

        $this->nowAt('23:30');
        $response = $this->get($path);

        /*
            MENÜ GİZLENMEZ. Gece yarısına yarım saat kala karekodu okutan
            misafir çoğu zaman yarını planlıyor; ona kapıyı kapatmak, elinde
            olan bilgiyi saklamak olurdu. Ürün ne biliyorsa onu gösterir ve
            bilmediğini değil, BİLDİĞİNİ söyler: "şu anda kapalıyız".
        */
        $response->assertStatus(200, 'GUEST-CLOSED-01: kapalı şube 404 değildir.');
        $response->assertSee('Adana Kebap', false);
        $response->assertSee('data-guest-state="closed"', false);

        /*
            SONRAKİ AÇILIŞ GERÇEK VERİDEN gelir: hafta 09:00'da açılıyor ve
            bugünün açılışı geçtiği için sıradaki açılış yarınınkidir — saat
            yine 09:00. Uydurulmuş bir saat ya da "0" yazmaktansa hiç
            yazmamak gerekirdi; burada gerçek bir saat VAR.
        */
        $response->assertSee('data-next-opening="09:00"', false);
    }

    // --- GUEST-CLOSED-OPEN-NOW-01 ------------------------------------------

    public function test_no_banner_is_drawn_at_all_while_the_location_is_open(): void
    {
        [$workspaceId, $locationId, $path] = $this->publishedLocation('guest-open-now');
        $this->insertOpeningHours($workspaceId, $locationId, $this->uniformWeek(9 * 60, 23 * 60));

        $this->nowAt('12:00');
        $response = $this->get($path);

        $response->assertStatus(200);
        $response->assertSee('Adana Kebap', false);

        /*
            AÇIKKEN ŞERİT HİÇ ÇİZİLMEZ — boş bir kutu bile değil. Boş bir
            kap bırakmak sayfanın üstünde sebepsiz bir boşluk açar ve ekran
            okuyucuya duyurulacak boş bir `status` bölgesi bırakırdı.
        */
        $response->assertDontSee('data-guest-state="closed"', false);
    }

    // --- GUEST-CLOSED-NO-HOURS-01 ------------------------------------------

    public function test_a_location_that_never_entered_its_hours_keeps_working_and_shows_no_banner(): void
    {
        [, , $path] = $this->publishedLocation('guest-no-hours');

        $this->nowAt('03:00');
        $response = $this->get($path);

        /*
            YOKLUK SESSİZDİR. Bugün çalışan şubelerin çoğunun saati hiç
            girilmemiş durumda; onlara varsayılan bir hafta uydurup gece
            03:00'te "kapalıyız" dedirtmek, sahibin hiç söylemediği bir
            iddiayı ekranda doğruymuş gibi göstermek olurdu.
        */
        $response->assertStatus(200, 'GUEST-CLOSED-NO-HOURS-01: saati girilmemiş şube bozulmamalı.');
        $response->assertSee('Adana Kebap', false);
        $response->assertDontSee('data-guest-state="closed"', false);
    }

    // --- GUEST-CLOSED-TIMEZONE-01 ------------------------------------------

    public function test_the_banner_is_decided_in_the_locations_own_timezone(): void
    {
        [$workspaceId, $locationId, $path] = $this->publishedLocation('guest-closed-tz', 'Europe/Berlin');
        $this->insertOpeningHours($workspaceId, $locationId, $this->uniformWeek(9 * 60, 23 * 60));

        // Berlin'de 22:30 — HÂLÂ AÇIK. Aynı an İstanbul'da 23:30'dur; sunucunun
        // ya da sabit bir saat diliminin saatine bakılsaydı Berlin'deki misafir
        // yarım saat erken "kapalıyız" görürdü.
        Carbon::setTestNow(Carbon::parse('2026-09-05 22:30', 'Europe/Berlin'));
        $open = $this->get($path);
        $open->assertStatus(200);
        $open->assertDontSee('data-guest-state="closed"', false);

        // Berlin'de 08:30 — HENÜZ AÇILMADI. İstanbul'da 09:30, yani sabit saat
        // dilimiyle bu misafire yanlışlıkla "açığız" denirdi.
        Carbon::setTestNow(Carbon::parse('2026-09-05 08:30', 'Europe/Berlin'));
        $closed = $this->get($path);
        $closed->assertStatus(200);
        $closed->assertSee('data-guest-state="closed"', false);
        // Açılış BUGÜNDÜR ve yarım saat sonradır.
        $closed->assertSee('data-next-opening="09:00"', false);
    }

    // --- GUEST-CLOSED-MIDNIGHT-01 ------------------------------------------

    public function test_a_kitchen_that_stays_open_past_midnight_is_not_called_closed(): void
    {
        [$workspaceId, $locationId, $path] = $this->publishedLocation('guest-closed-midnight');
        // 18:00–02:00 → 1080 → 1560. Kapanış 1440'ı AŞAR ve bu bir istisna
        // değil, ölçünün doğal devamıdır (`location_opening_hours` göçü).
        $this->insertOpeningHours($workspaceId, $locationId, $this->uniformWeek(18 * 60, 26 * 60));

        /*
            Gece 01:00: mekân DOLU ve mutfak çalışıyor. Yalnız BUGÜNÜN
            satırına bakan bir kontrol ("01:00, 18:00'den önce") tam da en
            yoğun saatte misafire "kapalıyız" derdi — hem de karşısında
            duran garsona rağmen. Doğru cevap DÜNÜN aralığındadır.
        */
        Carbon::setTestNow(Carbon::parse('2026-09-05 01:00', 'Europe/Istanbul'));
        $night = $this->get($path);
        $night->assertStatus(200);
        $night->assertDontSee('data-guest-state="closed"', false);

        // Öğlen 10:00: gerçekten kapalı ve açılış BUGÜN 18:00'dedir.
        $this->nowAt('10:00');
        $morning = $this->get($path);
        $morning->assertSee('data-guest-state="closed"', false);
        $morning->assertSee('data-next-opening="18:00"', false);
    }

    // --- GUEST-CLOSED-NO-CLOCK-01 ------------------------------------------

    public function test_a_week_with_no_open_day_says_closed_without_inventing_a_clock(): void
    {
        [$workspaceId, $locationId, $path] = $this->publishedLocation('guest-closed-forever');
        $this->insertOpeningHours($workspaceId, $locationId, array_map(
            static fn (int $day): array => ['day' => $day, 'closed' => true],
            range(1, 7),
        ));

        $this->nowAt('12:00');
        $response = $this->get($path);

        $response->assertStatus(200);
        $response->assertSee('data-guest-state="closed"', false);

        /*
            AÇILIŞ SAATİ YOKSA CÜMLE HİÇ KURULMAZ. Yedi günü de kapalı olan
            bir şubenin bir sonraki açılışı veriden ÇIKMAZ; "0" ya da tahmini
            bir gün adı yazmak, tutulmayacak bir söz vermek olurdu.
        */
        $response->assertDontSee('data-next-opening', false);
    }
}
