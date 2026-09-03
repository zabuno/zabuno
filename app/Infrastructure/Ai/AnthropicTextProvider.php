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
 * Anthropic (Claude) şemaya-bağlı metin üretimi — `docs/96` Faz 3.
 *
 * DOĞRULAMA UYARISI: bu adaptör gerçek Anthropic API'sine karşı
 * DOĞRULANMADI. İsteğin şekli Messages API'nin belgelenmiş biçimine göre
 * yazıldı ve `Http::fake` ile sınandı. İlk gerçek çağrıyı anahtarı olan
 * kişi yapmalı — kodun yeşil olması "çalışıyor" anlamına gelmez (aynı
 * disiplin: `OpenAiVisionProvider`/FF-41, `GeminiVisionProvider`/FF-45,
 * `docs/94`).
 *
 * NEDEN AYRI BİR SINIF: Messages API OpenAI-uyumlu DEĞİL. Talimat ayrı bir
 * `system` alanında gider, `max_tokens` ZORUNLUDUR ve yanıt `content[]`
 * bloklarından oluşur. Bunları "OpenAI gibi" bir adaptöre bayrakla
 * sığdırmak, iki sağlayıcının da yanlış çalıştığı tek bir sınıf üretirdi.
 *
 * NEDEN YAPILANDIRILMIŞ ÇIKTI İÇİN ARAÇ (tool) KULLANILMIYOR: Claude'un
 * JSON'u garanti etmenin yolu tool_choice'tur, ama bu adaptörün ihtiyacı
 * tek bir metin alanı; `system` içinde şemayı söyleyip yanıtı ayrıştırmak
 * daha az yüzeydir ve şema doğrulayıcı (`ArtifactSchemaValidator`) zaten
 * son sözü söylüyor.
 *
 * TALİMAT ile VERİ tür düzeyinde ayrı: talimat `system`'de, kullanıcı
 * içeriği `messages[].content`'te (`docs/16` AI-10).
 */
final readonly class AnthropicTextProvider implements StructuredGenerationPort
{
    private const PROMPT_VERSION = 'anthropic.structured-text.v1';

    private const API_VERSION = '2023-06-01';

    public function __construct(
        private CredentialResolverPort $credentials,
        private AccountRoutingPort $routing,
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    public function generate(AiRequest $request): AiArtifact
    {
        $resolved = $this->credentials->resolveFor($request->workspaceId, CredentialProvider::Anthropic);
        $creds = $resolved->values;
        $connectionId = $resolved->connectionId;
        $apiKey = (string) ($creds['api_key'] ?? '');

        if ($apiKey === '') {
            throw new ProviderCallException('anthropic', 'no-credential');
        }

        $baseUrl = rtrim((string) ($creds['base_url'] ?? 'https://api.anthropic.com'), '/');
        $model = (string) $this->config->get('ai.anthropic.text_model', 'claude-sonnet-5');
        $timeout = (int) $this->config->get('ai.anthropic.request_timeout', 60);
        $maxTokens = (int) $this->config->get('ai.anthropic.max_tokens', 1024);

        $payload = [
            'model' => $model,
            // `max_tokens` Messages API'de ZORUNLU — atlanırsa 400 döner ve
            // bu, hesabın değil bizim hatamız olurdu.
            'max_tokens' => $maxTokens,
            'temperature' => 0.4,
            // TALİMAT kanalı.
            'system' => $request->instruction."\n\n".self::schemaInstruction(),
            // VERİ kanalı.
            'messages' => [
                ['role' => 'user', 'content' => $this->userText($request)],
            ],
        ];

        $client = $this->http
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
            ->acceptJson()
            ->timeout($timeout);

        try {
            $response = $client->post("{$baseUrl}/v1/messages", $payload);
        } catch (Throwable) {
            $this->record($request, $model, 'failed', 0, 0, 0, 'network');
            $this->dropIfAccountFault($connectionId, ProviderHealthVerdict::networkFailureDropsAccount());
            throw new ProviderCallException('anthropic', 'network');
        }

        if (! $response->successful()) {
            $this->record($request, $model, 'failed', 0, 0, 0, 'http-'.$response->status());
            $this->dropIfAccountFault(
                $connectionId,
                ProviderHealthVerdict::httpStatusDropsAccount($response->status()),
            );
            throw new ProviderCallException('anthropic', 'http-'.$response->status());
        }

        if ($connectionId !== null) {
            $this->routing->markHealthy($connectionId);
        }

        $json = (array) $response->json();
        $decoded = $this->decodeFirstTextBlock($json);

        if ($decoded === null) {
            $this->record($request, $model, 'invalid', 0, 0, 0, 'unparseable');
            throw new ProviderCallException('anthropic', 'unparseable');
        }

        $usage = (array) ($json['usage'] ?? []);
        $inputTokens = (int) ($usage['input_tokens'] ?? 0);
        $outputTokens = (int) ($usage['output_tokens'] ?? 0);
        $cost = $this->cost($model, $inputTokens, $outputTokens);

        $this->record($request, $model, 'succeeded', $inputTokens, $outputTokens, $cost, null);

        $description = trim((string) ($decoded['description'] ?? ''));
        $confidence = max(0.0, min(1.0, (float) ($decoded['confidence'] ?? 0.0)));
        $uncertain = ($decoded['uncertain'] ?? false) === true || $description === '';

        return new AiArtifact(
            capability: $request->capability,
            model: new ModelDeployment('anthropic', 'platform', $model),
            promptVersion: self::PROMPT_VERSION,
            schemaVersion: $request->capability->schemaVersion(),
            fields: [
                new FieldValue('description', $description, $confidence, $uncertain),
            ],
        );
    }

    /**
     * Yanıtın ilk metin bloğunu JSON olarak çözer.
     *
     * Model bazen JSON'u ```json çitleri içinde döndürür; çitleri soymak
     * ayrıştırmayı kurtarır ve bunun için ayrı bir yeniden deneme çağrısı
     * (yani ikinci bir fatura) gerekmez.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>|null
     */
    private function decodeFirstTextBlock(array $json): ?array
    {
        $blocks = (array) ($json['content'] ?? []);

        foreach ($blocks as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'text') {
                continue;
            }

            $text = trim((string) ($block['text'] ?? ''));
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;

            $decoded = json_decode($text, true);

            if (is_array($decoded) && array_key_exists('description', $decoded)) {
                return $decoded;
            }
        }

        return null;
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
            'model_identity' => (new ModelDeployment('anthropic', 'platform', $model))->identity(),
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
