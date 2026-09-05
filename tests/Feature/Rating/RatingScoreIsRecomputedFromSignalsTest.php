<?php

declare(strict_types=1);

namespace Tests\Feature\Rating;

use App\Domain\Rating\RatingAlgorithm;
use App\Domain\Rating\RatingSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PUAN YENİDEN HESAPLANABİLİR — `docs/116` §1 Ö2/Ö3, §6 P3.
 *
 * *"Yeniden hesaplanamayan puan, düzeltilemeyen puandır."*
 *
 * ═══ BU TESTİN ASIL İŞİ ═══
 *
 * Komutun çalıştığını değil, ALGORİTMANIN GERÇEKTEN UYGULANDIĞINI ölçer.
 * Her iddia bir SONUÇ üzerinden kurulur: düz ortalamanın vereceği sayı ile
 * ağırlıklı hesabın verdiği sayı farklı olmalıdır. Ağırlık ya da sönüm
 * sessizce devre dışı kalırsa, düz ortalamaya düşen sonuç burayı kırar.
 *
 * Requirement IDs: RATING-RECOMPUTE-01.
 */
final class RatingScoreIsRecomputedFromSignalsTest extends TestCase
{
    use RefreshDatabase;

    private const WORKSPACE_ID = 1;

    private const SUBJECT_ID = 7;

    /** Her sinyale ayrı bir ziyaretçi veren sayaç (bkz. `writeSignal`). */
    private static int $visitorSequence = 0;

    /**
     * EŞİĞİ GEÇEN BİR ÜRÜN, PUANINI VE SÜRÜM DAMGASINI ALIR.
     *
     * Sekiz taze masa oyu, algoritma dosyasındaki eşiğin (sekiz sinyal,
     * 4,0 ağırlık) hemen üstündedir — yani bu senaryo eşiğin "geçilebilir"
     * olduğunu da kanıtlar. Eşik bir gün ulaşılamaz bir yere çekilirse
     * burası kırılır ve bunu sahibin menüsü boş göründüğünde değil, burada
     * öğreniriz.
     */
    public function test_a_product_with_enough_fresh_votes_gets_a_score_stamped_with_its_algorithm(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->writeSignal(score: 4);
        }

        $this->artisan('rating:recompute')->assertSuccessful();

        $score = $this->score();

