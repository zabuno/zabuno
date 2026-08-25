<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

final class MediaScanResult
{
    public function __construct(
        public readonly MediaScanVerdict $verdict,
    ) {}
}
