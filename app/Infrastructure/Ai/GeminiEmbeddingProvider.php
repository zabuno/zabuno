<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\EmbeddingPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Ai\ModelDeployment;
use App\Domain\Platform\Credential\CredentialProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gemini metin gömme adaptörü — `docs/95`/`docs/96` Faz 2, taksonomi
 * yinelenen-terim tespiti.
 *
 * GEÇİCİ SAĞLAYICI, ONAYLI BİR SAPMA: `docs/51` §4.4 gömmenin YEREL-FIRST
 * (kodlayıcı, `vps-ai` profili) çalışmasını şart koşuyor. Ama yerel çıkarım
 * katmanı bugün YOK (`docs/51` §3.5: `ai-local` sidecar, `queue-worker` —
 * "bugün YOK"). §4.5'in kendi ilkesi bu geçişi meşrulaştırıyor: "yerel model
 * bir SAĞLAYICIDIR, ayrı kod yolu değil — aynı adaptör hem bulut hem yerel
 * için çalışır." Bu yüzden `EmbeddingPort` arayüzü bulut/yerel ayrımını
 * zaten gizliyor; `vps-ai` profili gerçekten kurulduğunda tek değişecek
 * şey `AppServiceProvider`'ın binding kararıdır, bu port değil.
 *
 * DOĞRULAMA UYARISI: gerçek Gemini API'sine karşı doğrulanmadı — aynı
 * disiplin: `GeminiVisionProvider`, `GeminiTextProvider`.
 */
final readonly class GeminiEmbeddingProvider implements EmbeddingPort
{
    public function __construct(
        private CredentialResolverPort $credentials,
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    /**
     * @param  list<string>  $texts
     * @return list<array{vector: list<float>, model: string}>
     */
    public function embed(int $workspaceId, array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $creds = $this->credentials->resolve(CredentialProvider::Gemini);
        $apiKey = (string) ($creds['api_key'] ?? '');

        if ($apiKey === '') {
            throw new ProviderCallException('gemini', 'no-credential');
        }

        $baseUrl = rtrim((string) ($creds['base_url'] ?? 'https://generativelanguage.googleapis.com'), '/');
        $model = (string) $this->config->get('ai.gemini.embedding_model', 'text-embedding-004');
        $timeout = (int) $this->config->get('ai.gemini.request_timeout', 60);

        $payload = [
            'requests' => array_map(
                static fn (string $text): array => [
                    'model' => "models/{$model}",
                    'content' => ['parts' => [['text' => $text]]],
                ],
                $texts,
            ),
        ];

        $client = $this->http->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->timeout($timeout);

        try {
            $response = $client->post("{$baseUrl}/v1beta/models/{$model}:batchEmbedContents", $payload);
        } catch (Throwable) {
            $this->record($workspaceId, $model, 'failed', count($texts), 'network');
            throw new ProviderCallException('gemini', 'network');
        }

        if (! $response->successful()) {
            $this->record($workspaceId, $model, 'failed', count($texts), 'http-'.$response->status());
            throw new ProviderCallException('gemini', 'http-'.$response->status());
        }

        $json = (array) $response->json();
        $embeddings = (array) ($json['embeddings'] ?? []);

        if (count($embeddings) !== count($texts)) {
            $this->record($workspaceId, $model, 'invalid', count($texts), 'unparseable');
            throw new ProviderCallException('gemini', 'unparseable');
        }

        $identity = (new ModelDeployment('google', 'platform', $model))->identity();
        $this->record($workspaceId, $model, 'succeeded', count($texts), null);

        return array_map(
            static fn (array $embedding): array => [
                'vector' => array_map('floatval', (array) ($embedding['values'] ?? [])),
                'model' => $identity,
            ],
            $embeddings,
        );
    }

    private function record(int $workspaceId, string $model, string $outcome, int $textCount, ?string $failureReason): void
    {
        /*
         * Gemini'nin embedContent uç noktası token kullanımını döndürmez
         * (vision/metin uç noktalarının aksine) — bu yüzden maliyet burada
         * ÖLÇÜLEMEZ, uydurulmaz. Girdi sayısı denetim için kaydedilir,
         * kuruş cinsinden maliyet 0 kalır ve bu bilinçli bir boşluktur.
         */
        DB::table('ai_invocations')->insert([
            'workspace_id' => $workspaceId,
            'capability' => 'embedding.text',
            'model_identity' => (new ModelDeployment('google', 'platform', $model))->identity(),
            'outcome' => $outcome,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_minor' => 0,
            'duration_ms' => 0,
            'failure_reason' => $failureReason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
