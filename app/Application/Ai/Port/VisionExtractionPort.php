<?php

declare(strict_types=1);

namespace App\Application\Ai\Port;

use App\Domain\Ai\AiArtifact;

/**
 * Görüntü → yapılandırılmış kayıt.
 *
 * Stage 1 dikeyinin ikinci yarısı: OCR ham metni ve kutuları verir, bu port
 * onlardan kategori/ürün/fiyat ilişkisini kurar ve okuyamadığı alanı
 * UYDURMAZ — `uncertain` ile işaretler.
 */
interface VisionExtractionPort
{
    /** @param list<string> $filePaths Çok sayfalı belge için sırayla */
    public function extract(AiRequest $request, array $filePaths): AiArtifact;
}
