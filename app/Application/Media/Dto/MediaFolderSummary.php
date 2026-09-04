<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

/**
 * Kenar çubuğundaki tek bir klasör satırı.
 *
 * `fileCount` DOĞRUDAN dosya sayısıdır, alt klasörler dahil değildir:
 * kaynağın kendi verisinde "Ürünler 4" yazarken altındaki "Tatlılar 1"
 * ayrı sayılıyor. Toplayarak göstermek, süzgece tıklayan sahibin gördüğü
 * dosya sayısıyla sayacın uyuşmaması demek olurdu — sayaç 5 der, liste 4
 * dosya gösterir.
 */
final class MediaFolderSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $name,
        /** Kök klasörde `null`; kaynağın `depth: 0` satırları. */
        public readonly ?int $parentId,
        public readonly int $position,
        /** Çöpe atılmamış, doğrudan bu klasördeki dosya sayısı. */
        public readonly int $fileCount = 0,
    ) {}
}
