<?php

declare(strict_types=1);

namespace App\Application\Media\UseCase;

use App\Application\Media\Dto\MediaProcessingOutcome;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\Port\MediaRepositoryPort;
use Throwable;

final class ProcessAcceptedMediaAsset
{
    public function __construct(
        private readonly MediaAssetProcessorPort $processor,
        private readonly MediaRepositoryPort $media,
    ) {}

    public function __invoke(int $workspaceId, int $assetId): void
    {
        $claimed = $this->media->claimAcceptedForProcessing($workspaceId, $assetId);

        if ($claimed === null) {
            return;
        }

        $jobId = $this->media->openProcessingJob($claimed->workspaceId, $claimed->id);

        $result = $this->processor->process($claimed->diskPath, $claimed->slot);

        if ($result->outcome === MediaProcessingOutcome::Failed) {
            $this->media->closeProcessingJobAsFailed(
                $jobId,
                // Sebep BOŞ bırakılamaz: sahibin ekranında "başarısız" tek
                // başına bir bilgi değil, bir çıkmazdır (`docs/76`).
                $result->failureReason ?? 'Görsel işlenemedi.',
            );
            $this->media->markFailedIfProcessing($claimed->workspaceId, $claimed->id);

            return;
        }

        if ($result->outcome !== MediaProcessingOutcome::Succeeded) {
            // Belirsiz sonuç TERMİNAL DEĞİLDİR: varlık `processing`'te
            // güvenle bekler ve iş kaydı neden beklediğini söyler.
            $this->media->closeProcessingJobAsFailed(
                $jobId,
                'Bu ortamda görsel işleme kullanılamıyor; dosya işlenmeyi bekliyor.',
            );

            return;
        }

        try {
            if ($result->renditions !== []) {
                $this->media->persistRenditions($claimed->workspaceId, $claimed->id, $result->renditions);
            }
        } catch (Throwable $exception) {
            // Türevler üretildi ama kaydedilemedi. Varlığı "hazır" demek,
            // olmayan dosyalara işaret eden bir menü yayınlamaktır.
            $this->media->closeProcessingJobAsFailed($jobId, 'Görsel türevleri kaydedilemedi: '.$exception->getMessage());
            $this->media->markFailedIfProcessing($claimed->workspaceId, $claimed->id);

            return;
        }

        $this->media->closeProcessingJobAsSucceeded($jobId);
        $this->media->markReadyIfProcessing($claimed->workspaceId, $claimed->id);
    }
}
