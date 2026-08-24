<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

final class MediaAssetSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $altText,
        public readonly string $slot,
        public readonly string $status,
    ) {}
}
