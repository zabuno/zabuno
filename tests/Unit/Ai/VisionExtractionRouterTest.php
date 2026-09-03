<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\Capability;
use App\Domain\Ai\FieldValue;
use App\Domain\Ai\ModelDeployment;
use App\Infrastructure\Ai\VisionExtractionRouter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * VISION-FALLBACK — canlı yedek zinciri (`docs/97` R10-R12).
 *
 * `docs/16` AI-01/AIV-02'nin "Containment" hücresi "Fallback provider
 * tanımlı" diyordu — bağlanma anında statik seçimdi, çalışma zamanında
 * canlı yeniden deneme YOKTU. Bu test o iddiayı gerçek yapıyor: birinci
 * aday çalışma zamanında başarısız olursa, aynı istek ikinciye gider.
 */
final class VisionExtractionRouterTest extends TestCase
{
    private function stubProvider(?AiArtifact $result, ?ProviderCallException $failure = null): VisionExtractionPort
    {
        return new class($result, $failure) implements VisionExtractionPort
        {
            public int $calls = 0;

            public function __construct(private readonly ?AiArtifact $result, private readonly ?ProviderCallException $failure) {}

            public function extract(AiRequest $request, array $filePaths): AiArtifact
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
            capability: Capability::MenuExtract,
            model: new ModelDeployment($provider, 'platform', 'test-model'),
            promptVersion: 'test.v1',
            schemaVersion: Capability::MenuExtract->schemaVersion(),
            fields: [new FieldValue('row.1', ['category' => 'X'], 0.9, false)],
        );
    }

    private function request(): AiRequest
    {
        return new AiRequest(Capability::MenuExtract, 1, 'çıkar');
    }

    // --- VISION-FALLBACK-FIRST-SUCCEEDS-01 ---------------------------------

    #[Test]
    public function the_first_candidate_answers_and_carries_no_fallback_flag(): void
    {
        $first = $this->stubProvider($this->artifact('gemini'));
        $second = $this->stubProvider($this->artifact('openai'));

        $router = new VisionExtractionRouter([$first, $second]);
        $artifact = $router->extract($this->request(), []);

        self::assertSame('gemini', $artifact->model->provider);
        self::assertFalse($artifact->usedFallback);
        self::assertSame(0, $second->calls);
    }

    // --- VISION-FALLBACK-RETRIES-NEXT-01 ------------------------------------

    #[Test]
    public function a_failed_first_candidate_falls_through_to_the_second_and_the_result_is_marked(): void
    {
        $first = $this->stubProvider(null, new ProviderCallException('gemini', 'http-500'));
        $second = $this->stubProvider($this->artifact('openai'));

        $router = new VisionExtractionRouter([$first, $second]);
        $artifact = $router->extract($this->request(), []);

        self::assertSame('openai', $artifact->model->provider);
        self::assertTrue($artifact->usedFallback, 'İkinci adaydan gelen sonuç yedek olarak işaretlenmeli.');
        self::assertSame(1, $first->calls);
        self::assertSame(1, $second->calls);
    }

    // --- VISION-FALLBACK-EXHAUSTED-01 ---------------------------------------

    #[Test]
    public function when_every_candidate_fails_the_last_failure_is_thrown(): void
    {
        $first = $this->stubProvider(null, new ProviderCallException('gemini', 'http-500'));
        $second = $this->stubProvider(null, new ProviderCallException('openai', 'network'));

        $router = new VisionExtractionRouter([$first, $second]);

        try {
            $router->extract($this->request(), []);
            self::fail('ProviderCallException bekleniyordu.');
        } catch (ProviderCallException $exception) {
            self::assertSame('openai', $exception->provider);
            self::assertSame('network', $exception->reason);
        }
    }

    // --- VISION-FALLBACK-NO-CANDIDATES-01 -----------------------------------

    #[Test]
    public function an_empty_candidate_list_fails_closed(): void
    {
        $router = new VisionExtractionRouter([]);

        $this->expectException(ProviderCallException::class);
        $router->extract($this->request(), []);
    }
}
