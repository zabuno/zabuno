<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\MediaProcessingResult;

interface MediaAssetProcessorPort
{
    /**
     * Türev ölçüleri SLOTUN kuralıdır; işleyici hangi yerde kullanılacağını
     * bilmeden doğru kırpma yapamaz (`config/media-slots.php`).
     */
    public function process(string $absolutePath, string $slot = ''): MediaProcessingResult;
}
