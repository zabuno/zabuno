<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Dto;

final class QrRenderedImage
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $mimeType,
    ) {}
}
