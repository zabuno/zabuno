<?php

declare(strict_types=1);

namespace App\Domain\Content\Block;

use App\Domain\Content\PageContentException;

/**
 * Şablondaki tek bir bölüm — yönerge §15.
 */
final class ContentBlock
{
    /** @param  list<BlockEntry>  $entries */
    public function __construct(
        public readonly BlockType $type,
        public readonly ?string $heading,
        public readonly array $entries,
    ) {
        if ($this->entries === []) {
            // Başlığı olup içeriği olmayan bir bölüm, ince içeriktir: sayfayı
            // uzatır, hiçbir soruya cevap vermez.
            throw new PageContentException("Boş blok: {$this->type->value}.");
        }

        $hasHeading = $this->heading !== null && trim($this->heading) !== '';

        if ($this->type->needsHeading() && ! $hasHeading) {
            // Başlıksız bölüm, başlık hiyerarşisinde delik açar; ekran okuyucu
            // kullanıcısı sayfayı başlıklarla gezer (yönerge §20, erişilebilirlik).
            throw new PageContentException("Başlıksız blok: {$this->type->value}.");
        }

        if (! $this->type->needsHeading() && $hasHeading) {
            throw new PageContentException("Bu blok başlık taşımaz: {$this->type->value}.");
        }
    }
}
