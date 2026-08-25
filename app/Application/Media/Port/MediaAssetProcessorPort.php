<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\MediaProcessingResult;

interface MediaAssetProcessorPort
{
    public function process(string $absolutePath): MediaProcessingResult;
}
