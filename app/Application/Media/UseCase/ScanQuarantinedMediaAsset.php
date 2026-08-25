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

        $result = $this->scanner->scan($claimed->diskPath);

        if ($result->verdict === MediaScanVerdict::Infected) {
            $this->media->markRejectedIfScanning($claimed->workspaceId, $claimed->id);
        } elseif ($result->verdict === MediaScanVerdict::Clean) {
            $this->media->markAcceptedIfScanning($claimed->workspaceId, $claimed->id);
        }
    }
}
