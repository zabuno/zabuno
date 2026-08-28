<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

final class MediaProcessingResult
{
    /**
     * @param  list<GeneratedRendition>  $renditions
     *
     * `failureReason` SAHİBİN okuyacağı cümledir. Boş bırakılırsa kullanıcı
     * "yükledim ama bir şey olmadı" ile baş başa kalır (`docs/76`).
     */
    public function __construct(
        public readonly MediaProcessingOutcome $outcome,
        public readonly array $renditions = [],
        public readonly ?string $failureReason = null,
        public readonly ?int $sourceWidth = null,
        public readonly ?int $sourceHeight = null,
    ) {}

    /** @param  list<GeneratedRendition>  $renditions */
    public static function succeeded(array $renditions, int $sourceWidth, int $sourceHeight): self
    {
        return new self(MediaProcessingOutcome::Succeeded, $renditions, null, $sourceWidth, $sourceHeight);
    }

    public static function failed(string $reason): self
    {
        return new self(MediaProcessingOutcome::Failed, [], $reason);
    }
}
