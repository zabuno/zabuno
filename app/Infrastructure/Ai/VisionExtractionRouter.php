<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Domain\Ai\AiArtifact;

/**
 * Canlı yedek zinciri — `docs/97` R10-R12.
 *
 * `docs/16` AI-01/AIV-02'nin "Containment" hücresi "Fallback provider
 * tanımlı" diyordu. Bu doğru DEĞİLDİ: adaylar yalnız bağlanma anında
 * (`AppServiceProvider`) hangisinin yapılandırıldığına göre statik seçiliyordu
 * — biri çalışma zamanında başarısız olursa istek orada biterdi. Bu sınıf
 * o boşluğu kapatır: adaylar SIRAYLA denenir, biri `ProviderCallException`
 * fırlatırsa aynı istek bir sonrakine gider.
 *
 * `docs/51` §4b.1 sırası (Gemini → OpenAI → Claude) burada bir DAVRANIŞ
 * kararı değil, `$candidates` dizisinin sırasıdır — kod sırayı bilmez,
 * yalnız listeyi tüketir.
 *
 * Sessiz geçiş YOK (`docs/51` UNK-03): ilk aday dışında biri cevap verirse
 * sonuç `usedFallback: true` ile işaretlenir.
 */
final readonly class VisionExtractionRouter implements VisionExtractionPort
{
    /** @param list<VisionExtractionPort> $candidates öncelik sırasında */
    public function __construct(private array $candidates) {}

    public function extract(AiRequest $request, array $filePaths): AiArtifact
    {
        $lastFailure = null;

        foreach ($this->candidates as $index => $candidate) {
            try {
                $artifact = $candidate->extract($request, $filePaths);

                return $index === 0 ? $artifact : $this->markFallback($artifact);
            } catch (ProviderCallException $exception) {
                $lastFailure = $exception;

                continue;
            }
        }

        throw $lastFailure ?? new ProviderCallException('none', 'no-candidates');
    }

    private function markFallback(AiArtifact $artifact): AiArtifact
    {
        return new AiArtifact(
            capability: $artifact->capability,
            model: $artifact->model,
            promptVersion: $artifact->promptVersion,
            schemaVersion: $artifact->schemaVersion,
            fields: $artifact->fields,
            usedFallback: true,
        );
    }
}
