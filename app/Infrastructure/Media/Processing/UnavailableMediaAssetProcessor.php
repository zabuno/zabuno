<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Processing;

use App\Application\Media\Dto\MediaProcessingOutcome;
use App\Application\Media\Dto\MediaProcessingResult;
use App\Application\Media\Port\MediaAssetProcessorPort;

final class UnavailableMediaAssetProcessor implements MediaAssetProcessorPort
{
    public function process(string $absolutePath): MediaProcessingResult
    {
        return new MediaProcessingResult(MediaProcessingOutcome::Indeterminate);
    }
}
