<?php

declare(strict_types=1);

namespace Tests\Feature\Rating;

use App\Domain\Rating\RatingAlgorithm;
use App\Domain\Rating\RatingSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Rating\Concerns\BuildsRatingFixture;
use Tests\TestCase;

/**
 * MİSAFİRİN OY VERMESİ — `docs/116` §4 (P4).
 *
 * ═══ NEDEN KAREKOD BAĞLAMI ŞART ═══
 *
 * `docs/116` §4: *"Oy vermek için o masadan karekod okutmuş olmak
 * gerekir."* Bu, ürünün elindeki en güçlü sinyaldir ve rakip bir platformun
 * sahip olmadığı şeydir: oy veren kişi GERÇEKTEN ORADAYDI. Algoritma
 * dosyası masadan gelen oya en yüksek ağırlığı tam da bu yüzden veriyor —
 * bağlam doğrulanmazsa o ağırlık farkının gerekçesi bir pazarlama cümlesine
 * dönüşür.
 *
 * ═══ AÇIK SORUNUN CEVABI: MİSAFİR FİKRİNİ DEĞİŞTİRİRSE ═══
 *
 * P1 göçü bu soruyu BİLEREK açık bıraktı ve benzersizlik kısıtını koymadı.
 * Bu paketin cevabı: **yeni bir satır yazılır, eskisi `superseded` olarak
 * işaretlenir.**
 *
 * Gerekçe üç katlı:
 *
 * 1. `rating_signals` DEĞİŞMEZDİR. "Fikrimi değiştirdim"i eski satırı
 *    güncelleyerek anlatmak, defterin değişmezliğini ilk gerçek kullanımda
 *    bozmak olurdu. Değişmez bir defterde bu cümlenin tek karşılığı yeni
 *    bir satırdır.
 *
 * 2. İKİSİNİ DE SAYMAK OY ÇOKLAMAKTIR. "Ziyaretçi + ürün başına tek oy"
 *    kuralı SAYILAN küme üzerinde tutulur: her an en fazla bir sinyal
 *    ağırlıklandırmaya girer. Kural satır sayısı üzerinde değil, ağırlık
 *    üzerinde tanımlıdır — çünkü ölçülen şey ağırlıktır.
 *
 * 3. ESKİYİ SİLMEK KANITI SİLERDİ. Bir ziyaretçinin bir akşamda puanını
 *    dört kez çevirmesi kendi başına bir kötüye kullanım işaretidir; eski
 *    satır silinseydi o örüntü hiçbir yerde görünmezdi.
 *
 * `superseded` sebebinin `duplicate_visitor` ile BİRLEŞTİRİLMEMESİ de aynı
 * gerekçenin devamı: fikrini değiştiren misafir bir kötüye kullanıcı
 * değildir ve ikisini tek sebep altında toplamak, "kaç oyu neden eledik?"
 * sorusunun cevabını kalıcı olarak yanlış yapardı.
 *
 * Requirement ID'leri: RATING-GUEST-SCAN-01, RATING-GUEST-CHANGED-MIND-02,
 * RATING-GUEST-BURST-03, RATING-GUEST-NEVER-DELETED-04,
 * RATING-GUEST-RANGE-05, RATING-GUEST-ITEM-06.
 */
final class GuestRatingSubmissionTest extends TestCase
{
    use BuildsRatingFixture;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $scene = [];

    private function ratePath(?string $token = null): string
    {
        return '/q/'.($token ?? $this->scene['token']).'/ratings';
    }

