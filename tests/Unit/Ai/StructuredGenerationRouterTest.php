<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\StructuredGenerationPort;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\Capability;
use App\Domain\Ai\FieldValue;
use App\Domain\Ai\ModelDeployment;
use App\Infrastructure\Ai\StructuredGenerationRouter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TEXT-FALLBACK — `VisionExtractionRouterTest` ile aynı desen, StructuredGenerationPort için.
 */
final class StructuredGenerationRouterTest extends TestCase
{
    private function stubProvider(?AiArtifact $result, ?ProviderCallException $failure = null): StructuredGenerationPort
    {
        return new class($result, $failure) implements StructuredGenerationPort
        {
            public int $calls = 0;

            public function __construct(private readonly ?AiArtifact $result, private readonly ?ProviderCallException $failure) {}

            public function generate(AiRequest $request): AiArtifact
            {
                $this->calls++;
                if ($this->failure !== null) {
                    throw $this->failure;
                }

                return $this->result;
            }
        };
    }

    private function artifact(string $provider): AiArtifact
    {
        return new AiArtifact(
            capability: Capability::ProductDescription,
            model: new ModelDeployment($provider, 'platform', 'test-model'),
            promptVersion: 'test.v1',
            schemaVersion: Capability::ProductDescription->schemaVersion(),
            fields: [new FieldValue('description', 'x', 0.9, false)],
        );
    }

    #[Test]
    public function a_failed_first_candidate_falls_through_and_the_result_is_marked(): void
    {
        $first = $this->stubProvider(null, new ProviderCallException('gemini', 'http-500'));
        $second = $this->stubProvider($this->artifact('openai'));

        $router = new StructuredGenerationRouter([$first, $second]);
        $artifact = $router->generate(new AiRequest(Capability::ProductDescription, 1, 'yaz'));

        self::assertSame('openai', $artifact->model->provider);
        self::assertTrue($artifact->usedFallback);
    }

    #[Test]
    public function when_every_candidate_fails_the_last_failure_is_thrown(): void
    {
        $only = $this->stubProvider(null, new ProviderCallException('gemini', 'network'));
        $router = new StructuredGenerationRouter([$only]);

        $this->expectException(ProviderCallException::class);
        $router->generate(new AiRequest(Capability::ProductDescription, 1, 'yaz'));
    }
}
