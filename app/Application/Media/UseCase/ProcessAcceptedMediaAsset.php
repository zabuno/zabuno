<?php

declare(strict_types=1);

namespace App\Application\Media\UseCase;

use App\Application\Media\Dto\MediaProcessingOutcome;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\Port\MediaRepositoryPort;

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

        $result = $this->processor->process($claimed->diskPath);

        if ($result->outcome === MediaProcessingOutcome::Succeeded) {
            $this->media->markReadyIfProcessing($claimed->workspaceId, $claimed->id);
        } elseif ($result->outcome === MediaProcessingOutcome::Failed) {
            $this->media->markFailedIfProcessing($claimed->workspaceId, $claimed->id);
        }
    }
}
