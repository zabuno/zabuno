<?php

declare(strict_types=1);

namespace App\Application\Media\UseCase;

use App\Application\Media\Dto\MediaProcessingOutcome;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\Port\MediaRepositoryPort;
use Throwable;

/**
 * Yeniden üretim — `docs/49` Faz 3 madde 5, `docs/98` FF-69.
 *
 * Asıl dosya değişmez; boru hattı ya da slot politikası değiştiğinde
 * rendition seti ASILDAN yeniden üretilir ve YENİ bir sürüm olur. Eski sürüm
 * silinmez: onu gösteren bir yayın snapshot'ı varsa göstermeye devam eder.
 *
 * Başarısızlıkta varlık `failed` olmaz — zaten hazır bir varlığı, yeniden
 * üretim denemesi yüzünden kırık göstermek yanlıştır; iş kaydı sebebi taşır,
 * varlık `ready` kalır.
 */
final class ReprocessMediaAsset
{
    public function __construct(
        private readonly MediaAssetProcessorPort $processor,
        private readonly MediaRepositoryPort $media,
    ) {}

    /**
     * `$targetFormat` DÖNÜŞTÜRME içindir (`docs/108` §6.3): "eski biçimleri
     * modern biçime çevir". Ayrı bir metot ya da ayrı bir hat DEĞİL, aynı
     * hattın bir parametresi olması bilinçlidir — dönüştürme de bir yeniden
     * üretimdir ve aynı güvenceleri hak eder: asıl korunur, YENİ SÜRÜM
     * açılır, hiçbir satır silinmez, başarısızlık varlığı `failed` yapmaz.
     * İkinci bir hat yazmak, bu dört cümlenin iki ayrı yerde
     * cevaplanması ve bir gün ayrışması demek olurdu.
     *
     * @return 'reprocessed'|'not-ready'|'failed'
     */
    public function __invoke(int $workspaceId, int $assetId, ?string $targetFormat = null): string
    {
        $claimed = $this->media->claimReadyForReprocessing($workspaceId, $assetId);

        if ($claimed === null) {
            return 'not-ready';
        }

        $jobId = $this->media->openProcessingJob($claimed->workspaceId, $claimed->id);
        $result = $this->processor->process($claimed->diskPath, $claimed->slot, $targetFormat);

        if ($result->outcome !== MediaProcessingOutcome::Succeeded || $result->renditions === []) {
            $this->media->closeProcessingJobAsFailed(
                $jobId,
                $result->failureReason ?? 'Yeniden üretim bu ortamda yapılamadı; mevcut sürüm geçerli kaldı.',
            );
            $this->media->markReadyIfProcessing($claimed->workspaceId, $claimed->id);

            return 'failed';
        }

        try {
            $this->media->persistRenditions($claimed->workspaceId, $claimed->id, $result->renditions, $result->lqip);
        } catch (Throwable $exception) {
            $this->media->closeProcessingJobAsFailed($jobId, 'Yeni sürüm kaydedilemedi: '.$exception->getMessage());
            $this->media->markReadyIfProcessing($claimed->workspaceId, $claimed->id);

            return 'failed';
        }

        $this->media->closeProcessingJobAsSucceeded($jobId);
        $this->media->markReadyIfProcessing($claimed->workspaceId, $claimed->id);

        return 'reprocessed';
    }
}
