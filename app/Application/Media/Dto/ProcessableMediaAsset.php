<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

final class ProcessableMediaAsset
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $diskPath,
        // Türev ölçüleri SLOTUN kuralıdır: aynı fotoğraf logoda kırpılmaz,
        // ürün kartında 1:1 kırpılır (`config/media-slots.php`).
        public readonly string $slot = '',
    ) {}
}
