<?php

declare(strict_types=1);

namespace App\Application\Media\UseCase;

/**
 * DÖNÜŞTÜR — "eski biçimleri modern biçime çevir" (`docs/108` §6.3).
 *
 * Bu sınıf YENİ BİR İŞLEME HATTI DEĞİLDİR ve olmamalıdır; `Regenerate
 * WorkspaceDerivatives` ile birebir aynı gerekçe. Tek işi, SEÇİLEN
 * varlıkları sırayla var olan `ReprocessMediaAsset`e — bu kez bir hedef
 * biçimle — vermektir. Böylece "asıl korunuyor mu / yeni sürüm açılıyor mu
 * / iş kaydı düşüyor mu" soruları tek bir yerde cevaplanır.
 *
 * SINIRLIDIR. Dönüştürme senkrondur: çağrıldığı istekte görsel kodlar ve
 * AVIF kodlaması JPEG'den belirgin biçimde yavaştır. Sınırsız bir seçim,
 * iki yüz fotoğraflu bir kiracıda isteği zaman aşımına uğratır ve sahip
 * işin yarıda kaldığını hiçbir yerden öğrenemez. Sınıra takılanlar
 * `remaining` olarak SAYILIR; sahip düğmeye yeniden bastığında kaldığı
 * yerden devam eder.
 *
 * Tek bir varlığın başarısızlığı işi DURDURMAZ: bozuk ya da saydam tek bir
 * dosya yüzünden diğer kırk dokuzunun dönüşmemesi sahibin işini görmez.
 * Başarısızlık SAYILIR ve sebebiyle birlikte kuyrukta görünür.
 */
final class ConvertMediaAssetsToFormat
{
    public function __construct(private readonly ReprocessMediaAsset $reprocess) {}

    /**
     * @param  list<int>  $assetIds
     * @return array{processed:int, succeeded:int, failed:int, skipped:int, remaining:int, assetIds:list<int>}
     */
    public function __invoke(int $workspaceId, string $format, array $assetIds, int $batchLimit): array
    {
        /*
            Aynı kimliğin iki kez gönderilmesi bir kazadır (çift tıklama,
            yinelenen bir seçim), iki iş değil: `array_unique` olmasaydı
            aynı dosya için iki sürüm açılırdı ve ikincisi birincinin
            çıktısını yeniden kodlardı.
        */
        $unique = array_values(array_unique(array_map('intval', $assetIds)));
        $batch = array_slice($unique, 0, max(0, $batchLimit));

        $succeeded = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($batch as $assetId) {
            /*
                KİRACI SINIRI burada değil, `claimReadyForReprocessing`
                içinde çizilir: yabancı ya da hazır olmayan bir kimlik
                `not-ready` döner ve ATLANIR. Bunu bir hata saymak, sahibin
                ekranında sebebi olmayan bir kırmızı sayı bırakırdı — oysa
                onun seçtiği dosyaların hepsi işlendi.
            */
            $outcome = ($this->reprocess)($workspaceId, $assetId, $format);

            match ($outcome) {
                'reprocessed' => $succeeded++,
                'failed' => $failed++,
                default => $skipped++,
            };
        }

        return [
            'processed' => count($batch),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'skipped' => $skipped,
            // Sınıra takılan SEÇİLİ dosyalar — kütüphanenin kalanı değil.
            // Sahip neyi seçtiyse onun kalanını okur.
            'remaining' => max(0, count($unique) - count($batch)),
            'assetIds' => $batch,
        ];
    }
}
