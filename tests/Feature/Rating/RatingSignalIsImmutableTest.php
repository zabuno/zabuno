<?php

declare(strict_types=1);

namespace Tests\Feature\Rating;

use App\Domain\Rating\RatingSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * HAM SİNYAL DEĞİŞMEZ — `docs/116` §1 Ö1/Ö2/Ö3.
 *
 * Bu dosyadaki dört önlem, ŞİMDİ alınmazsa sonradan alınamayacak
 * olanlardır. Sonradan eklemek "geçmiş satırlara ne yazacağız?" sorusunu
 * doğurur ve o sorunun her cevabı bir uydurmadır.
 *
 * ═══ NEDEN ORTALAMA BİR SÜTUNA YAZILMIYOR ═══
 *
 * "Beş yıldızın ortalaması" bir hesap değil, bir VARSAYIMDIR: her oyun eşit
 * ağırlıkta olduğunu, zamanın önemsiz olduğunu ve kaynağın fark etmediğini
 * varsayar. Ortalamayı satır üstüne yazan bir sistem ham sinyalleri
 * saklamayı gereksiz görür — ve o sinyaller bir daha geri gelmez.
 *
 * Requirement IDs: RATING-SIGNAL-IMMUTABLE-01.
 */
final class RatingSignalIsImmutableTest extends TestCase
{
    use RefreshDatabase;

    // --- Ö1: her sinyal KAYNAĞINI taşır --------------------------------------

    public function test_a_signal_cannot_be_written_without_naming_its_source(): void
    {
        self::assertTrue(Schema::hasColumn('rating_signals', 'source'));

        $columns = Schema::getColumns('rating_signals');
        $source = collect($columns)->firstWhere('name', 'source');

        self::assertNotNull($source);
        self::assertFalse(
            (bool) $source['nullable'],
            'RATING-SIGNAL-IMMUTABLE-01: kaynak boş bırakılabilseydi, ikinci kaynak geldiğinde '
            .'eski satırların hepsi "kaynağı bilinmiyor" olurdu ve ağırlıklandırma o günden öncesini kapsayamazdı.'
        );
    }

    /**
     * BUGÜN TEK KAYNAK VAR AMA YERİ AÇIK.
     *
     * Zomato, Swarm, Google Haritalar ve sosyal uygulama sonra gelecek
     * (`docs/116` §5). Enum'da yerlerinin bugünden açık olması, o gün
     * geldiğinde göç değil yalnız bir adaptör gerektirmesini sağlar.
     */
    public function test_the_source_vocabulary_already_has_room_for_what_is_coming(): void
    {
        $values = array_map(static fn (RatingSource $s): string => $s->value, RatingSource::cases());

        self::assertContains('guest_scan', $values);
        self::assertContains('external_zomato', $values);
        self::assertContains('external_swarm', $values);
        self::assertContains('external_google', $values);
        self::assertContains('social_app', $values);
    }

    // --- Ö2: ham ile türetilmiş AYRI yaşar -----------------------------------

    public function test_the_derived_score_lives_in_its_own_table(): void
    {
        self::assertTrue(Schema::hasTable('rating_signals'));
        self::assertTrue(
            Schema::hasTable('rating_scores'),
            'RATING-SIGNAL-IMMUTABLE-01: türetilmiş puan ham sinyalle aynı satırda yaşarsa, '
            .'algoritma değiştiğinde yeniden hesaplanamaz.'
        );

        self::assertFalse(
            Schema::hasColumn('menu_items', 'rating_average'),
            'RATING-SIGNAL-IMMUTABLE-01: ortalamayı ürün satırına yazmak, bu belgenin reddettiği varsayımın ta kendisi.'
        );
        self::assertFalse(Schema::hasColumn('products', 'rating_average'));
    }

    // --- Ö3: her türetilmiş puan SÜRÜM taşır ---------------------------------

    public function test_a_derived_score_cannot_exist_without_an_algorithm_version(): void
    {
        $version = collect(Schema::getColumns('rating_scores'))->firstWhere('name', 'algorithm_version');

        self::assertNotNull($version);
        self::assertFalse(
            (bool) $version['nullable'],
            'RATING-SIGNAL-IMMUTABLE-01: sürüm damgası olmadan "bu puan neden düştü?" sorusunun cevabı yoktur — '
            .'kural mı değişti, oy mu geldi, ayırt edilemez.'
        );
    }

    // --- §4: kötüye kullanım SİLMEZ, İŞARETLER -------------------------------

    /**
     * BİR SİNYAL SİLİNMEZ, DIŞARIDA BIRAKILIR.
     *
     * Silmek, yanlış işaretlemenin geri dönüşünü de silerdi: "bu oyu neden
     * saymadık?" sorusunun cevabı, o oyun kendisidir.
     */
    public function test_an_abusive_signal_is_flagged_and_still_readable(): void
    {
        $signalId = $this->writeSignal();

        DB::table('rating_signals')->where('id', $signalId)->update([
            'excluded_at' => now(),
            'exclusion_reason' => 'burst_detected',
        ]);

        $row = DB::table('rating_signals')->where('id', $signalId)->first();

        self::assertNotNull($row, 'RATING-SIGNAL-IMMUTABLE-01: işaretlenen sinyal SİLİNMEZ.');
        self::assertNotNull($row->excluded_at);
        self::assertSame('burst_detected', (string) $row->exclusion_reason);
        self::assertSame(5, (int) $row->score_value, 'Puanın kendisi işaretlemeden ETKİLENMEZ.');
    }

    /**
     * DIŞ KAYNAK ÖLÇEĞİ SİNYALLE BİRLİKTE SAKLANIR.
     *
     * Zomato beş üzerinden, bir sosyal uygulama beğen/beğenme olabilir.
     * Ölçeği yazmadan ham puanı saklamak, iki farklı birimdeki sayıyı aynı
     * sütuna koymaktır — ve o karışıklık sonradan çözülemez.
     */
    public function test_the_scale_travels_with_the_score(): void
    {
        $row = DB::table('rating_signals')->where('id', $this->writeSignal())->first();

        self::assertSame(5, (int) $row->score_scale_max);
    }

    /**
     * MASADAN GELDİĞİNİN KANITI SİNYALDE DURUR.
     *
     * `docs/116` §4: oy vermek için o masadan karekod okutmuş olmak gerekir.
     * Bağlam sinyalde saklanmazsa, bu iddia sonradan doğrulanamaz — ve
     * doğrulanamayan bir iddia, bir pazarlama cümlesidir.
     */
    public function test_the_at_the_table_evidence_is_stored_with_the_signal(): void
    {
        self::assertTrue(Schema::hasColumn('rating_signals', 'qr_code_id'));
        self::assertTrue(Schema::hasColumn('rating_signals', 'dining_table_id'));
        self::assertTrue(Schema::hasColumn('rating_signals', 'visitor_key'));
    }

    private function writeSignal(): int
    {
        return (int) DB::table('rating_signals')->insertGetId([
            'workspace_id' => 1,
            'subject_type' => 'product',
            'subject_id' => 7,
            'source' => RatingSource::GuestScan->value,
            'score_value' => 5,
            'score_scale_max' => 5,
            'visitor_key' => str_repeat('a', 64),
            'observed_at' => now(),
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