        self::assertNotNull($score, 'RATING-RECOMPUTE-01: ham sinyal var ama türetilmiş puan yok.');
        self::assertEqualsWithDelta(4.0, (float) $score->score_value, 0.0001);
        self::assertSame(RatingAlgorithm::current()->version, (int) $score->algorithm_version);
        self::assertTrue((bool) $score->meets_display_threshold);
    }

    /**
     * EŞİĞİN ALTINDA PUAN HESAPLANIR AMA GÖSTERİLEMEZ DİYE İŞARETLENİR.
     *
     * `docs/116` §3: üç kişinin verdiği beş yıldız bir bilgi değildir.
     * Burada ölçülen şey, o üç oyun SIFIRLANMADIĞI ama "gösterilebilir"
     * de sayılmadığıdır — sıfır bir ölçümdür ve bilinmeyenin yerine
     * geçemez.
     */
    public function test_three_votes_are_computed_but_not_publishable(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->writeSignal(score: 5);
        }

        $this->artisan('rating:recompute')->assertSuccessful();

        $score = $this->score();

        self::assertNotNull($score);
        self::assertEqualsWithDelta(5.0, (float) $score->score_value, 0.0001);
        self::assertFalse(
            (bool) $score->meets_display_threshold,
            'RATING-RECOMPUTE-01: üç oyluk bir ürün gösterilebilir sayılırsa, yeni ürün her zaman en iyi görünür.'
        );
    }

    /**
     * MASADAN GELEN OY, DIŞ KAYNAKTAN GELENİ BASTIRIR.
     *
     * Aynı gün verilmiş iki oy: masadan 5, Google'dan 1. Düz ortalama 3,0
     * derdi. Ağırlık uygulanıyorsa sonuç 3,0'ın belirgin biçimde üstünde
     * olmak zorundadır — çünkü masadaki kişinin orada olduğunu BİLİYORUZ,
     * Google'daki yorumcunun olup olmadığını bilmiyoruz.
     */
    public function test_a_vote_from_the_table_pulls_harder_than_an_external_one(): void
    {
        $this->writeSignal(score: 5, source: RatingSource::GuestScan);
        $this->writeSignal(score: 1, source: RatingSource::ExternalGoogle);

        $this->artisan('rating:recompute')->assertSuccessful();

        self::assertGreaterThan(
            3.0,
            (float) $this->score()->score_value,
            'RATING-RECOMPUTE-01: sonuç düz ortalamaya eşitse kaynak ağırlığı hiç uygulanmamış demektir.'
        );
    }

    /**
     * ESKİ OY HAFİFLER.
     *
     * Bugün verilmiş bir 5 ile dört yarı ömür önce verilmiş bir 1. Düz
     * ortalama yine 3,0 derdi; sönüm çalışıyorsa eski oy ağırlığının
     * on altıda birine inmiştir ve sonuç 5'e çok yakın çıkar.
     *
     * Sönüm olmasaydı, şefini değiştirmiş bir restoranın üç yıl önceki
     * kötü tabağı bugünkü tabağı hâlâ aşağı çekerdi.
     */
    public function test_an_old_vote_weighs_less_than_todays_vote(): void
    {
        $halfLife = RatingAlgorithm::current()->recency->halfLifeDays;

        $this->writeSignal(score: 5);
        $this->writeSignal(score: 1, observedAt: Carbon::now()->subDays((int) ($halfLife * 4)));

        $this->artisan('rating:recompute')->assertSuccessful();

        self::assertGreaterThan(
            4.5,
            (float) $this->score()->score_value,
            'RATING-RECOMPUTE-01: sonuç düz ortalamaya yakınsa zaman sönümü hiç uygulanmamış demektir.'
        );
    }

    /**
     * İŞARETLENEN OY SAYILMAZ — VE ESKİ PUANI DA GÖTÜRÜR.
     *
     * Önce iki oyla bir puan üretilir. Sonra ikisi de kötüye kullanım
     * olarak işaretlenir (silinmez!) ve yeniden hesaplanır. Kalan bir
     * türetilmiş puan olsaydı, sahibin ekranında artık hiçbir geçerli oya
     * dayanmayan bir sayı asılı kalırdı — ve o sayının nereden geldiğini
     * kimse bulamazdı.
     */
    public function test_a_score_that_lost_all_its_valid_signals_does_not_linger(): void
    {
        $this->writeSignal(score: 5);
        $this->writeSignal(score: 5);
        $this->artisan('rating:recompute')->assertSuccessful();
        self::assertNotNull($this->score());

        DB::table('rating_signals')->update([
            'excluded_at' => Carbon::now(),
            'exclusion_reason' => 'burst_detected',
        ]);

        $this->artisan('rating:recompute')->assertSuccessful();

        self::assertNull($this->score(), 'RATING-RECOMPUTE-01: dayanağı kalmamış puan ekranda kalamaz.');
        self::assertSame(
            2,
            DB::table('rating_signals')->count(),
            'RATING-RECOMPUTE-01: işaretlenen sinyaller SİLİNMEZ; yalnız hesabın dışında kalır.'
        );
    }

    /**
     * AYNI GİRDİ, AYNI ÇIKTI — VE TEK SATIR.
     *
     * Komut günde birkaç kez çalışacak. İkinci koşu yeni bir satır
     * bıraksaydı, "bir ürünün puanı" sorusunun cevabı zamanla çoğalır ve
     * hangisinin güncel olduğu ancak tarihe bakarak anlaşılırdı.
     */
    public function test_running_it_twice_leaves_exactly_one_row(): void
    {
        $this->writeSignal(score: 4);

        $this->artisan('rating:recompute')->assertSuccessful();
        $first = (float) $this->score()->score_value;

        $this->artisan('rating:recompute')->assertSuccessful();

        self::assertSame(1, DB::table('rating_scores')->count());
        self::assertEqualsWithDelta($first, (float) $this->score()->score_value, 0.0001);
    }

    private function writeSignal(
        int $score,
        RatingSource $source = RatingSource::GuestScan,
        ?Carbon $observedAt = null,
    ): void {
        $observedAt ??= Carbon::now();

        DB::table('rating_signals')->insert([
            'workspace_id' => self::WORKSPACE_ID,
            'subject_type' => 'product',
            'subject_id' => self::SUBJECT_ID,
            'source' => $source->value,
            'score_value' => $score,
            'score_scale_max' => 5,
            /*
                HER SİNYAL AYRI BİR ZİYARETÇİDEN.

                Önceden anahtar on olasılıktan rastgele seçiliyordu ve
                sekiz sinyalli sahnelerde aynı anahtar iki kez çıkabiliyordu
                — yani "sekiz oy" aslında bir kişinin sekiz oyu olabilirdi.
                P4 bu durumu artık veritabanı düzeyinde imkânsız kılıyor
                (`rating_signals_one_counted_vote_unique`): bir ziyaretçinin
                bir üründe en fazla bir SAYILAN oyu olur. Sayaç, sahnenin
                anlattığı şeyi ("sekiz farklı misafir") doğru kuruyor.
            */
            'visitor_key' => str_pad((string) ++self::$visitorSequence, 64, '0', STR_PAD_LEFT),
            'observed_at' => $observedAt,
            'recorded_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function score(): ?object
    {
        return DB::table('rating_scores')
            ->where('workspace_id', self::WORKSPACE_ID)
            ->where('subject_type', 'product')
            ->where('subject_id', self::SUBJECT_ID)
            ->first();
    }
}
