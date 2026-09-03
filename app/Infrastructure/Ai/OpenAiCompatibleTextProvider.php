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
 * OpenAI-UYUMLU metin üretimi — Kimi ve "özel uç nokta" (`docs/96` Faz 3).
 *
 * TEK SINIF, İKİ SAĞLAYICI — çünkü fark yalnız adres ve anahtar. Kimi de,
 * kendi barındırılan bir Qwen/vLLM sunucusu da `/chat/completions` konuşur;
 * ikisi için ayrı sınıf yazmak aynı kodu iki kez bakımda tutmak olurdu.
 * Anthropic ise ayrı bir sınıftır ve olmalıdır: Messages API bu biçimle
 * uyumlu DEĞİL (talimat ayrı alanda, `max_tokens` zorunlu, yanıt blok
 * dizisi).
 *
 * UYUMLULUK VARSAYILMAZ. `docs/51` §4.5 açık: özel bir uç noktanın tam
 * uyumluluğu garanti değildir. Bu yüzden bu adaptör iyimser davranmaz —
 * beklenmeyen bir yanıt şekli `unparseable` ile reddedilir ve sağlayıcı
 * zinciri bir sonraki adaya geçer; "belki çalışmıştır" diye yarım bir
 * sonuç ÜRETİLMEZ.
 *
 * DOĞRULAMA UYARISI: gerçek Kimi/Qwen uç noktasına karşı DOĞRULANMADI —
 * `Http::fake` ile sınandı (aynı disiplin: `docs/94`).
 */
final readonly class OpenAiCompatibleTextProvider implements StructuredGenerationPort
{
    private const PROMPT_VERSION = 'openai-compatible.structured-text.v1';

    public function __construct(
        private CredentialProvider $provider,
        private CredentialResolverPort $credentials,
        private AccountRoutingPort $routing,
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    public function generate(AiRequest $request): AiArtifact
    {
        $resolved = $this->credentials->resolveFor($request->workspaceId, $this->provider, (string) ($request->options['purpose'] ?? 'interactive'));
        $creds = $resolved->values;
        $connectionId = $resolved->connectionId;

        $baseUrl = rtrim((string) ($creds['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            // Özel uç noktada adres ZORUNLU; onsuz nereye gideceğimiz belli
            // değil ve "varsayılan bir adres" uydurmak sessizce yanlış bir
            // sunucuya çağrı yapmak olurdu.
            throw new ProviderCallException($this->provider->value, 'no-credential');
        }

        $apiKey = (string) ($creds['api_key'] ?? '');
        $model = (string) $this->config->get("ai.{$this->provider->value}.text_model", '');

        if ($model === '') {
            throw new ProviderCallException($this->provider->value, 'no-model');
        }

        $timeout = (int) $this->config->get("ai.{$this->provider->value}.request_timeout", 60);

        $payload = [
            'model' => $model,
            'temperature' => 0.4,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                // TALİMAT kanalı — kullanıcı içeriğinden ayrı rol.
                ['role' => 'system', 'content' => $request->instruction."\n\n".self::schemaInstruction()],
                ['role' => 'user', 'content' => $this->userText($request)],
            ],
        ];

        $client = $this->http->acceptJson()->timeout($timeout);

        // Anahtar OPSİYONEL: kendi barındırılan bir sunucu ağ sınırında
        // korunuyor olabilir ve anahtar istemez.
        if ($apiKey !== '') {
            $client = $client->withToken($apiKey);
        }

        try {
            $response = $client->post("{$baseUrl}/chat/completions", $payload);
        } catch (Throwable) {
            $this->record($request, $model, 'failed', 0, 0, 0, 'network');
            $this->dropIfAccountFault($connectionId, ProviderHealthVerdict::networkFailureDropsAccount());
            throw new ProviderCallException($this->provider->value, 'network');
        }

        if (! $response->successful()) {
            $this->record($request, $model, 'failed', 0, 0, 0, 'http-'.$response->status());
            $this->dropIfAccountFault(
                $connectionId,
                ProviderHealthVerdict::httpStatusDropsAccount($response->status()),
            );
            throw new ProviderCallException($this->provider->value, 'http-'.$response->status());
        }

        if ($connectionId !== null) {
            $this->routing->markHealthy($connectionId);
        }

        $json = (array) $response->json();
        $content = $json['choices'][0]['message']['content'] ?? null;
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded) || ! array_key_exists('description', $decoded)) {
            $this->record($request, $model, 'invalid', 0, 0, 0, 'unparseable');
            throw new ProviderCallException($this->provider->value, 'unparseable');
        }

        $usage = (array) ($json['usage'] ?? []);
        $inputTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($usage['completion_tokens'] ?? 0);
        $cost = $this->cost($model, $inputTokens, $outputTokens);

        $this->record($request, $model, 'succeeded', $inputTokens, $outputTokens, $cost, null);

        $description = trim((string) ($decoded['description'] ?? ''));
        $confidence = max(0.0, min(1.0, (float) ($decoded['confidence'] ?? 0.0)));
        $uncertain = ($decoded['uncertain'] ?? false) === true || $description === '';

        return new AiArtifact(
            capability: $request->capability,
            model: new ModelDeployment($this->provider->value, 'platform', $model),
            promptVersion: self::PROMPT_VERSION,
            schemaVersion: $request->capability->schemaVersion(),
            fields: [
                new FieldValue('description', $description, $confidence, $uncertain),
            ],
        );
    }

    private function userText(AiRequest $request): string
    {
        $lines = [];

        foreach ($request->userContent as $key => $value) {
            if ($value === null) {
                continue;
            }
            $lines[] = (string) $key.': '.(string) $value;
        }

        return $lines === [] ? '(no additional context)' : implode("\n", $lines);
    }

    private static function schemaInstruction(): string
    {
        return 'Respond with a single JSON object and nothing else: '
            .'{"description": string, "confidence": number between 0 and 1, "uncertain": boolean}.';
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
            'model_identity' => (new ModelDeployment($this->provider->value, 'platform', $model))->identity(),
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

    private function dropIfAccountFault(?int $connectionId, bool $accountFault): void
    {
        if ($connectionId !== null && $accountFault) {
            $this->routing->markUnhealthy($connectionId);
        }
    }
}
