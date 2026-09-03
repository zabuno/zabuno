<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Application\Ai\Port\AiAvailability;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Ai\Capability;
use App\Infrastructure\Ai\AiBudgetLedger;
use App\Infrastructure\Ai\ConfiguredAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AI-S1-01 kabul ölçütü #1 — **AI kapalıyken ürün TAM çalışır.**
 *
 * Bu, bir kolaylık değil ürünün temel vaadi: AI sağlayıcısı kapalı olabilir,
 * kredisi bitebilir, model hata verebilir, tenant AI istemeyebilir. Bunların
 * hiçbirinde restoran sahibi menüsünü yönetemez hâle gelmemeli
 * (`docs/14` §1, `docs/51` §3.6/1).
 */
final class AiOffProductWorksTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceId(): int
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        return (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin',
            'slug' => 'zeytin-ai-'.$user->getKey(),
            'state' => 'active',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function availability(): ConfiguredAvailability
    {
        return new ConfiguredAvailability(new AiBudgetLedger, $this->app->make(CredentialResolverPort::class));
    }

    public function test_with_ai_disabled_the_plane_reports_kill_switch_and_nothing_throws(): void
    {
        config(['ai.enabled' => false]);

        self::assertSame(
            AiAvailability::KillSwitch,
            $this->availability()->isAvailable($this->workspaceId(), Capability::MenuExtract),
        );
    }

    public function test_the_public_menu_and_the_panel_answer_while_ai_is_off(): void
    {
        config(['ai.enabled' => false]);

        // Ürünün kendisi AI'ya hiç bakmadan cevap vermeli.
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_a_capability_with_no_configured_candidate_reports_no_route(): void
    {
        config(['ai.enabled' => true, 'ai.capabilities.menu.extract.candidates' => []]);

        // "Aday yok" ile "kapalı" farklı durumlardır ve farklı çözümleri
        // vardır: biri yönetici açar, diğeri yapılandırma ister.
        self::assertSame(
            AiAvailability::NoRoute,
            $this->availability()->isAvailable($this->workspaceId(), Capability::MenuExtract),
        );
    }

    public function test_an_exhausted_budget_stops_ai_and_not_the_product(): void
    {
        $workspaceId = $this->workspaceId();

        config([
            'ai.enabled' => true,
            /*
                DÜZ anahtar — `config/ai.php` içindeki gerçek şekil.

                Bu satır önceden `'ai.capabilities.menu.extract.candidates'`
                yazıyordu ve noktalı yazıcı `capabilities → menu → extract`
                diye İÇ İÇE bir yapı kuruyordu. Okuyucu da aynı şekilde
                yanlış olduğu için test geçiyordu: kusurun TUTARLI olduğunu
                kanıtlıyordu, doğru olduğunu değil.

                Gerçek yapılandırma dosyasında anahtar `'menu.extract'` düz
                metnidir; üretimde cevap her zaman "rota yok" olurdu
                (`docs/92`).
            */
            'ai.capabilities' => [
                'menu.extract' => ['candidates' => ['local:fake:m'], 'confidence_threshold' => 0.90],
            ],
            'ai.budget.monthly_minor_per_tenant' => 100,
        ]);

        DB::table('ai_invocations')->insert([
            'workspace_id' => $workspaceId,
            'capability' => Capability::MenuExtract->value,
            'model_identity' => 'local:fake:m',
            'outcome' => 'success',
            'cost_minor' => 150,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertSame(
            AiAvailability::BudgetExhausted,
            $this->availability()->isAvailable($workspaceId, Capability::MenuExtract),
        );

        // Bütçe dolu — ama ürün ayakta. Medya kotasıyla aynı ilke.
        $this->get('/')->assertOk();
    }

    public function test_a_zero_budget_means_closed_not_unlimited(): void
    {
        config([
            'ai.enabled' => true,
            /*
                DÜZ anahtar — `config/ai.php` içindeki gerçek şekil.

                Bu satır önceden `'ai.capabilities.menu.extract.candidates'`
                yazıyordu ve noktalı yazıcı `capabilities → menu → extract`
                diye İÇ İÇE bir yapı kuruyordu. Okuyucu da aynı şekilde
                yanlış olduğu için test geçiyordu: kusurun TUTARLI olduğunu
                kanıtlıyordu, doğru olduğunu değil.

                Gerçek yapılandırma dosyasında anahtar `'menu.extract'` düz
                metnidir; üretimde cevap her zaman "rota yok" olurdu
                (`docs/92`).
            */
            'ai.capabilities' => [
                'menu.extract' => ['candidates' => ['local:fake:m'], 'confidence_threshold' => 0.90],
            ],
            'ai.budget.monthly_minor_per_tenant' => 0,
        ]);

        // Varsayılan olarak sınırsız harcama açmak, tavansız bir maliyet
        // demek olurdu. Sıfır "kapalı" demektir.
        self::assertSame(
            AiAvailability::BudgetExhausted,
            $this->availability()->isAvailable($this->workspaceId(), Capability::MenuExtract),
        );
    }

    public function test_ai_is_off_by_default(): void
    {
        // Bir kurulum, kimse bir şey yapmadan AI harcaması başlatmamalı.
        self::assertFalse((bool) config('ai.enabled'));
    }
}
