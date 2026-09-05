<?php

declare(strict_types=1);

namespace Tests\Unit\Rating;

use App\Domain\Rating\RatingAlgorithm;
use App\Domain\Rating\RatingSource;
use Tests\TestCase;

/**
 * ALGORİTMA BİR DOSYADIR — `docs/116` §2 (sahibin 2026-09-05 kararı).
 *
 * *"Puanlamanın KPI'ları, OKR'ları bir algoritma dosyasına bağlıdır."*
 *
 * ═══ NEDEN DOSYA, NEDEN PANEL DEĞİL ═══
 *
 * Ağırlıkları panelden düzenlenebilir yapmak cazip görünür ve yanlıştır:
 * ölçüm ve para etkileyen bir kural, gözden geçirme ve testten geçmeden
 * değişmemeli. "Panelden ayarlanabilir algoritma", ilk yanlış değerde
 * sessizce HER ÜRÜNÜN puanını değiştirir ve bunu kimse fark etmez.
 *
 * ═══ BU TESTİN ASIL İŞİ ═══
 *
 * Dosyanın var olduğunu değil, İÇİNDE NE OLMASI GEREKTİĞİNİ dondurur.
 * Özellikle KPI ve OKR: optimize edilen şey yazılı olmazsa, algoritma
 * kimsenin kabul etmediği bir hedefe doğru kayar ve "puan düştü" sorusunun
 * cevabı kalmaz.
 *
 * Requirement IDs: RATING-ALGO-FILE-01.
 */
final class RatingAlgorithmFileTest extends TestCase
{
    public function test_the_current_version_loads_and_names_what_it_optimises(): void
    {
        $algorithm = RatingAlgorithm::current();

        self::assertSame(1, $algorithm->version);

        self::assertNotSame('', trim($algorithm->kpi), 'RATING-ALGO-FILE-01: neyi iyileştirdiğimiz YAZILI olmalı.');
        self::assertNotSame('', trim($algorithm->okr->objective));
        self::assertGreaterThan(0.0, $algorithm->okr->target);
    }

    /**
     * HER KAYNAĞIN BİR AĞIRLIĞI VARDIR — eksiği olan bir kaynak sessizce
     * sıfır ağırlıkla katılamaz.
     *
     * Sessiz sıfır, bir kaynağın hiç sayılmadığını kimseye söylemeden
     * yapmaktır: `source` enum'una yeni bir değer eklenir, ağırlık dosyasına
     * eklenmeyi unutulur ve o kaynaktan gelen her oy hesaba katılmamış olur.
     * Burası kırılırsa, unutulan şey ortaya çıkar.
     */
    public function test_every_source_carries_an_explicit_weight(): void
    {
        $algorithm = RatingAlgorithm::current();

        foreach (RatingSource::cases() as $source) {
            self::assertArrayHasKey(
                $source->value,
                $algorithm->weights,
                'RATING-ALGO-FILE-01: `'.$source->value.'` için ağırlık yazılmamış — '
                .'sessiz sıfır, bir kaynağı hiç saymamanın en görünmez yoludur.'
            );
        }
    }

    /**
     * MASADAN GELEN OY DAHA AĞIRDIR — ve bu ürünün en güçlü sinyali.
     *
     * `docs/116` §4: oy vermek için o masadan karekod okutmuş olmak gerekir.
     * Rakip bir platformun sahip olmadığı şey budur: o kişi gerçekten
     * oradaydı. Dış kaynaktan gelen oy bunu kanıtlayamaz.
     *
     * Bu sıralama bozulursa ürünün ayırt edici iddiası da bozulur.
     */
    public function test_a_signal_from_the_table_outweighs_every_external_source(): void
    {
        $weights = RatingAlgorithm::current()->weights;
        $atTable = $weights[RatingSource::GuestScan->value];

        foreach (RatingSource::cases() as $source) {
            if ($source === RatingSource::GuestScan) {
                continue;
            }

            self::assertGreaterThan(
                $weights[$source->value],
                $atTable,
                'RATING-ALGO-FILE-01: masadan gelen oy '.$source->value.' kaynağından ağır OLMALI.'
            );
        }
    }

    /**
     * EŞİK BİR SAYI DEĞİL, GÜVEN MESELESİDİR — ama sıfır olamaz.
     *
     * Üç kişinin verdiği beş yıldız bir bilgi değildir; gösterilirse yeni
     * ürün her zaman en iyi görünür. Eşik sıfıra düşerse bu koruma
     * kalkar ve kimse fark etmez.
     */
    public function test_the_display_threshold_is_never_zero(): void
    {
        $thresholds = RatingAlgorithm::current()->thresholds;

        self::assertGreaterThan(
            0,
            $thresholds->minimumSignals,
            'RATING-ALGO-FILE-01: eşik sıfıra düşerse üç oyluk bir ürün "5,0" görünür.'
        );
    }

    /**
     * ZAMAN SÖNÜMÜ VARDIR VE SONSUZ DEĞİLDİR.
     *
     * Üç yıllık oy bugünkü tabağı anlatmaz. Yarı ömür yoksa, bir kez iyi
     * puan almış bir ürün sonsuza kadar iyi kalır — restoran şefini
     * değiştirse bile.
     */
    public function test_recency_actually_decays(): void
    {
        $recency = RatingAlgorithm::current()->recency;

        self::assertGreaterThan(0, $recency->halfLifeDays);
        self::assertLessThan(
            1.0,
            $recency->weightAfterDays($recency->halfLifeDays * 4),
            'RATING-ALGO-FILE-01: sönüm gerçekten sönmeli.'
        );
        self::assertEqualsWithDelta(0.5, $recency->weightAfterDays($recency->halfLifeDays), 0.001);
    }

    /**
     * ESKİ SÜRÜM SİLİNMEZ.
     *
     * `docs/116` §2: sürüm yükseltmek bir pakettir; eski dosya kalır, yoksa
     * eski puanlar açıklanamaz hâle gelir — "bu puan neden böyleydi?"
     * sorusunun cevabı o dosyadır.
     */
    public function test_every_version_up_to_the_current_one_still_loads(): void
    {
        for ($version = 1; $version <= RatingAlgorithm::current()->version; $version++) {
            $loaded = RatingAlgorithm::version($version);

            self::assertNotNull(
                $loaded,
                'RATING-ALGO-FILE-01: v'.$version.' dosyası kayıp — o sürümle hesaplanmış puanlar artık açıklanamaz.'
            );
            self::assertSame($version, $loaded->version);
        }
    }

    /**
     * DOSYA ÇALIŞMA ZAMANINDA DEĞİŞTİRİLEBİLİR BİR BLOB DEĞİLDİR.
     *
     * Bu testin ölçtüğü şey bir niyet değil, bir yer: algoritma `config/`
     * altında, depoda, gözden geçirilebilir. Veritabanından ya da ayar
     * ekranından okunmaya başlarsa burası kırılır.
     */
    public function test_the_algorithm_lives_in_the_repository_not_in_a_database(): void
    {
        self::assertFileExists(config_path('rating-algorithm/v1.php'));
    }
}
