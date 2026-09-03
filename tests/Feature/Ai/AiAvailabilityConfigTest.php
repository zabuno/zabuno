<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Application\Ai\Port\AiAvailability;
use App\Domain\Ai\Capability;
use App\Infrastructure\Ai\AiBudgetLedger;
use App\Infrastructure\Ai\ConfiguredAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `docs/92` — yapılandırılmış bir yetenek gerçekten bulunmalı.
 *
 * Bu testler ÇERÇEVELİDİR (`config()` bir kap ister); birim testinde
 * yaşayamazlar.
 */
final class AiAvailabilityConfigTest extends TestCase
{
    use RefreshDatabase;

    /**
     * YETENEK ADLARI NOKTA İÇERİR ve `config()` noktayı iç içe anahtar sanar.
     *
     * `config("ai.capabilities.menu.extract.candidates")` çağrısı
     * `capabilities → menu → extract → candidates` yolunu arar; gerçek
     * anahtar ise `'menu.extract'` düz metnidir. Dört yeteneğin DÖRDÜ de
     * nokta taşıdığı için bu çağrı her zaman varsayılana düşüyordu:
     * sağlayıcı tam yapılandırılmış olsa bile cevap "rota yok" olurdu.
     *
     * Kusur görünmüyordu çünkü AI kapalı ve gerçek sağlayıcı yok; ilk kez
     * anahtar girildiği gün "para ödedik ama çalışmıyor" olarak ortaya
     * çıkardı (`docs/92`).
     */
    public function test_a_configured_capability_is_actually_found_despite_the_dot_in_its_name(): void
    {
        config(['ai.enabled' => true]);
        config(['ai.capabilities' => [
            'menu.extract' => ['candidates' => ['openai'], 'confidence_threshold' => 0.90],
        ]]);
        config(['ai.budget.monthly_minor_per_tenant' => 100000]);

        $availability = (new ConfiguredAvailability(new AiBudgetLedger))
            ->isAvailable(1, Capability::MenuExtract);

        self::assertSame(
            AiAvailability::Available,
            $availability,
            'Yapılandırılmış bir yetenek "rota yok" dönmemeli.',
        );
    }

    public function test_an_unconfigured_capability_still_reports_no_route(): void
    {
        config(['ai.enabled' => true]);
        config(['ai.capabilities' => []]);

        self::assertSame(
            AiAvailability::NoRoute,
            (new ConfiguredAvailability(new AiBudgetLedger))->isAvailable(1, Capability::MenuExtract),
        );
    }

    /**
     * BÜTÇE SIFIRSA YETENEK KAPALIDIR — ve bu varsayılan.
     *
     * Tavansız harcamayı varsayılan yapmak, bir betiğin faturayı uçurmasına
     * açık kapı bırakırdı. Anahtar girilse bile bütçe konmadan hiçbir çağrı
     * gitmez.
     */
    public function test_without_a_monthly_budget_nothing_calls_the_provider(): void
    {
        config(['ai.enabled' => true]);
        config(['ai.capabilities' => [
            'menu.extract' => ['candidates' => ['openai'], 'confidence_threshold' => 0.90],
        ]]);
        config(['ai.budget.monthly_minor_per_tenant' => 0]);

        self::assertSame(
            AiAvailability::BudgetExhausted,
            (new ConfiguredAvailability(new AiBudgetLedger))->isAvailable(1, Capability::MenuExtract),
        );
    }
}
