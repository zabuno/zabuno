<?php

declare(strict_types=1);

namespace App\Application\Ai\Port;

use App\Domain\Ai\AiArtifact;

/**
 * Şemaya bağlı metin üretimi.
 *
 * Serbest metin döndürmez: her yetenek bir JSON şemasına bağlıdır ve şemaya
 * uymayan cevap BAŞARISIZDIR (`docs/51` UNK-02). Aynı prompt farklı modelde
 * farklı şema döndürür; doğrulama olmadan bu, arayüzde sessiz bozulmadır.
 */
interface StructuredGenerationPort
{
    public function generate(AiRequest $request): AiArtifact;
}
