<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Processing;

use App\Application\Media\Dto\MediaProcessingOutcome;
use App\Application\Media\Dto\MediaProcessingResult;
use App\Application\Media\Port\MediaAssetProcessorPort;

/**
 * Görsel işleme YOK demenin dürüst yolu.
 *
 * Bu adaptör artık üretimin varsayılanı DEĞİL (`docs/76`): bir ürünün
 * yüklenen her fotoğrafı sonsuza kadar bekletmesi "güvenli varsayılan"
 * değil, sessizce bozuk olmaktır. GD bulunmayan bir ortamda yedek olarak
 * kalır ve orada da "belirsiz" der — yani sahibi yanıltmaz.
 */
final class UnavailableMediaAssetProcessor implements MediaAssetProcessorPort
{
    public function process(string $absolutePath, string $slot = '', ?string $targetFormat = null): MediaProcessingResult
    {
        return new MediaProcessingResult(MediaProcessingOutcome::Indeterminate);
    }
}
