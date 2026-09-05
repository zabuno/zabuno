<?php

declare(strict_types=1);

namespace Tests\Feature\Rating;

use App\Domain\Rating\RatingAlgorithm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Rating\Concerns\BuildsRatingFixture;
use Tests\TestCase;

/**
 * GÖSTERİM EŞİĞİ — `docs/116` §3 (P5), `docs/114`'ten devralındı.
 *
 * ═══ SIFIR BİR ÖLÇÜMDÜR VE BİLİNMEYENİN YERİNE GEÇEMEZ ═══
 *
 * Eşiğin altında ekran "henüz yeterli değerlendirme yok" der. Sıfır yıldız
 * yazmak, hiç oy almamış bir ürünü KÖTÜ ürün gibi göstermektir — ve bu
 * yanlış, sahibin menüsündeki en yeni ürünü sürekli en aşağıda tutardı.
 *
 * Üç kişinin verdiği beş yıldız da bir bilgi değildir: gösterilirse YENİ
 * ÜRÜN HER ZAMAN EN İYİ GÖRÜNÜR.
 *
 * ═══ EŞİK EKRANA GÖMÜLMEZ ═══
 *
 * Karar `rating_scores.meets_display_threshold` sütununda, o sütunu yazan
 * kural ise algoritma dosyasındadır. Ekran KARARI okur, sayıyı değil. İki
 * yerde yaşayan bir eşik bir gün iki farklı cevap verir — ve o gün sahip,
 * misafirin gördüğü puanla panelde gördüğü puanın neden farklı olduğunu
 * hiçbir yerde bulamaz.
 *
 * Bu testin en sert iddiası bu: `signal_count` YÜKSEK ama karar OLUMSUZ
 * olan bir satırda ekran yine "yeterli değerlendirme yok" der. Ekran sayıyı
 * kendisi yorumlasaydı burada puanı çizerdi.
 *
 * Requirement ID'leri: RATING-DISPLAY-BELOW-01, RATING-DISPLAY-ABOVE-02,
 * RATING-DISPLAY-NOT-ZERO-03, RATING-DISPLAY-PANEL-04,
 * RATING-DISPLAY-DECIDED-BY-FILE-05.
 */
final class RatingDisplayThresholdTest extends TestCase
{
    use BuildsRatingFixture;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $scene = [];

    /** Puanlı sayfanın TELDEKİ tavanı — bayt (bkz. ölçüm tablosu). */
    private const MAX_RATING_PAGE_GZIP_BYTES = 25000;

    /**
     * Puanlı sayfanın satır içi betik tavanı — bayt.
     *
     * 2026-09-06'DA 26 000 → 30 000 (`docs/122` Y5).
     *
     * SEBEP PUANLAMA DEĞİL: favori işaretlemesi TABAN misafir yüzeyine girdi
     * ve puanlı sayfa da o tabanın üstünde duruyor. Puanlamanın kendi betiği
     * tek bayt büyümedi; ölçülen fark 24 013 → 28 222 bayt ve tamamı favori
     * bloğu (gerekçe yorumları dâhil).
     *
     * TELDEKİ TAVAN (`MAX_RATING_PAGE_GZIP_BYTES`) DEĞİŞMEDİ ve hâlâ
     * tutuyor: kırk satırlık bu sahnede favori düğmeleri birbirinin
     * neredeyse aynısı olduğu için sıkıştırmadan sonra bedeli küçük kalıyor.
     * Değişen tavan yalnız sıkıştırılmamış betik ölçüsüdür.
     *
     * Kütüphane yine eklenmedi: kalp de yıldız gibi sayfada bir kez
     * `<symbol>` olarak duruyor ve düğmeler ona `<use>` ile bakıyor.
     */
    private const MAX_RATING_SCRIPT_BYTES = 30000;

    private function guestHtml(): string
    {
        return (string) $this->withHeaders(['Accept' => 'text/html'])
            ->get('/menu/'.$this->scene['token'])
            ->getContent();
    }

    /**
     * @param  array<string, mixed>  $scene
     * @return array<string, mixed>
     */
    private function panelPayload(array $scene): array
    {
        $response = $this->actingAs($scene['owner'])->getJson(
            '/api/workspaces/'.$scene['workspaceId'].'/menus/'.$scene['menuId'].'/ratings'
        );

        $response->assertStatus(200);

        return $response->json();
    }

