<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

final class MediaProcessingResult
{
    public function __construct(
        public readonly MediaProcessingOutcome $outcome,
    ) {}
}
