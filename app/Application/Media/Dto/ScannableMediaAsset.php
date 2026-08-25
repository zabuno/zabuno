<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

final class ScannableMediaAsset
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $diskPath,
    ) {}
}
