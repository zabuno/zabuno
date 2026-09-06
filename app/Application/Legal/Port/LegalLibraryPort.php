<?php

declare(strict_types=1);

namespace App\Application\Legal\Port;

use App\Domain\Legal\LegalDocument;

/**
 * Yasal belgelerin kaynağı — FF-198.
 *
 * Bir port, çünkü metin bugün kodda yaşıyor ama orada kalmak zorunda
 * değil: hukukçu incelemesi sonrası metin bir tabloya ya da dosyaya
 * taşınırsa değişecek tek şey bu portun arkasıdır. `ContentLibraryPort`
 * ile aynı gerekçe.
 */
interface LegalLibraryPort
{
    /** Belge yoksa `null` — bir hata değil, bir DURUM. */
    public function find(string $key): ?LegalDocument;

    /** @return list<LegalDocument> */
    public function all(): array;
}
