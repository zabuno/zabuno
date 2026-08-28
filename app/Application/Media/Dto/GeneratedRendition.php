<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

/**
 * İşleyicinin ürettiği tek bir türev — henüz kaydedilmemiş hâli.
 *
 * Baytlar burada taşınır, dosyaya YAZILMAZ: yazma kararı deponundur.
 * İşleyici görüntü bilir, depo bilmez; depo depolama bilir, görüntü bilmez.
 */
final class GeneratedRendition
{
    public function __construct(
        public readonly string $profile,
        public readonly int $width,
        public readonly int $height,
        public readonly string $format,
        public readonly string $mimeType,
        public readonly string $bytes,
    ) {}
}
