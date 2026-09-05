<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Rating\RatingAlgorithm;
use App\Domain\Rating\RatingSource;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TÜRETİLMİŞ PUANLARI HAM SİNYALDEN YENİDEN ÜRETİR — `docs/116` §1 Ö2/Ö3
 * (FF-180 / P3).
 *
 * ═══ YENİDEN HESAPLANAMAYAN PUAN, DÜZELTİLEMEYEN PUANDIR ═══
 *
 * Bu komut olmasaydı algoritmanın kendisi de bir hipotez olarak kalırdı:
 * ağırlıkları değiştirebilirdik ama dünkü puanlara dokunamazdık, dolayısıyla
 * hiçbir değişikliğin sonucunu göremezdik. Kural dosyaya yazılabilir olmanın
 * bedeli, o dosyanın çıktısının HER AN yeniden üretilebilmesidir.
 *
 * ═══ ORTALAMA HİÇBİR ÜRÜN SATIRINA YAZILMAZ ═══
 *
 * Bu komut yalnız `rating_scores`'a yazar. `menu_items` ya da `products`
 * satırlarına bir "ortalama" sütunu yazsaydık, `docs/116`'nın reddettiği
 * varsayımı arka kapıdan geri getirmiş olurduk: o sütunu gören her sorgu
 * ham sinyali unuturdu.
 *
 * ═══ NEDEN ÖNCE SİL, SONRA YAZ ═══
 *
 * Hedef sürüm ve kapsam için satırlar tek işlemde silinip yeniden yazılır.
 * Sebep bir kenar durumu: bütün sinyalleri kötüye kullanım olarak
 * işaretlenmiş bir ürünün ESKİ puanı, "üstüne yaz" yaklaşımında sonsuza
 * kadar ekranda kalırdı — çünkü onu güncelleyecek hiçbir sinyal kalmamış
 * olurdu. Tablo tamamen türetilmiş olduğu için silmek bir veri kaybı
 * değildir; işlem (transaction) sayesinde okuyucular ya eskisini ya
 * yenisini görür, ikisinin arasını değil.
 *
 * ═══ ESKİ SÜRÜMÜN PUANI EZİLMEZ ═══
 *
 * Silme `algorithm_version` ile SINIRLIDIR. v2 hesaplandığında v1 satırları
 * yerinde kalır; "bu puan neden düştü — kural mı değişti, oy mu geldi?"
 * sorusunun cevabı iki satırın yan yana durmasıdır.
 */
final class RecomputeRatingScoresCommand extends Command
{
    /**
     * Tek seferde yazılan satır sayısı.
     *
     * SQLite'ın sorgu başına değişken sınırı vardır ve bu tablo satır başına
     * on sütun yazar; sınırsız bir toplu ekleme yerelde patlar, üretimde
     * geçerdi — yani hatayı en geç fark edeceğimiz yerde bırakırdı.
     */
    private const WRITE_CHUNK = 200;

    /** Bellekte biriktirilen sinyal okuma penceresi. */
    private const READ_CHUNK = 1000;

    protected $signature = 'rating:recompute
        {--algorithm-version= : Hangi sürümle hesaplansın (varsayılan: yürürlükteki sürüm)}
        {--workspace= : Yalnız bu kiracı yeniden hesaplansın}';

    protected $description = 'Türetilmiş puanları değişmez ham sinyallerden yeniden hesaplar.';

    public function handle(): int
    {
        $algorithm = $this->resolveAlgorithm();

        if (! $algorithm instanceof RatingAlgorithm) {
            return self::FAILURE;
        }

        $workspaceId = $this->option('workspace') === null ? null : (int) $this->option('workspace');
        $now = Carbon::now();

        [$tally, $skipped] = $this->tally($algorithm, $workspaceId, $now);

        if ($skipped > 0) {
            /*
                BİLİNMEYEN KAYNAK SESSİZCE ATLANMAZ. Veritabanında enum'da
                olmayan bir `source` varsa, bu ya bir geri alınmış göçtür ya
                da elle yazılmış bir satır; ikisi de operatörün görmesi
                gereken şeylerdir. Sessizce atlansaydı, o oylar bir daha hiç
                sayılmaz ve kimse fark etmezdi.
            */
            $this->warn($skipped.' signal(s) skipped: unknown source or non-positive scale.');
        }

        $written = $this->write($algorithm, $workspaceId, $tally, $now);

        $this->info($written.' rating score(s) recomputed with algorithm v'.$algorithm->version.'.');

        return self::SUCCESS;
    }

