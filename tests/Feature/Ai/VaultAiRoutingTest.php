<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Ai\Capability;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Infrastructure\Ai\FakeProvider;
use App\Infrastructure\Ai\GeminiVisionProvider;
use App\Infrastructure\Ai\OpenAiVisionProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * VAULT-AI-ROUTING — kasadaki OpenAI anahtarı rotayı ve adaptörü açar (Faz 5).
 */
final class VaultAiRoutingTest extends TestCase
{
    use RefreshDatabase;

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
        // Anahtar var + AI açık → gerçek adaptör.
        self::assertInstanceOf(OpenAiVisionProvider::class, $this->app->make(VisionExtractionPort::class));

        // AI kapalıyken anahtar olsa bile sahte sağlayıcı (kill switch kazanır).
        Config::set('ai.enabled', false);
        self::assertInstanceOf(FakeProvider::class, $this->app->make(VisionExtractionPort::class));
    }

    // --- VAULT-AI-GEMINI-FIRST-01 -----------------------------------------

    #[Test]
    public function gemini_is_tried_before_openai_when_both_are_configured(): void
    {
        Config::set('ai.enabled', true);

        $this->configureOpenAi();
        self::assertInstanceOf(OpenAiVisionProvider::class, $this->app->make(VisionExtractionPort::class));

        // Gemini de yapılandırılınca — docs/51 §4b.1: "Gemini'de başlar,
        // yetmezse OpenAI" — Gemini kazanır, OpenAI hâlâ kayıtlı kalır.
        $this->app->make(PlatformCredentialAdminPort::class)->put(
            CredentialProvider::Gemini,
            ['api_key' => 'gm-test-key'],
            byUserId: null,
        );

        self::assertInstanceOf(GeminiVisionProvider::class, $this->app->make(VisionExtractionPort::class));
    }
}
