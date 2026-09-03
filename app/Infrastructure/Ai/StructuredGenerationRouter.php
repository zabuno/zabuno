<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\StructuredGenerationPort;
use App\Domain\Ai\AiArtifact;

/**
 * Canlı yedek zinciri — `StructuredGenerationPort` için `VisionExtractionRouter`
 * ile aynı desen (`docs/97` R10-R12). Bugün tek aday (Gemini) olsa da, Faz 3'te
 * Anthropic/OpenAI metin adaptörleri eklendiğinde kod değişmeden genişler.
 */
final readonly class StructuredGenerationRouter implements StructuredGenerationPort
{
    /** @param list<StructuredGenerationPort> $candidates öncelik sırasında */
    public function __construct(private array $candidates) {}

    public function generate(AiRequest $request): AiArtifact
    {
        $lastFailure = null;

        foreach ($this->candidates as $index => $candidate) {
            try {
                $artifact = $candidate->generate($request);

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
