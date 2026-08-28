<?php

declare(strict_types=1);

namespace App\Application\Media\UseCase;

use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Application\Media\Port\MediaRepositoryPort;

final class ScanQuarantinedMediaAsset
{
    public function __construct(
        private readonly MalwareScannerPort $scanner,
        private readonly MediaRepositoryPort $media,
    ) {}

    public function __invoke(int $workspaceId, int $assetId): void
    {
        $claimed = $this->media->claimQuarantinedForScanning($workspaceId, $assetId);

        if ($claimed === null) {
            return;
        }

        $jobId = $this->media->openScanJob($claimed->workspaceId, $claimed->id);

        $result = $this->scanner->scan($claimed->diskPath);

        if ($result->verdict === MediaScanVerdict::Infected) {
            $this->media->closeScanJobAsHeld($jobId, 'Dosyada zararlı içerik bulundu ve reddedildi.');
            $this->media->markRejectedIfScanning($claimed->workspaceId, $claimed->id);

            return;
        }

        if ($result->verdict === MediaScanVerdict::Clean) {
            $this->media->closeScanJobAsCompleted($jobId);
            $this->media->markAcceptedIfScanning($claimed->workspaceId, $claimed->id);

            return;
        }

        // Tarayıcı yok ya da cevap veremedi. Dosya İLERLETİLMEZ — ve ürün
        // bunu "tarandı" gibi göstermez; sebep kayda geçer ve sahibin
        // listesinde görünür (`docs/76`, P0-08 kriter 3).
        $this->media->closeScanJobAsHeld(
            $jobId,
            'Virüs taraması bu ortamda çalışmıyor; dosya taranmadan yayına alınmaz.',
        );
    }
}