    /**
     * Bir ürünün panel satırı.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function panelRow(array $payload, int $menuItemId): array
    {
        foreach ($payload['data'] as $row) {
            if ((int) $row['menuItemId'] === $menuItemId) {
                return $row;
            }
        }

        self::fail('Panel yanıtında '.$menuItemId.' satırı yok.');
    }

    // --- RATING-DISPLAY-BELOW-01 / RATING-DISPLAY-NOT-ZERO-03 -------------

    public function test_below_the_threshold_the_guest_menu_says_there_is_not_enough_yet_and_never_draws_a_number(): void
    {
        $this->scene = $this->ratingScene('esik-alti', ['Kahve']);

        // Üç kişinin verdiği beş yıldız: karar OLUMSUZ.
        $this->storeRatingScore(
            $this->scene['workspaceId'],
            $this->scene['products']['Kahve'],
            5.0,
            3,
            3.0,
            false,
        );

        $html = $this->guestHtml();

        self::assertStringContainsString(
            'data-rating-unknown',
            $html,
            'RATING-DISPLAY-BELOW-01: eşik altında ekran bilinmezliği SÖYLEMELİ; boş bir yer bir cümle değildir.'
        );

        self::assertStringNotContainsString(
            'data-rating-score',
            $html,
            'RATING-DISPLAY-BELOW-01: eşik altında puan çizilmez — üç kişinin verdiği beş yıldız bir bilgi değildir.'
        );

        /*
            SIFIR YAZILMAZ. Bu iddia ayrı duruyor çünkü en kolay yapılan
            yanlış budur: veri yoksa `0` basmak. Sıfır bir ÖLÇÜMDÜR ve
            "bilmiyoruz"un yerine geçemez.
        */
        preg_match('#<li[^>]*id="item-'.$this->scene['menuItems']['Kahve'].'".*?</li>#s', $html, $row);

