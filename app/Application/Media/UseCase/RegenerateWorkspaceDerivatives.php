<?php

declare(strict_types=1);

namespace App\Application\Media\UseCase;

use App\Application\Media\Port\MediaRegenerationPort;

/**
 * TOPLU YENİDEN ÜRETİM — `docs/108` §3 madde 4, §4.
 *
 * Bu sınıf YENİ BİR İŞLEME HATTI DEĞİLDİR ve olmamalıdır. Tek işi, hazır
 * varlıkları sırayla var olan `ReprocessMediaAsset`e vermektir. İkinci bir
 * hat yazmak, asıl korunuyor mu / sürüm açılıyor mu / iş kaydı düşüyor mu
 * sorularının iki ayrı yerde cevaplanması demek olurdu; ikisi bir gün
 * ayrışır ve ayrıştığı gün kimse fark etmez.
 *
 * SINIRLIDIR. Yeniden üretim senkrondur: çağrıldığı istekte görsel işler.
 * İki yüz fotoğraflu bir kiracıda sınırsız bir toplu iş, isteği zaman
 * aşımına uğratır ve sahip işin yarıda kaldığını hiçbir yerden öğrenemez.
 * Sınıra takılan dosyalar `remaining` olarak SAYILIR; sahip düğmeye yeniden
 * bastığında kaldığı yerden devam eder.
 *
 * Tek bir varlığın başarısızlığı toplu işi DURDURMAZ: bozuk tek bir dosya
 * yüzünden diğer kırk dokuzunun yenilenmemesi, sahibin işini görmez.
 * Başarısızlık sayılır ve kuyrukta sebebiyle birlikte görünür.
 */
final class RegenerateWorkspaceDerivatives
{
    public function __construct(
        private readonly MediaRegenerationPort $regeneration,
        private readonly ReprocessMediaAsset $reprocess,
    ) {}

    /**
     * @return array{processed:int, succeeded:int, failed:int, skipped:int, remaining:int, assetIds:list<int>}
     */
    public function __invoke(int $workspaceId, int $batchLimit): array
    {
        $stats = $this->regeneration->stats($workspaceId);
        $ids = $this->regeneration->readyAssetIds($workspaceId, $batchLimit);

        $succeeded = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($ids as $assetId) {
            $outcome = ($this->reprocess)($workspaceId, $assetId);

            match ($outcome) {
                'reprocessed' => $succeeded++,
                'failed' => $failed++,
                // Arada başka bir istek onu işlemeye başlamış olabilir;
                // "hazır değil" bir hata değil, bir yarıştır.
                default => $skipped++,
            };
        }

        return [
            'processed' => count($ids),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'skipped' => $skipped,
            'remaining' => max(0, $stats['affectedAssets'] - count($ids)),
            'assetIds' => array_values($ids),
        ];
    }
}
