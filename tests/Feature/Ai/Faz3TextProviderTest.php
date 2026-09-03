<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\StructuredGenerationPort;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Application\Platform\Port\AccountRoutingPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Ai\Capability;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Infrastructure\Ai\AnthropicTextProvider;
use App\Infrastructure\Ai\FakeProvider;
use App\Infrastructure\Ai\OpenAiCompatibleTextProvider;
use App\Infrastructure\Ai\StructuredGenerationRouter;
use App\Models\User;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FAZ 3 METİN ADAPTÖRLERİ — Anthropic + OpenAI-uyumlu (`docs/96` Faz 3).
 *
 * DOĞRULAMA UYARISI: ikisi de gerçek API'ye karşı doğrulanmadı; burada
 * sınanan, isteğin ŞEKLİ ve arıza davranışıdır (`docs/94` disiplini).
 */
final class Faz3TextProviderTest extends TestCase
{
    use RefreshDatabase;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai.enabled', true);
        Config::set('ai.budget.monthly_minor_per_tenant', 100000);
        Config::set('ai.capabilities', [
            'product.description' => ['candidates' => [], 'confidence_threshold' => 0.6],
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'f3-'.$user->id, 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function request(): AiRequest
    {
        return new AiRequest(
            capability: Capability::ProductDescription,
            workspaceId: $this->workspaceId,
            instruction: 'Write one short line. Never claim it is allergen-free.',
            userContent: ['product' => 'Adana Kebap', 'category' => 'Kebaplar'],
        );
    }

    private function configure(CredentialProvider $provider, array $values): void
    {
        $this->app->make(PlatformCredentialAdminPort::class)->put($provider, $values, null);

        /*
            ÖZEL UÇ NOKTA, SINANMADAN ADAY OLMAZ (FF-60, `docs/51` §4.5) —
            superadmin'in yazdığı keyfi bir adrese, ne konuştuğunu bilmeden
            üretim trafiği gitmez. Bu testlerin konusu adaptörün KENDİ
            davranışı olduğu için, kapıyı yoklama yapılmış gibi açıyoruz;
            kapının kendisi `ConnectionProbeTest`'te sınanıyor.
        */
        if ($provider === CredentialProvider::CustomEndpoint) {
            $id = DB::table('platform_credential_connections')
                ->where('provider', $provider->value)->value('id');

            $this->app->make(AccountRoutingPort::class)->markHealthy((int) $id);
        }
    }

    // --- ANTHROPIC ---------------------------------------------------------

    #[Test]
    public function anthropic_sends_the_instruction_in_the_system_field_not_mixed_with_user_content(): void
    {
        $this->configure(CredentialProvider::Anthropic, ['api_key' => 'sk-ant-test']);

        Http::fake([
            '*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode(['description' => 'Közde pişmiş, acılı.', 'confidence' => 0.9, 'uncertain' => false]),
                ]],
                'usage' => ['input_tokens' => 120, 'output_tokens' => 30],
            ], 200),
        ]);

        $artifact = $this->app->make(AnthropicTextProvider::class)->generate($this->request());

        self::assertSame('Közde pişmiş, acılı.', $artifact->fields[0]->value);
        self::assertFalse($artifact->fields[0]->uncertain);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            // TALİMAT ile VERİ ayrı kanalda (`docs/16` AI-10).
            self::assertStringContainsString('Never claim it is allergen-free', (string) $body['system']);
            self::assertStringNotContainsString('Adana Kebap', (string) $body['system']);
            self::assertStringContainsString(
                'Adana Kebap',
                (string) $body['messages'][0]['content'],
            );

            // `max_tokens` Messages API'de ZORUNLU — atlanırsa 400 gelir ve
            // bu bizim hatamız olurdu, hesabınki değil.
            self::assertArrayHasKey('max_tokens', $body);
            self::assertSame('2023-06-01', $request->header('anthropic-version')[0]);

            return str_contains($request->url(), '/v1/messages');
        });
    }

    #[Test]
    public function anthropic_survives_a_json_answer_wrapped_in_a_code_fence(): void
    {
        $this->configure(CredentialProvider::Anthropic, ['api_key' => 'sk-ant-test']);

        // Model bazen JSON'u ``` çitleri içinde döndürür. Çiti soymak
        // ayrıştırmayı kurtarır — ve ikinci bir fatura üretmez.
        Http::fake([
            '*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => "```json\n{\"description\":\"Acılı kebap.\",\"confidence\":0.8,\"uncertain\":false}\n```",
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $artifact = $this->app->make(AnthropicTextProvider::class)->generate($this->request());

        self::assertSame('Acılı kebap.', $artifact->fields[0]->value);
    }

    #[Test]
    public function anthropic_without_a_key_fails_before_any_network_call(): void
    {
        Http::fake();

        $this->expectException(ProviderCallException::class);

        try {
            $this->app->make(AnthropicTextProvider::class)->generate($this->request());
        } finally {
            Http::assertNothingSent();
        }
    }

    // --- OPENAI-UYUMLU (Kimi / özel uç nokta) ------------------------------

    #[Test]
    public function the_compatible_adapter_talks_chat_completions_and_names_its_provider(): void
    {
        $this->configure(CredentialProvider::Kimi, ['api_key' => 'km-test', 'base_url' => 'https://api.moonshot.ai/v1']);
        Config::set('ai.kimi.text_model', 'kimi-test-model');

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['description' => 'Ucuz taslak.', 'confidence' => 0.7, 'uncertain' => false]),
                    ],
                ]],
                'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 12],
            ], 200),
        ]);

        $artifact = $this->provider(CredentialProvider::Kimi)->generate($this->request());

        self::assertSame('Ucuz taslak.', $artifact->fields[0]->value);
        self::assertSame('kimi', $artifact->model->provider);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/chat/completions'));
    }

    /**
     * ÖZEL UÇ NOKTADA ANAHTAR OPSİYONELDİR — ama adres değil.
     */
    #[Test]
    public function a_custom_endpoint_without_a_key_still_calls_but_without_authorization(): void
    {
        $this->configure(CredentialProvider::CustomEndpoint, ['base_url' => 'https://qwen.internal.example/v1']);
        Config::set('ai.custom_endpoint.text_model', 'qwen2.5-7b-instruct');

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'description' => 'Kendi sunucumdan.', 'confidence' => 0.6, 'uncertain' => false,
                ])]]],
            ], 200),
        ]);

        $artifact = $this->provider(CredentialProvider::CustomEndpoint)->generate($this->request());

        self::assertSame('Kendi sunucumdan.', $artifact->fields[0]->value);

        Http::assertSent(function ($request): bool {
            self::assertEmpty($request->header('Authorization'));

            return str_starts_with($request->url(), 'https://qwen.internal.example/v1/');
        });
    }

    /**
     * MODEL ADI OLMADAN ÇAĞRI YAPILMAZ.
     *
     * Sistem, özel bir sunucuda hangi modelin çalıştığını bilemez; uydurmak
     * sessizce 404 almak olurdu. Adres var ama model yoksa çağrı hiç
     * kurulmaz.
     */
    #[Test]
    public function a_custom_endpoint_without_a_model_name_fails_before_calling(): void
    {
        $this->configure(CredentialProvider::CustomEndpoint, ['base_url' => 'https://qwen.internal.example/v1']);
        Config::set('ai.custom_endpoint.text_model', '');

        Http::fake();

        try {
            $this->provider(CredentialProvider::CustomEndpoint)->generate($this->request());
            self::fail('Model adı yokken çağrı kurulmamalıydı.');
        } catch (ProviderCallException $exception) {
            self::assertSame('no-model', $exception->reason);
        }

        Http::assertNothingSent();
    }

    /**
     * UYUMLULUK VARSAYILMAZ (`docs/51` §4.5).
     *
     * Beklenmeyen bir yanıt şekli "belki çalışmıştır" diye yarım bir sonuca
     * çevrilmez — reddedilir ve zincir bir sonraki adaya geçer.
     */
    #[Test]
    public function an_unexpected_response_shape_is_rejected_not_guessed(): void
    {
        $this->configure(CredentialProvider::CustomEndpoint, ['base_url' => 'https://qwen.internal.example/v1']);
        Config::set('ai.custom_endpoint.text_model', 'qwen2.5-7b-instruct');

        Http::fake(['*' => Http::response(['output' => 'plain text, not our schema'], 200)]);

        try {
            $this->provider(CredentialProvider::CustomEndpoint)->generate($this->request());
            self::fail('Uyumsuz yanıt kabul edildi.');
        } catch (ProviderCallException $exception) {
            self::assertSame('unparseable', $exception->reason);
        }
    }

    // --- ROTA / KULLANILABİLİRLİK -----------------------------------------

    #[Test]
    public function an_anthropic_key_alone_opens_the_description_route_and_binds_the_router(): void
    {
        $this->configure(CredentialProvider::Anthropic, ['api_key' => 'sk-ant-test']);

        self::assertSame(
            AiAvailability::Available,
            $this->app->make(AiAvailabilityPort::class)
                ->isAvailable($this->workspaceId, Capability::ProductDescription),
        );

        self::assertInstanceOf(
            StructuredGenerationRouter::class,
            $this->app->make(StructuredGenerationPort::class),
        );
    }

    /**
     * BİR METİN ANAHTARI, GÖRME YETENEĞİNİ AÇMAZ.
     *
     * Anthropic/Kimi/özel uç noktanın görüntü adaptörü yok. Hepsini tek bir
     * "kasa hizmet ediyor" listesine koymak, yalnız Kimi anahtarı girilmiş
     * bir kurulumda "fotoğraftan menü oku" eylemini AÇIK gösterirdi —
     * kullanıcı basar, arkada o yeteneği karşılayan hiçbir adaptör
     * olmadığı için sahte üretici devreye girerdi.
     */
    #[Test]
    public function a_text_only_provider_does_not_open_the_vision_route(): void
    {
        $this->configure(CredentialProvider::Kimi, ['api_key' => 'km-test']);
        Config::set('ai.capabilities', ['menu.extract' => ['candidates' => [], 'confidence_threshold' => 0.6]]);

        self::assertSame(
            AiAvailability::NoRoute,
            $this->app->make(AiAvailabilityPort::class)
                ->isAvailable($this->workspaceId, Capability::MenuExtract),
        );

        // Ve gerçekten de bağlanan şey sahte üretici — yani rota kapalıyken
        // "açık" demek, kullanıcıyı boş bir düğmeye göndermek olurdu.
        self::assertInstanceOf(FakeProvider::class, $this->app->make(VisionExtractionPort::class));
    }

    #[Test]
    public function a_text_only_provider_does_not_open_the_embedding_route(): void
    {
        $this->configure(CredentialProvider::Anthropic, ['api_key' => 'sk-ant-test']);
        Config::set('ai.capabilities', ['embedding.text' => ['candidates' => [], 'confidence_threshold' => 0.6]]);

        self::assertSame(
            AiAvailability::NoRoute,
            $this->app->make(AiAvailabilityPort::class)
                ->isAvailable($this->workspaceId, Capability::TextEmbedding),
        );
    }

    private function provider(CredentialProvider $provider): OpenAiCompatibleTextProvider
    {
        return new OpenAiCompatibleTextProvider(
            $provider,
            $this->app->make(CredentialResolverPort::class),
            $this->app->make(AccountRoutingPort::class),
            $this->app->make(Factory::class),
            $this->app->make(Repository::class),
        );
    }
}