        self::assertNotEmpty($row);
        self::assertDoesNotMatchRegularExpression(
            '/data-rating-score="0(?:[.,]0*)?"/',
            $row[0],
            'RATING-DISPLAY-NOT-ZERO-03: bilinmeyen puanın yerine sıfır konamaz.'
        );
    }

    // --- RATING-DISPLAY-ABOVE-02 ------------------------------------------

    public function test_above_the_threshold_the_guest_menu_draws_the_score(): void
    {
        $this->scene = $this->ratingScene('esik-ustu', ['Kahve']);

        $this->storeRatingScore(
            $this->scene['workspaceId'],
            $this->scene['products']['Kahve'],
            4.3,
            12,
            9.5,
            true,
        );

        $html = $this->guestHtml();

        self::assertStringContainsString(
            'data-rating-score',
            $html,
            'RATING-DISPLAY-ABOVE-02: eşiği geçen ürünün puanı gösterilmeli.'
        );

        self::assertMatchesRegularExpression(
            '/data-rating-score="4[.,]3"/',
            $html,
            'RATING-DISPLAY-ABOVE-02: gösterilen sayı hesaplanan sayı olmalı.'
        );
    }

    public function test_a_score_written_by_another_algorithm_version_is_not_shown_as_todays_score(): void
    {
        /*
            Ö3 — SÜRÜM DAMGASI GÖSTERİMDE DE GEÇERLİDİR.

            Yürürlükteki sürüm v1 iken v2 ile hesaplanmış bir satırı çizmek,
            misafire bugün geçerli olmayan bir kuralın çıktısını bugünün
            puanı diye göstermek olurdu. İki sürüm YAN YANA yaşar; ekran
            hangisine baktığını bilmek zorundadır.
        */
        $this->scene = $this->ratingScene('esik-surum', ['Kahve']);

        $this->storeRatingScore(
            $this->scene['workspaceId'],
            $this->scene['products']['Kahve'],
            4.9,
            40,
            30.0,
            true,
            RatingAlgorithm::CURRENT_VERSION + 1,
        );

        $html = $this->guestHtml();

        self::assertStringNotContainsString(
            'data-rating-score',
            $html,
            'RATING-DISPLAY-ABOVE-02: başka bir sürümün puanı bugünün puanı gibi çizilemez.'
        );
        self::assertStringContainsString('data-rating-unknown', $html);
    }

    // --- RATING-DISPLAY-DECIDED-BY-FILE-05 --------------------------------

    public function test_the_screen_reads_the_decision_and_never_re_derives_it_from_a_count(): void
    {
        /*
            EN SERT İDDİA.

            `signal_count` eşiğin ÜSTÜNDE, karar ise OLUMSUZ. Bu gerçek bir
            durumdur: sekiz oy vardır ama hepsi üç yıllıktır ve sönüm sonrası
            toplam ağırlık eşiği geçmez (`minimum_weight`). Ekran sayıya
            bakıp kendi kararını verseydi, ölü bir puanı canlıymış gibi
            çizerdi.

            Eşiğin sayısal değeri bu testte HİÇ GEÇMİYOR ve geçmemeli:
            burada sınanan şey eşiğin kaç olduğu değil, ekranın onu
            hesaplamıyor oluşudur.
        */
        $this->scene = $this->ratingScene('esik-karar', ['Kahve']);

        $this->storeRatingScore(
            $this->scene['workspaceId'],
            $this->scene['products']['Kahve'],
            4.8,
            400,
            0.9,
            false,
        );

        $html = $this->guestHtml();

        self::assertStringContainsString(
            'data-rating-unknown',
            $html,
            'RATING-DISPLAY-DECIDED-BY-FILE-05: sinyal sayısı yüksek olsa da KARAR olumsuzsa puan çizilmez.'
        );
        self::assertStringNotContainsString('data-rating-score', $html);

        // Panel de aynı kararı okur; iki yüzey aynı satırdan aynı cevabı alır.
        $row = $this->panelRow($this->panelPayload($this->scene), $this->scene['menuItems']['Kahve']);

        self::assertFalse($row['meetsDisplayThreshold']);
        self::assertNull($row['score'], 'RATING-DISPLAY-PANEL-04: eşik altında panel de sayı vermez.');
        self::assertSame(400, $row['signalCount'], 'RATING-DISPLAY-PANEL-04: sahip KAÇ oy geldiğini görebilmeli — sayının kendisi bir ölçümdür, puan değil.');
    }

    /**
     * Eşiğin sayısı MİSAFİRİN SAYFASINA hiç inmez.
     *
     * İnseydi, istemci bir gün "sayıyı ben de hesaplayabilirim" derdi ve
     * eşik kuralı ikinci bir yerde yaşamaya başlardı.
     */
    public function test_the_threshold_numbers_never_reach_the_guest_page(): void
    {
        $this->scene = $this->ratingScene('esik-sizmaz', ['Kahve']);

        $this->storeRatingScore($this->scene['workspaceId'], $this->scene['products']['Kahve'], 4.3, 12, 9.5, true);

        $html = $this->guestHtml();

        foreach (['minimum_signals', 'minimumSignals', 'minimum_weight', 'minimumWeight', 'totalWeight', 'signalCount'] as $leak) {
            self::assertStringNotContainsString(
                $leak,
                $html,
                "RATING-DISPLAY-DECIDED-BY-FILE-05: `{$leak}` misafirin sayfasına inmemeli; eşik sunucuda kalır."
            );
        }
    }

    // --- RATING-DISPLAY-PANEL-04 ------------------------------------------

    public function test_the_panel_shows_the_score_only_when_the_decision_says_so(): void
    {
        $this->scene = $this->ratingScene('esik-panel', ['Kahve', 'Çay']);

        $this->storeRatingScore($this->scene['workspaceId'], $this->scene['products']['Kahve'], 4.3, 12, 9.5, true);
        // Çay'ın hiç satırı yok: henüz hiç oy almadı.

        $payload = $this->panelPayload($this->scene);

        $coffee = $this->panelRow($payload, $this->scene['menuItems']['Kahve']);
        self::assertTrue($coffee['meetsDisplayThreshold']);
        self::assertSame(4.3, $coffee['score']);
        self::assertSame('Kahve', $coffee['productName']);

        $tea = $this->panelRow($payload, $this->scene['menuItems']['Çay']);
        self::assertFalse($tea['meetsDisplayThreshold'], 'RATING-DISPLAY-PANEL-04: hiç oy almamış ürün eşiği geçmiş sayılamaz.');
        self::assertNull($tea['score'], 'RATING-DISPLAY-PANEL-04: hiç oy yoksa puan da yoktur — sıfır DEĞİL.');
        self::assertSame(0, $tea['signalCount']);

        self::assertSame(
            RatingAlgorithm::CURRENT_VERSION,
            $payload['algorithmVersion'],
            'RATING-DISPLAY-PANEL-04: panel hangi kuralın çıktısına baktığını söylemeli.'
        );
    }

    public function test_the_panel_rating_surface_is_closed_to_another_tenant(): void
    {
        $this->scene = $this->ratingScene('esik-kiraci');
        $neighbour = $this->ratingScene('esik-komsu');

        // Enumeration-safe: komşu "böyle bir menü var ama sana kapalı"yı
        // bile öğrenemez.
        $this->actingAs($neighbour['owner'])
            ->getJson('/api/workspaces/'.$this->scene['workspaceId'].'/menus/'.$this->scene['menuId'].'/ratings')
            ->assertStatus(404);
    }

    // --- RATING-DISPLAY-320-06 --------------------------------------------

    /**
     * EN KÜÇÜK EKRAN İLE EN ZAYIF HAT AYNI MİSAFİRDİR.
     *
     * Bu sayfa masada, çoğu zaman zayıf bir hücresel bağlantıda açılıyor.
     * Puanlama hem işaretleme hem betik istiyor; istenen şey ÖLÇÜLÜR ve bir
     * tavana bağlanır.
     *
     * ÖLÇÜM (2026-09-08, 40 ürünlük menü, aynı sayfa, aynı yöntem):
     *
     * |                          | puansız   | puanlı    |
     * | Sayfanın tamamı, ham     | 104 493 B | 199 514 B |
     * | Sayfanın tamamı, gzip    |  18 967 B |  22 320 B |
     * | Satır içi betik, ham     |  19 8xx B |  24 013 B |
     * | Dış istek sayısı         |         0 |         0 |
     *
     * TELDE ÖDENEN FARK ~3,4 KB'dir ve AĞ İSTEĞİ SAYISI DEĞİŞMEZ. Ham
     * baytın büyük kısmı satır başına beş düğmenin girintisi ve erişilebilir
     * adıdır; sıkıştırmadan sonra bedeli küçüktür çünkü kırk satır birbirinin
     * neredeyse aynısıdır.
     *
     * Puansız sayfa bu pakette BÜYÜMEDİ: işaretleme, stil, sözlük ve betik
     * yalnız masaya bağlı bir karekodun sayfasında iniyor. Afişten menüye
     * bakan misafir, kullanamayacağı bir yeteneğin bedelini ödemiyor.
     *
     * KÜTÜPHANE EKLENMEZ: bir simge paketi bu tavanı tek başına aşar. Yıldız
     * sayfada bir kez `<symbol>` olarak duruyor ve düğmeler ona `<use>` ile
     * bakıyor.
     */
    public function test_the_rating_surface_stays_inside_the_guest_weight_budget(): void
    {
        $names = [];

        for ($i = 0; $i < 40; $i++) {
            $names[] = 'Tabak '.$i;
        }

        $this->scene = $this->ratingScene('esik-butce', $names);

        $html = $this->guestHtml();

        $gzipped = strlen((string) gzencode($html, 6));

        self::assertLessThanOrEqual(
            self::MAX_RATING_PAGE_GZIP_BYTES,
            $gzipped,
            "RATING-DISPLAY-320-06: puanlı sayfa telde {$gzipped} bayt; tavan aşıldı."
        );

        preg_match_all(
            '#<script\b(?![^>]*application/(?:ld\+json|json))[^>]*>(.*?)</script>#is',
            $html,
            $matches,
        );

        $bytes = strlen(implode("\n", $matches[1]));

        self::assertLessThanOrEqual(
            self::MAX_RATING_SCRIPT_BYTES,
            $bytes,
            "RATING-DISPLAY-320-06: puanlı sayfanın satır içi betiği {$bytes} bayt; tavan aşıldı."
        );

        /*
            DIŞ İSTEK YOK: bir simge paketi, bir yıldız kütüphanesi ya da bir
            yazı tipi indirmek masadaki zayıf hatta ödenmez. Kanonik adres
            bir `<link rel="canonical">` olarak duruyor ve o bir İSTEK
            değildir — arama motoruna söylenen bir cümledir.
        */
        self::assertDoesNotMatchRegularExpression(
            '#<script[^>]+src=#i',
            $html,
            'RATING-DISPLAY-320-06: misafir yüzeyine dış betik eklenmez.'
        );

        self::assertDoesNotMatchRegularExpression(
            '#<link[^>]+rel="(?:stylesheet|preload|preconnect)"#i',
            $html,
            'RATING-DISPLAY-320-06: misafir yüzeyine dış stil ya da ön yükleme eklenmez.'
        );
    }

    public function test_every_star_is_a_real_finger_target(): void
    {
        /*
            `docs/48` §1 — 320 px gerçek başlangıç noktasıdır ve dokunma
            hedefi 44 px'tir. Beş yıldız × 44 px = 220 px; kendi satırında
            320'ye rahat sığar.

            ÖLÇÜ ORTAK DEĞİŞKENDEN gelir (`--qr-tap`): sepetin adet
            düğmeleriyle aynı parmak. Sabit bir sayı yazsaydık, ölçü bir gün
            değiştiğinde bu satır geride kalırdı.
        */
        $this->scene = $this->ratingScene('esik-parmak', ['Kahve']);

        preg_match_all('#<style[^>]*>(.*?)</style>#s', $this->guestHtml(), $matches);

        $css = implode("\n", $matches[1]);

        self::assertMatchesRegularExpression(
            '/\.qr-rate-btn\s*\{[^}]*min-height\s*:\s*var\(--qr-tap\)/s',
            $css,
            'RATING-DISPLAY-320-06: yıldız düğmesi ortak dokunma ölçüsünü kullanmalı.'
        );

        self::assertMatchesRegularExpression(
            '/\.qr-rate-btn\s*\{[^}]*min-inline-size\s*:\s*var\(--qr-tap\)/s',
            $css,
            'RATING-DISPLAY-320-06: yıldız düğmesi parmakla vurulacak kadar geniş olmalı.'
        );

        /*
            SEÇİLİ YILDIZ YALNIZ RENKLE ANLATILMAZ (WCAG 1.4.1). Renk körü
            bir misafir için "gri yıldız" ile "vurgu rengi yıldız" aynı
            yıldızdır; parlaklık farkı her görme türünde okunur.
        */
        self::assertMatchesRegularExpression(
            '/\.qr-rate-btn\[aria-pressed=.true.\]\s*\{[^}]*opacity/s',
            $css,
            'RATING-DISPLAY-320-06: seçili yıldız renk DIŞINDA bir işaret de taşımalı.'
        );
    }

    public function test_the_guest_rating_surface_is_not_drawn_where_nobody_can_vote(): void
    {
        /*
            YAPILAMAYAN İŞ ÇİZİLMEZ — sepetle aynı kural (`docs/115` S3).

            Masaya bağlı olmayan bir kodda oy verilemez; oy denetimini çizip
            sonra reddetmek, misafire olmayan bir yetenek göstermektir.
            Puanı da çizmiyoruz: aynı satırın yarısını gösterip yarısını
            gizlemek, iki ayrı kod yolu ve bir gün ayrışacak iki ekran
            demekti.
        */
        $this->scene = $this->ratingScene('esik-cizilmez', ['Kahve']);

        $this->storeRatingScore($this->scene['workspaceId'], $this->scene['products']['Kahve'], 4.3, 12, 9.5, true);

        DB::table('qr_codes')->where('id', $this->scene['qrCodeId'])->update(['dining_table_id' => null]);

        $html = $this->guestHtml();

        self::assertStringContainsString('Kahve', $html, 'Menü GİZLENMEZ; çizilmeyen şey yalnız puanlamadır.');

        foreach (['data-rate', 'data-rating-score', 'data-rating-unknown', '/ratings'] as $control) {
            self::assertStringNotContainsString(
                $control,
                $html,
                "RATING-DISPLAY-BELOW-01: oy verilemeyen bir yüzeyde `{$control}` çizilemez."
            );
        }
    }
}
