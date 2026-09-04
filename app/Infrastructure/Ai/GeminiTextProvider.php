<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\StructuredGenerationPort;
use App\Application\Platform\Port\AccountRoutingPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\FieldValue;
use App\Domain\Ai\ModelDeployment;
use App\Domain\Platform\Credential\CredentialProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gemini şemaya-bağlı metin üretimi — `docs/96` (Faz 2: ürün açıklaması,
 * çeviri taslağı).
 *
 * DOĞRULAMA UYARISI: bu adaptör gerçek Gemini API'sine karşı DOĞRULANMADI
 * — aynı disiplin: `GeminiVisionProvider` (FF-45), `OpenAiVisionProvider`
 * (FF-41). `Http::fake`'e karşı sınandı.
 *
 * TALİMAT ile VERİ ayrıdır: talimat `systemInstruction`'da, kullanıcı
 * içeriği (ürün adı, kategori adı vb.) `contents[].parts`'ta gider
 * (`docs/16` AI-10). Görsel taşımaz — `GeminiVisionProvider`'dan farkı bu;
 * ikisi ayrı sınıf çünkü istek şekli ve yanıt şeması gerçekten farklı.
 */
final readonly class GeminiTextProvider implements StructuredGenerationPort
{
    private const PROMPT_VERSION = 'gemini.structured-text.v1';

    public function __construct(
        private CredentialResolverPort $credentials,
        private AccountRoutingPort $routing,
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    public function generate(AiRequest $request): AiArtifact
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
        $model = (string) $this->config->get('ai.gemini.text_model', 'gemini-flash-latest');
        $timeout = (int) $this->config->get('ai.gemini.request_timeout', 60);

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $request->instruction]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => $this->userParts($request)],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
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

        if (! is_array($decoded) || ! array_key_exists('description', $decoded)) {
            $this->record($request, $model, 'invalid', 0, 0, 0, 'unparseable');
            throw new ProviderCallException('gemini', 'unparseable');
        }

        $usage = (array) ($json['usageMetadata'] ?? []);
        $inputTokens = (int) ($usage['promptTokenCount'] ?? 0);
        $outputTokens = (int) ($usage['candidatesTokenCount'] ?? 0);
        $cost = $this->cost($model, $inputTokens, $outputTokens);

        $this->record($request, $model, 'succeeded', $inputTokens, $outputTokens, $cost, null);

        $confidence = max(0.0, min(1.0, (float) ($decoded['confidence'] ?? 0.0)));
        $description = trim((string) ($decoded['description'] ?? ''));
        $uncertain = ($decoded['uncertain'] ?? false) === true || $description === '';

        return new AiArtifact(
            capability: $request->capability,
            model: new ModelDeployment('google', 'platform', $model),
            promptVersion: self::PROMPT_VERSION,
            schemaVersion: $request->capability->schemaVersion(),
            fields: [
                new FieldValue('description', $description, $confidence, $uncertain),
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    private function userParts(AiRequest $request): array
    {
        $parts = [];

        foreach ($request->userContent as $key => $value) {
            if ($value === null) {
                continue;
            }
            $parts[] = ['text' => (string) $key.': '.(string) $value];
        }

        if ($parts === []) {
            $parts[] = ['text' => '(no additional context)'];
        }

        return $parts;
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
            'required' => ['description', 'confidence', 'uncertain'],
            'properties' => [
                'description' => ['type' => 'STRING'],
                'confidence' => ['type' => 'NUMBER'],
                'uncertain' => ['type' => 'BOOLEAN'],
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
