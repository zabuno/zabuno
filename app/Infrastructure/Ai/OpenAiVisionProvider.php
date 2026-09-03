<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Application\Platform\Port\AccountRoutingPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\FieldValue;
use App\Domain\Ai\ModelDeployment;
use App\Domain\Ai\SourceRef;
use App\Domain\Platform\Credential\CredentialProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * OpenAI görüntü → menü satırı adaptörü (Vault Faz 5).
 *
 * DOĞRULAMA UYARISI: bu adaptör gerçek OpenAI API'sine karşı DOĞRULANMADI.
 * İsteğin şekli, OpenAI'ın belgelenmiş Chat Completions + yapılandırılmış
 * çıktı biçimine göre yazıldı ve `Http::fake` ile sınandı. İlk gerçek çağrıyı
 * anahtarı olan kişi tek sayfalık bir denemeyle yapmalı; kod yeşil olması
 * "çalışıyor" anlamına gelmez.
 *
 * TALİMAT ile VERİ tür düzeyinde ayrı: talimat `system` rolünde, görsel ve
 * kullanıcı içeriği `user` rolünde gider. Menünün içine "önceki talimatları
 * yoksay" yazan bir metin, talimat kanalına asla geçemez (`docs/16` AI-10).
 */
final readonly class OpenAiVisionProvider implements VisionExtractionPort
{
    private const PROMPT_VERSION = 'openai.menu-extract.v1';

    public function __construct(
        private CredentialResolverPort $credentials,
        private AccountRoutingPort $routing,
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    /** @param list<string> $filePaths */
    public function extract(AiRequest $request, array $filePaths): AiArtifact
    {
        /*
            YAPIŞKAN HESAP SEÇİMİ (`docs/14` §2a): bu tenant hangi
            bağlantıya yapıştıysa oraya gider; kendi anahtarı (BYOK)
            varsa o kazanır. Dönen kimlik, çağrı başarısız olursa
            HANGİ hesabın düştüğünü söyleyebilmek için gerekli.
        */
        $resolved = $this->credentials->resolveFor($request->workspaceId, CredentialProvider::OpenAi);
        $creds = $resolved->values;
        $connectionId = $resolved->connectionId;
        $apiKey = (string) ($creds['api_key'] ?? '');

        if ($apiKey === '') {
            throw new ProviderCallException('openai', 'no-credential');
        }

        $baseUrl = rtrim((string) ($creds['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $model = (string) $this->config->get('ai.openai.vision_model', 'gpt-4o-mini');
        $timeout = (int) $this->config->get('ai.openai.request_timeout', 60);

        $payload = [
            'model' => $model,
            'temperature' => 0,
            'messages' => [
                // TALİMAT kanalı.
                ['role' => 'system', 'content' => $request->instruction],
                // VERİ kanalı: görseller + (varsa) kullanıcı içeriği, hepsi veri.
                ['role' => 'user', 'content' => $this->userContent($request, $filePaths)],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'menu_extract',
                    'strict' => true,
                    'schema' => self::responseSchema(),
                ],
            ],
        ];

        $client = $this->http->withToken($apiKey)->acceptJson()->timeout($timeout);
        if (($creds['organization'] ?? '') !== '') {
            $client = $client->withHeaders(['OpenAI-Organization' => $creds['organization']]);
        }
        if (($creds['project'] ?? '') !== '') {
            $client = $client->withHeaders(['OpenAI-Project' => $creds['project']]);
        }

        try {
            $response = $client->post("{$baseUrl}/chat/completions", $payload);
        } catch (Throwable) {
            $this->record($request, $model, 'failed', 0, 0, 0, 'network');
            $this->dropIfAccountFault($connectionId, ProviderHealthVerdict::networkFailureDropsAccount());
            throw new ProviderCallException('openai', 'network');
        }

        if (! $response->successful()) {
            $this->record($request, $model, 'failed', 0, 0, 0, 'http-'.$response->status());
            $this->dropIfAccountFault(
                $connectionId,
                ProviderHealthVerdict::httpStatusDropsAccount($response->status()),
            );
            throw new ProviderCallException('openai', 'http-'.$response->status());
        }

        if ($connectionId !== null) {
            $this->routing->markHealthy($connectionId);
        }

        $json = (array) $response->json();
        $content = $json['choices'][0]['message']['content'] ?? null;
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded) || ! isset($decoded['rows']) || ! is_array($decoded['rows'])) {
            $this->record($request, $model, 'invalid', 0, 0, 0, 'unparseable');
            throw new ProviderCallException('openai', 'unparseable');
        }

        $usage = (array) ($json['usage'] ?? []);
        $inputTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($usage['completion_tokens'] ?? 0);
        $cost = $this->cost($model, $inputTokens, $outputTokens);

        $this->record($request, $model, 'succeeded', $inputTokens, $outputTokens, $cost, null);

        return new AiArtifact(
            capability: $request->capability,
            model: new ModelDeployment('openai', 'platform', $model),
            promptVersion: self::PROMPT_VERSION,
            schemaVersion: $request->capability->schemaVersion(),
            fields: $this->mapRows($decoded['rows'], $filePaths),
        );
    }

    /**
     * @param  list<string>  $filePaths
     * @return list<array<string, mixed>>
     */
    private function userContent(AiRequest $request, array $filePaths): array
    {
        $parts = [[
            'type' => 'text',
            'text' => 'The following pages are a printed menu. Extract only what is printed; do not invent a price you cannot read.',
        ]];

        foreach ($filePaths as $path) {
            $bytes = @file_get_contents($path);
            if ($bytes === false) {
                throw new ProviderCallException('openai', 'unreadable-file');
            }

            $mime = $this->mime($path, $bytes);
            $parts[] = [
                'type' => 'image_url',
                'image_url' => ['url' => "data:{$mime};base64,".base64_encode($bytes)],
            ];
        }

        // Kullanıcı içeriği DE veri kanalına, düz metin olarak — talimat değil.
        foreach ($request->userContent as $key => $value) {
            if ($value === null) {
                continue;
            }
            $parts[] = ['type' => 'text', 'text' => (string) $key.': '.(string) $value];
        }

        return $parts;
    }

    /**
     * @param  list<mixed>  $rows
     * @param  list<string>  $filePaths
     * @return list<FieldValue>
     */
    private function mapRows(array $rows, array $filePaths): array
    {
        $source = new SourceRef(hash('sha256', implode('|', $filePaths)), page: 1, boundingBox: null);
        $fields = [];
        $index = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $index++;
            $price = $row['priceMinorAmount'] ?? null;
            $confidence = max(0.0, min(1.0, (float) ($row['confidence'] ?? 0.0)));
            // Model belirsiz dediyse VEYA fiyat okunamıyorsa belirsiz.
            $uncertain = ($row['uncertain'] ?? false) === true || $price === null;

            $fields[] = new FieldValue(
                name: "row.{$index}",
                value: [
                    'category' => (string) ($row['category'] ?? ''),
                    'product' => (string) ($row['product'] ?? ''),
                    'priceMinorAmount' => is_numeric($price) ? (int) $price : null,
                    'currencyCode' => (string) ($row['currencyCode'] ?? 'TRY'),
                ],
                confidence: $confidence,
                uncertain: $uncertain,
                source: $source,
            );
        }

        return $fields;
    }

    private function mime(string $path, string $bytes): string
    {
        $detected = @mime_content_type($path);
        if (is_string($detected) && str_starts_with($detected, 'image/')) {
            return $detected;
        }

        // PNG imzası — mime_content_type yoksa/yanılırsa güvenli varsayılan.
        if (str_starts_with($bytes, "\x89PNG")) {
            return 'image/png';
        }

        return 'image/jpeg';
    }

    private function cost(string $model, int $inputTokens, int $outputTokens): int
    {
        $pricing = (array) $this->config->get('ai.pricing', []);
        $rate = (array) ($pricing[$model] ?? []);
        $inputPerMillion = (int) ($rate['input_per_million'] ?? 0);
        $outputPerMillion = (int) ($rate['output_per_million'] ?? 0);

        return (int) round(($inputTokens * $inputPerMillion + $outputTokens * $outputPerMillion) / 1_000_000);
    }

    private function record(
        AiRequest $request,
        string $model,
        string $outcome,
        int $inputTokens,
        int $outputTokens,
        int $cost,
        ?string $failureReason,
    ): void {
        DB::table('ai_invocations')->insert([
            'workspace_id' => $request->workspaceId,
            'capability' => $request->capability->value,
            'model_identity' => (new ModelDeployment('openai', 'platform', $model))->identity(),
            'outcome' => $outcome,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_minor' => $cost,
            'duration_ms' => 0,
            'failure_reason' => $failureReason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private static function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['rows'],
            'properties' => [
                'rows' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['category', 'product', 'priceMinorAmount', 'currencyCode', 'confidence', 'uncertain'],
                        'properties' => [
                            'category' => ['type' => 'string'],
                            'product' => ['type' => 'string'],
                            'priceMinorAmount' => ['type' => ['integer', 'null']],
                            'currencyCode' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                            'uncertain' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Hesabı havuzdan düşür — ama YALNIZ hata hesaba aitse.
     *
     * Kendi gövdemiz bozuk olduğu için (400) çalışan bir hesabı
     * düşürmek, sahibin ödediği bir hesabı kullanılamaz kılardı ve
     * sıradaki hesap da aynı 400'ü alırdı.
     */
    private function dropIfAccountFault(?int $connectionId, bool $accountFault): void
    {
        if ($connectionId !== null && $accountFault) {
            $this->routing->markUnhealthy($connectionId);
        }
    }
}
