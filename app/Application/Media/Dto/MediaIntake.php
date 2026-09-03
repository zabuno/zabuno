<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

final class MediaIntake
{
    public function __construct(
        public readonly string $temporaryPath,
        public readonly string $originalName,
        public readonly string $detectedMimeType,
        public readonly int $sizeBytes,
        public readonly string $altText,
        public readonly string $slot,
        /** İstemcinin ürettiği yeniden-deneme anahtarı; yoksa null (`docs/49` Faz 2). */
        public readonly ?string $idempotencyKey = null,
    ) {}
}
