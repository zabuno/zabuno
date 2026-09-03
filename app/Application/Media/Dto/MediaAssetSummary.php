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
        /**
         * Durumun İNSANCA açıklaması — yalnız bir şey ters gittiğinde
         * doludur. Sorunsuz bir dosyaya sebep yazmak gürültüdür.
         */
        public readonly ?string $statusReason = null,
        /** Aynı kiracıda, aynı parmak izli daha eski bir varlık — yoksa null (`docs/49` Faz 3). */
        public readonly ?int $duplicateOfId = null,
        /** En küçük hazır rendition'ın değişmez adresi — kütüphane küçük resmi (`docs/49` Faz 4). */
        public readonly ?string $previewUrl = null,
        /** Taslak bağ sayısı (ürün görseli, marka logosu) — silme etki önizlemesi (`docs/49` Faz 5). */
        public readonly int $usageCount = 0,
        public readonly int $versionCount = 0,
        public readonly ?string $originalName = null,
        public readonly int $sizeBytes = 0,
        public readonly ?string $createdAt = null,
        /** draft | active | archived | trashed | purged */
        public readonly string $lifecycle = 'draft',
    ) {}
}
