<?php

declare(strict_types=1);

namespace App\Application\Ai\Port;

use App\Domain\Ai\AiArtifact;

/**
 * Görüntü/PDF → metin + KUTU KOORDİNATLARI.
 *
 * Koordinatlar isteğe bağlı bir süs değil: menü çıkarımının her alanı
 * kaynağını taşımak zorunda (`docs/51` §3.4) ve kaynak, sayfadaki konumdur.
 * Koordinat üretmeyen bir OCR bu yetenek için aday olamaz.
 */
interface OcrPort
{
    public function read(AiRequest $request, string $filePath): AiArtifact;
}
