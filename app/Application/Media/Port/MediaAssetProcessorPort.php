<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\MediaProcessingResult;

interface MediaAssetProcessorPort
{
    /**
     * Türev ölçüleri SLOTUN kuralıdır; işleyici hangi yerde kullanılacağını
     * bilmeden doğru kırpma yapamaz (`config/media-slots.php`).
     *
     * `$targetFormat` DÖNÜŞTÜRME içindir (`docs/108` §6.3) ve varsayılanı
     * `null`dır: çağıranların çoğu biçimi seçmez, işleyicinin kendi
     * kararına bırakır. İkinci bir işleme hattı açmamak için burada duruyor
     * — dönüştürme, var olan yeniden üretim hattının biçim seçilmiş
     * hâlidir. Ayrı bir hat, "asıl korunuyor mu / sürüm açılıyor mu / iş
     * kaydı düşüyor mu" sorularını iki ayrı yerde cevaplamak olurdu.
     */
    public function process(string $absolutePath, string $slot = '', ?string $targetFormat = null): MediaProcessingResult;
}