    private function resolveAlgorithm(): ?RatingAlgorithm
    {
        $requested = $this->option('algorithm-version');

        if ($requested === null) {
            return RatingAlgorithm::current();
        }

        $algorithm = RatingAlgorithm::version((int) $requested);

        if (! $algorithm instanceof RatingAlgorithm) {
            // Var olmayan bir sürümle hesaplamak, olmayan bir kuralı
            // uygulamış gibi damgalanmış satırlar üretirdi.
            $this->error('Rating algorithm v'.((int) $requested).' does not exist.');

            return null;
        }

        return $algorithm;
    }

    /**
     * Sinyalleri ürün başına toplar.
     *
     * @return array{0: array<string, array{workspace_id: int, subject_type: string, subject_id: int, count: int, weight: float, weighted: float}>, 1: int}
     */
    private function tally(RatingAlgorithm $algorithm, ?int $workspaceId, Carbon $now): array
    {
        $tally = [];
        $skipped = 0;

        DB::table('rating_signals')
            /*
                İŞARETLİ SİNYAL HESABA GİRMEZ AMA SİLİNMEZ (`docs/116` §4).
                Buradaki tek fark bir `WHERE`tir; satır yerinde durur ve
                "bu oyu neden saymadık?" sorusu hâlâ cevaplanabilir.
            */
            ->whereNull('excluded_at')
            ->when($workspaceId !== null, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->chunkById(self::READ_CHUNK, function ($rows) use ($algorithm, $now, &$tally, &$skipped): void {
                foreach ($rows as $row) {
                    $source = RatingSource::tryFrom((string) $row->source);
                    $scaleMax = (int) $row->score_scale_max;

                    if (! $source instanceof RatingSource || $scaleMax <= 0) {
                        $skipped++;

                        continue;
                    }

                    /*
                        ÖLÇEK ÇEVRİMİ. Sinyal kendi ölçeğinde saklanır;
                        burada gösterim ölçeğine taşınır. Çevirmeden
                        toplasaydık, beğen/beğenme (1 üzerinden) gönderen
                        bir kaynak her ürünü "1 yıldıza" çekerdi.
                    */
                    $normalised = ((float) $row->score_value / $scaleMax) * $algorithm->scaleMax;

                    $ageInDays = ($now->getTimestamp() - Carbon::parse($row->observed_at)->getTimestamp()) / 86400;
                    $weight = $algorithm->weightForSignal($source, $ageInDays);

                    $key = $row->workspace_id.'|'.$row->subject_type.'|'.$row->subject_id;

                    if (! isset($tally[$key])) {
                        $tally[$key] = [
                            'workspace_id' => (int) $row->workspace_id,
                            'subject_type' => (string) $row->subject_type,
                            'subject_id' => (int) $row->subject_id,
                            'count' => 0,
                            'weight' => 0.0,
                            'weighted' => 0.0,
                        ];
                    }

                    $tally[$key]['count']++;
                    $tally[$key]['weight'] += $weight;
                    $tally[$key]['weighted'] += $normalised * $weight;
                }
            });

        return [$tally, $skipped];
    }

    /**
     * @param  array<string, array{workspace_id: int, subject_type: string, subject_id: int, count: int, weight: float, weighted: float}>  $tally
     */
    private function write(RatingAlgorithm $algorithm, ?int $workspaceId, array $tally, Carbon $now): int
    {
        $rows = [];

        foreach ($tally as $entry) {
            /*
                AĞIRLIĞI SIFIRA SÖNMÜŞ ÜRÜN İÇİN SATIR YAZILMAZ.

                Bölme hatasından kaçınmanın ötesinde bir anlamı var: on yıl
                önce oy almış ve o gün bugündür hiç oy almamış bir ürünün
                puanı artık bir ölçüm değildir. Satır yazmamak, ekranda
                "henüz yeterli değerlendirme yok" demesini sağlar — sıfır
                yıldız değil.
            */
            if ($entry['weight'] <= 0.0) {
                continue;
            }

            $score = $entry['weighted'] / $entry['weight'];

            $rows[] = [
                'workspace_id' => $entry['workspace_id'],
                'subject_type' => $entry['subject_type'],
                'subject_id' => $entry['subject_id'],
                'algorithm_version' => $algorithm->version,
                'score_value' => round($score, 4),
                'score_scale_max' => $algorithm->scaleMax,
                'signal_count' => $entry['count'],
                'total_weight' => round($entry['weight'], 6),
                'meets_display_threshold' => $algorithm->thresholds->areMet($entry['count'], $entry['weight']),
                'computed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($algorithm, $workspaceId, $rows): void {
            DB::table('rating_scores')
                ->where('algorithm_version', $algorithm->version)
                ->when($workspaceId !== null, fn ($query) => $query->where('workspace_id', $workspaceId))
                ->delete();

            foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
                DB::table('rating_scores')->insert($chunk);
            }
        });

        return count($rows);
    }
}
