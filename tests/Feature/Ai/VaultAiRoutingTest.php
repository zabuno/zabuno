<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Ai\Capability;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Infrastructure\Ai\FakeProvider;
use App\Infrastructure\Ai\VisionExtractionRouter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * VAULT-AI-ROUTING — kasadaki OpenAI anahtarı rotayı ve adaptörü açar (Faz 5).
 */
final class VaultAiRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceId(): int
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        return (int) DB::table('workspaces')->insertGetId([
            'name' => 'Test',
            'slug' => 'test-'.$user->id,
            'state' => 'active',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configureOpenAi(): void
    {
        $this->app->make(PlatformCredentialAdminPort::class)->put(
            CredentialProvider::OpenAi,
            ['api_key' => 'sk-test-key'],
            byUserId: null,
        );
    }

    // --- VAULT-AI-ROUTE-OPENS-01 -----------------------------------------

    #[Test]
    public function the_vault_key_opens_the_route_without_touching_config_candidates(): void
    {
        Config::set('ai.enabled', true);
        Config::set('ai.budget.monthly_minor_per_tenant', 100000);
        // config aday listesi BOŞ kalıyor — rota kasadan gelmeli.

        $availability = $this->app->make(AiAvailabilityPort::class);
        self::assertSame(AiAvailability::NoRoute, $availability->isAvailable(1, Capability::MenuExtract));

        $this->configureOpenAi();

        self::assertSame(AiAvailability::Available, $availability->isAvailable(1, Capability::MenuExtract));
    }

    // --- VAULT-AI-BUDGET-STILL-GATES-01 ----------------------------------

    #[Test]
    public function a_key_without_budget_is_still_closed(): void
    {
        Config::set('ai.enabled', true);
        Config::set('ai.budget.monthly_minor_per_tenant', 0); // sıfır = kapalı
        $this->configureOpenAi();

        self::assertSame(
            AiAvailability::BudgetExhausted,
            $this->app->make(AiAvailabilityPort::class)->isAvailable(1, Capability::MenuExtract),
        );
    }

    // --- VAULT-AI-BINDING-01 ---------------------------------------------

    #[Test]
    public function the_real_adapter_is_bound_only_when_the_vault_has_the_key_and_ai_is_on(): void
    {
        Config::set('ai.enabled', true);

        // Anahtar yok → sahte sağlayıcı.
        self::assertInstanceOf(FakeProvider::class, $this->app->make(VisionExtractionPort::class));

        $this->configureOpenAi();
        /*
            Anahtar var + AI açık → CANLI YEDEK ZİNCİRİ (`docs/97` R10-R12).
            Artık tek bir sabit sınıf değil — `VisionExtractionRouter`,
            yapılandırılmış adayları sırayla dener. Hangi adayın gerçekten
            cevapladığı `gemini_wins_when_both_are_configured_and_healthy`
            testinde davranışsal olarak kanıtlanıyor.
        */
        self::assertInstanceOf(VisionExtractionRouter::class, $this->app->make(VisionExtractionPort::class));

        // AI kapalıyken anahtar olsa bile sahte sağlayıcı (kill switch kazanır).
        Config::set('ai.enabled', false);
        self::assertInstanceOf(FakeProvider::class, $this->app->make(VisionExtractionPort::class));
    }

    // --- VAULT-AI-GEMINI-FIRST-01 -----------------------------------------

    #[Test]
    public function gemini_wins_when_both_are_configured_and_healthy(): void
    {
        Config::set('ai.enabled', true);
        $this->configureOpenAi();
        $this->app->make(PlatformCredentialAdminPort::class)->put(
            CredentialProvider::Gemini,
            ['api_key' => 'gm-test-key', 'base_url' => 'https://generativelanguage.googleapis.test'],
            byUserId: null,
        );

        Http::fake([
            'generativelanguage.googleapis.test/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['rows' => []])]]]]],
                'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
            ], 200),
            // OpenAI hiç çağrılmamalı — çağrılırsa test bu sahte cevapla
            // başarıya "yardım" etmez, yanlış sağlayıcıyı ele verir.
            'api.openai.com/*' => Http::response(['choices' => []], 200),
        ]);

        $imagePath = tempnam(sys_get_temp_dir(), 'vision').'.png';
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $artifact = $this->app->make(VisionExtractionPort::class)->extract(
            new AiRequest(Capability::MenuExtract, $this->workspaceId(), 'çıkar'),
            [$imagePath],
        );

        self::assertSame('google', $artifact->model->provider, 'docs/51 §4b.1: Gemini birincil aday.');
        self::assertFalse($artifact->usedFallback);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'api.openai.com'));
    }

    // --- VAULT-AI-LIVE-FALLBACK-01 ------------------------------------------

    #[Test]
    public function a_failing_gemini_falls_through_to_openai_live_and_the_result_is_marked(): void
    {
        Config::set('ai.enabled', true);
        $this->configureOpenAi();
        $this->app->make(PlatformCredentialAdminPort::class)->put(
            CredentialProvider::Gemini,
            ['api_key' => 'gm-test-key', 'base_url' => 'https://generativelanguage.googleapis.test'],
            byUserId: null,
        );

        Http::fake([
            'generativelanguage.googleapis.test/*' => Http::response(['error' => 'down'], 500),
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['rows' => []])]]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ], 200),
        ]);

        $imagePath = tempnam(sys_get_temp_dir(), 'vision').'.png';
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $artifact = $this->app->make(VisionExtractionPort::class)->extract(
            new AiRequest(Capability::MenuExtract, $this->workspaceId(), 'çıkar'),
            [$imagePath],
        );

        self::assertSame('openai', $artifact->model->provider);
        self::assertTrue($artifact->usedFallback, 'docs/97 R12: yedekten gelen sonuç işaretlenmeli.');
    }

    // --- VAULT-AI-PRODUCT-DESCRIPTION-ROUTE-OPENS-01 -----------------------

    #[Test]
    public function the_vault_gemini_key_opens_the_product_description_route_too(): void
    {
        /*
            Gerçek kusur: `vaultServes()` yalnız görüntü yeteneklerini
            tanıyordu. Kasada Gemini anahtarı olsa bile
            `config('ai.capabilities.product.description.candidates')` boş
            olduğu için rota HİÇBİR ZAMAN açılmıyordu — FF-34'ün düzelttiği
            "paid but doesn't work" sınıfıyla aynı arıza.
        */
        Config::set('ai.enabled', true);
        Config::set('ai.budget.monthly_minor_per_tenant', 100000);

        $availability = $this->app->make(AiAvailabilityPort::class);
        self::assertSame(
            AiAvailability::NoRoute,
            $availability->isAvailable(1, Capability::ProductDescription),
        );

        $this->app->make(PlatformCredentialAdminPort::class)->put(
            CredentialProvider::Gemini,
            ['api_key' => 'gm-test-key'],
            byUserId: null,
        );

        self::assertSame(
            AiAvailability::Available,
            $availability->isAvailable(1, Capability::ProductDescription),
        );
    }
}
