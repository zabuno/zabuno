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
 * Gemini görüntü → menü satırı adaptörü — `docs/96` (Faz 2, öncelikli).
 *
 * DOĞRULAMA UYARISI: bu adaptör gerçek Gemini API'sine karşı DOĞRULANMADI.
 * İsteğin şekli Google'ın belgelenmiş `generateContent` + yapılandırılmış
 * JSON çıktı biçimine göre yazıldı ve `Http::fake` ile sınandı. İlk gerçek
 * çağrıyı anahtarı olan kişi tek sayfalık bir denemesiyle yapmalı — kod
 * yeşil olması "çalışıyor" anlamına gelmez (aynı disiplin: `OpenAiVisionProvider`,
 * FF-41, `docs/94`).
 *
 * Neden BİRİNCİ aday: `docs/51` §4b.1 görme zincirini Gemini→OpenAI→Claude
 * sıralıyor — ucuz, güçlü. Bu yüzden `AppServiceProvider` binding'i Gemini'yi
 * OpenAI'dan önce dener.
 *
 * TALİMAT ile VERİ tür düzeyinde ayrı: talimat `systemInstruction`'da, görsel
 * ve kullanıcı içeriği `contents[].parts`'ta gider (`docs/16` AI-10).
 */
final readonly class GeminiVisionProvider implements VisionExtractionPort
{
    private const PROMPT_VERSION = 'gemini.menu-extract.v1';

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
        $resolved = $this->credentials->resolveFor($request->workspaceId, CredentialProvider::Gemini, (string) ($request->options['purpose'] ?? 'interactive'));
        $creds = $resolved->values;
        $connectionId = $resolved->connectionId;
        $apiKey = (string) ($creds['api_key'] ?? '');

        if ($apiKey === '') {
            throw new ProviderCallException('gemini', 'no-credential');
        }

        $baseUrl = rtrim((string) ($creds['base_url'] ?? 'https://generativelanguage.googleapis.com'), '/');
        $model = (string) $this->config->get('ai.gemini.vision_model', 'gemini-flash-latest');
        $timeout = (int) $this->config->get('ai.gemini.request_timeout', 60);

        $payload = [
            // TALİMAT kanalı — kullanıcı/veri içeriğinden ayrı.
            'systemInstruction' => [
                'parts' => [['text' => $request->instruction]],
            ],
            // VERİ kanalı: görseller + (varsa) kullanıcı içeriği.
            'contents' => [
                ['role' => 'user', 'parts' => $this->userParts($request, $filePaths)],
            ],
            'generationConfig' => [
                'temperature' => 0,
                'responseMimeType' => 'application/json',
                'responseSchema' => self::responseSchema(),
            ],
        ];

        $client = $this->http->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->timeout($timeout);

        try {
            $response = $client->post("{$baseUrl}/v1beta/models/{$model}:generateContent", $payload);
        } catch (Throwable) {
            $this->record($request, $model, 'failed', 0, 0, 0, 'network');
            $this->dropIfAccountFault($connectionId, ProviderHealthVerdict::networkFailureDropsAccount());
            throw new ProviderCallException('gemini', 'network');
        }

        if (! $response->successful()) {
            $this->record($request, $model, 'failed', 0, 0, 0, 'http-'.$response->status());
            $this->dropIfAccountFault(
                $connectionId,
                ProviderHealthVerdict::httpStatusDropsAccount($response->status()),
            );
            throw new ProviderCallException('gemini', 'http-'.$response->status());
        }

        if ($connectionId !== null) {
            $this->routing->markHealthy($connectionId);
        }

        $json = (array) $response->json();
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $decoded = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($decoded) || ! isset($decoded['rows']) || ! is_array($decoded['rows'])) {
            $this->record($request, $model, 'invalid', 0, 0, 0, 'unparseable');
            throw new ProviderCallException('gemini', 'unparseable');
        }

        $usage = (array) ($json['usageMetadata'] ?? []);
        $inputTokens = (int) ($usage['promptTokenCount'] ?? 0);
        $outputTokens = (int) ($usage['candidatesTokenCount'] ?? 0);
        $cost = $this->cost($model, $inputTokens, $outputTokens);

        $this->record($request, $model, 'succeeded', $inputTokens, $outputTokens, $cost, null);

        return new AiArtifact(
            capability: $request->capability,
            model: new ModelDeployment('google', 'platform', $model),
            promptVersion: self::PROMPT_VERSION,
            schemaVersion: $request->capability->schemaVersion(),
            fields: $this->mapRows($decoded['rows'], $filePaths),
        );
    }

    /**
     * @param  list<string>  $filePaths
     * @return list<array<string, mixed>>
     */
    private function userParts(AiRequest $request, array $filePaths): array
    {
        $parts = [[
            'text' => 'The following pages are a printed menu. Extract only what is printed; do not invent a price you cannot read.',
        ]];

        foreach ($filePaths as $path) {
            $bytes = @file_get_contents($path);
            if ($bytes === false) {
                throw new ProviderCallException('gemini', 'unreadable-file');
            }

            $mime = $this->mime($path, $bytes);
            $parts[] = [
                'inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)],
            ];
        }

        foreach ($request->userContent as $key => $value) {
            if ($value === null) {
                continue;
            }
            $parts[] = ['text' => (string) $key.': '.(string) $value];
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
            'model_identity' => (new ModelDeployment('google', 'platform', $model))->identity(),
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
            'type' => 'OBJECT',
            'required' => ['rows'],
            'properties' => [
                'rows' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'required' => ['category', 'product', 'priceMinorAmount', 'currencyCode', 'confidence', 'uncertain'],
                        'properties' => [
                            'category' => ['type' => 'STRING'],
                            'product' => ['type' => 'STRING'],
                            'priceMinorAmount' => ['type' => 'INTEGER', 'nullable' => true],
                            'currencyCode' => ['type' => 'STRING'],
                            'confidence' => ['type' => 'NUMBER'],
                            'uncertain' => ['type' => 'BOOLEAN'],
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