    /** @return list<object> */
    private function signalsFor(int $subjectId): array
    {
        return DB::table('rating_signals')
            ->where('subject_id', $subjectId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    // --- RATING-GUEST-SCAN-01 ---------------------------------------------

    public function test_a_vote_from_a_table_is_recorded_with_the_proof_that_it_came_from_there(): void
    {
        /*
            SİNYAL KANITINI YANINDA TAŞIR.

            Karekod ve masa kimliği satıra yazılmasaydı, "bu oy masadan
            geldi" iddiası sonradan doğrulanamazdı — ve doğrulanamayan bir
            iddiaya ağırlık vermek, ölçmediğimiz bir şeye güvenmektir.
        */
        $this->scene = $this->ratingScene('oy-masa');

        $response = $this->postJson($this->ratePath(), [
            'menuItemId' => $this->scene['menuItems']['Kahve'],
            'score' => 4,
        ]);

        $response->assertStatus(201);

        $signals = $this->signalsFor($this->scene['products']['Kahve']);

        self::assertCount(1, $signals, 'RATING-GUEST-SCAN-01: masadan verilen oy deftere yazılmalı.');

        $signal = $signals[0];

        self::assertSame(RatingSource::GuestScan->value, $signal->source);
        self::assertSame('product', $signal->subject_type, 'RATING-GUEST-SCAN-01: puan MENÜ SATIRINA değil ÜRÜNE verilir; menü satırı yarın silinse de ürünün puanı yaşar.');
        self::assertSame(4, (int) $signal->score_value);
        self::assertSame(RatingAlgorithm::current()->scaleMax, (int) $signal->score_scale_max, 'RATING-GUEST-SCAN-01: ölçek sinyalin yanında yaşamalı; ölçeksiz bir puan birimsiz bir sayıdır.');
        self::assertSame($this->scene['qrCodeId'], (int) $signal->qr_code_id);
        self::assertSame($this->scene['tableId'], (int) $signal->dining_table_id);
        self::assertNotNull($signal->visitor_key);
        self::assertNull($signal->excluded_at);
    }

    public function test_a_qr_code_that_is_not_bound_to_a_table_cannot_carry_a_vote(): void
    {
        /*
            AFİŞ, KARTVİZİT VE GİRİŞ KODU BİR MASA DEĞİLDİR.

            Bu kodları okutan kişi restoranda olabilir de olmayabilir de;
            vitrindeki bir afişi sokaktan geçerken okutmuş olabilir. Oyu
            kabul edip "masadan geldi" diye saymak, ağırlık farkının tek
            dayanağını çürütürdü. Ret DÜRÜSTTÜR: sebebi söyler.
        */
        $this->scene = $this->ratingScene('oy-masasiz');

        DB::table('qr_codes')->where('id', $this->scene['qrCodeId'])->update(['dining_table_id' => null]);

        $response = $this->postJson($this->ratePath(), [
            'menuItemId' => $this->scene['menuItems']['Kahve'],
            'score' => 5,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('reason', 'table_unknown');

        self::assertSame(
            0,
            DB::table('rating_signals')->count(),
            'RATING-GUEST-SCAN-01: masası olmayan bir koddan gelen oy deftere HİÇ girmemeli.'
        );
    }

    public function test_an_unknown_token_never_says_whether_it_ever_existed(): void
    {
        // Karekod yüzeyinin tek tip çıkmaz sokağı (QR-PUBLIC-404-UNIFORM-01):
        // ayrı cevap vermek, deneyerek "bu token vardı ama kapatıldı"
        // bilgisini ölçmeye izin verirdi.
        $this->postJson($this->ratePath(Str::random(43)), ['menuItemId' => 1, 'score' => 5])
            ->assertStatus(404);
    }

    // --- RATING-GUEST-CHANGED-MIND-02 -------------------------------------

    public function test_a_guest_who_changes_their_mind_gets_a_new_row_and_the_old_one_stops_counting(): void
    {
        /*
            AÇIK SORUNUN CEVABI — sınıf yorumundaki üç gerekçe burada
            ÖLÇÜLÜYOR, anlatılmıyor:

            - iki satır VAR (değişmez defter),
            - yalnız BİRİ sayılıyor (tek oy kuralı ağırlık üzerinde),
            - eskisi SİLİNMEDİ ve neden sayılmadığını KENDİSİ söylüyor.
        */
        $this->scene = $this->ratingScene('oy-fikir');
        $menuItemId = $this->scene['menuItems']['Kahve'];

        $this->postJson($this->ratePath(), ['menuItemId' => $menuItemId, 'score' => 2])->assertStatus(201);
        $this->postJson($this->ratePath(), ['menuItemId' => $menuItemId, 'score' => 5])->assertStatus(201);

        $signals = $this->signalsFor($this->scene['products']['Kahve']);

        self::assertCount(2, $signals, 'RATING-GUEST-CHANGED-MIND-02: değişmez defterde fikir değişikliğinin tek karşılığı YENİ BİR SATIRDIR.');

        [$first, $second] = $signals;

        self::assertSame(2, (int) $first->score_value);
        self::assertNotNull($first->excluded_at, 'RATING-GUEST-CHANGED-MIND-02: eski oy artık sayılmamalı — yoksa tek ziyaretçi iki oy vermiş olurdu.');
        self::assertSame('superseded', $first->exclusion_reason);

        self::assertSame(5, (int) $second->score_value);
        self::assertNull($second->excluded_at, 'RATING-GUEST-CHANGED-MIND-02: misafirin BUGÜNKÜ fikri sayılan fikirdir.');

        // Sayılan küme üzerinde "ziyaretçi + ürün başına tek oy": kural
        // satır sayısında değil AĞIRLIKTA tutulur.
        self::assertSame(
            1,
            DB::table('rating_signals')
                ->where('subject_id', $this->scene['products']['Kahve'])
                ->whereNull('excluded_at')
                ->count(),
            'RATING-GUEST-CHANGED-MIND-02: bir ziyaretçinin bir üründe SAYILAN tek bir oyu olabilir.'
        );
    }

    public function test_the_reason_for_a_changed_mind_is_not_the_reason_for_abuse(): void
    {
        /*
            `superseded` ile `duplicate_visitor` AYRI SEBEPLERDİR.

            Fikrini değiştiren misafir bir kötüye kullanıcı değildir. İkisini
            tek sebep altında toplasaydık, altı ay sonra "kaç oyu kötüye
            kullanım diye eledik?" sorusunun cevabı kalıcı olarak yanlış
            olurdu — ve o yanlış, algoritmanın kendisini ayarlarken
            kullanılırdı.

            Sebep sözlüğü ALGORİTMA DOSYASINDA yaşar; kodda ikinci bir liste
            tutmak, iki listenin bir gün ayrışması demekti.
        */
        $reasons = RatingAlgorithm::current()->abuseRules()->exclusionReasons();

        self::assertContains('superseded', $reasons);
        self::assertContains('duplicate_visitor', $reasons);
        self::assertNotSame('duplicate_visitor', 'superseded');
    }

    // --- RATING-GUEST-BURST-03 --------------------------------------------

    public function test_a_burst_from_one_table_is_marked_and_not_deleted(): void
    {
        /*
            ANİ YIĞILMA — `docs/116` §4 ve algoritma dosyasındaki
            `burst_window_minutes` / `burst_max_signals_per_table`.

            Sınırı aşan oy REDDEDİLMEZ. İki sebepten:

            1. Sekiz kişilik bir masa on beş dakikada dokuz tabak
               puanlayabilir ve hiçbiri kötü niyetli değildir. Reddetmek
               dürüst misafiri cezalandırırdı.
            2. İşaret GERİ ALINABİLİR, ret alınamaz. Satır defterde
               durduğu sürece yanlış işaretleme bir gün düzeltilebilir;
               reddedilen bir oy geri gelmez.

            Ölçülen şey CEVAP DEĞİL, DEFTERİN HÂLİDİR: misafir aynı cevabı
            alır, sinyal ağırlıklandırmanın dışında kalır.
        */
        $this->scene = $this->ratingScene('oy-yigilma', ['Kahve', 'Çay', 'Salep', 'Ayran', 'Limonata', 'Şalgam', 'Boza']);

        $abuse = RatingAlgorithm::current()->abuseRules();
        $limit = $abuse->burstMaxSignalsPerTable();

        $names = array_keys($this->scene['menuItems']);

        self::assertGreaterThan(
            $limit,
            count($names),
            'RATING-GUEST-BURST-03: sahne, tavanı gerçekten aşacak kadar ürün taşımalı.'
        );

        foreach ($names as $index => $name) {
            $response = $this->postJson($this->ratePath(), [
                'menuItemId' => $this->scene['menuItems'][$name],
                'score' => 5,
            ]);

            $response->assertStatus(201);

            $signal = DB::table('rating_signals')
                ->where('subject_id', $this->scene['products'][$name])
                ->first();

            self::assertNotNull($signal, "RATING-GUEST-BURST-03: {$name} için sinyal deftere yazılmalı — yığılma bile satır SİLDİRMEZ.");

            if ($index < $limit) {
                self::assertNull($signal->excluded_at, "RATING-GUEST-BURST-03: tavan altındaki {$name} oyu sayılmalı.");

                continue;
            }

            self::assertNotNull($signal->excluded_at, "RATING-GUEST-BURST-03: tavanı aşan {$name} oyu ağırlıklandırma dışında kalmalı.");
            self::assertSame('burst_detected', $signal->exclusion_reason);
        }
    }

    public function test_a_supersession_does_not_count_towards_the_burst_ceiling(): void
    {
        /*
            FİKRİNİ DEĞİŞTİRMEK BİR YIĞILMA DEĞİLDİR.

            İşaretlenmiş satırlar yığılma sayımına girseydi, kararsız bir
            misafir kendi masasını "kampanya" gibi gösterir ve masadaki
            herkesin oyunu ağırlıklandırma dışına attırırdı.
        */
        $this->scene = $this->ratingScene('oy-yigilma-degil', ['Kahve']);

        $menuItemId = $this->scene['menuItems']['Kahve'];
        $limit = RatingAlgorithm::current()->abuseRules()->burstMaxSignalsPerTable();

        for ($i = 0; $i <= $limit + 1; $i++) {
            $this->postJson($this->ratePath(), ['menuItemId' => $menuItemId, 'score' => 3])->assertStatus(201);
        }

        $counted = DB::table('rating_signals')
            ->where('subject_id', $this->scene['products']['Kahve'])
            ->whereNull('excluded_at')
            ->first();

        self::assertNotNull($counted, 'RATING-GUEST-BURST-03: aynı ürüne verilen son oy hâlâ sayılmalı.');
        self::assertSame(3, (int) $counted->score_value);
    }

    // --- RATING-GUEST-NEVER-DELETED-04 ------------------------------------

    public function test_nothing_on_the_guest_path_ever_removes_a_signal(): void
    {
        /*
            §4 — KÖTÜYE KULLANIM SİLMEZ, İŞARETLER.

            "Bu oyu neden saymadık?" sorusunun cevabı, o oyun KENDİSİDİR.
            Silmek, yanlış işaretlemenin geri dönüşünü de silerdi.
        */
        $this->scene = $this->ratingScene('oy-silinmez', ['Kahve', 'Çay', 'Salep', 'Ayran', 'Limonata', 'Şalgam', 'Boza']);

        foreach (array_keys($this->scene['menuItems']) as $name) {
            $this->postJson($this->ratePath(), ['menuItemId' => $this->scene['menuItems'][$name], 'score' => 5]);
        }

        // Aynı ürüne ikinci oy: bir satır işaretlenir, hiçbiri kaybolmaz.
        $this->postJson($this->ratePath(), ['menuItemId' => $this->scene['menuItems']['Kahve'], 'score' => 1]);

        self::assertSame(
            8,
            DB::table('rating_signals')->count(),
            'RATING-GUEST-NEVER-DELETED-04: yedi oy + bir fikir değişikliği = sekiz satır; hiçbiri silinmez.'
        );
    }

    // --- RATING-GUEST-RANGE-05 --------------------------------------------

    public function test_a_score_outside_the_scale_is_refused_instead_of_being_clamped(): void
    {
        /*
            KIRPMA YASAK. 9 puanı sessizce 5'e çekmek, misafirin vermediği
            bir oyu ona atfetmek olurdu — ve defter değişmez olduğu için o
            uydurma sonsuza kadar kalırdı. Ölçek algoritma dosyasından
            okunur; ekrana ya da uca gömülmez.
        */
        $this->scene = $this->ratingScene('oy-olcek');
        $menuItemId = $this->scene['menuItems']['Kahve'];
        $scaleMax = RatingAlgorithm::current()->scaleMax;

        foreach ([0, -1, $scaleMax + 1] as $score) {
            $response = $this->postJson($this->ratePath(), ['menuItemId' => $menuItemId, 'score' => $score]);

            $response->assertStatus(422);
            $response->assertJsonPath('reason', 'score_out_of_range');
        }

        self::assertSame(0, DB::table('rating_signals')->count());
    }

    // --- RATING-GUEST-ITEM-06 ---------------------------------------------

    public function test_a_dish_that_is_not_on_this_published_menu_cannot_be_rated(): void
    {
        /*
            KİMLİK GÖVDEDEN GELİYOR, DOLAYISIYLA DOĞRULANIR.

            Doğrulanmasaydı, bir masadan okutulan karekodla BAŞKA BİR
            RESTORANIN ürününe oy verilebilirdi: gövdeye yabancı bir
            `menuItemId` yazmak yeterdi. Sipariş ucunda aynı kural yazılı
            ve aynı sebeple.
        */
        $this->scene = $this->ratingScene('oy-yabanci');
        $other = $this->ratingScene('oy-yabanci-komsu');

        $response = $this->postJson($this->ratePath(), [
            'menuItemId' => $other['menuItems']['Kahve'],
            'score' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('reason', 'item_unavailable');

        self::assertSame(0, DB::table('rating_signals')->count());
    }
}
