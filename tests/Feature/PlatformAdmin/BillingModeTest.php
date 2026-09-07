<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BILLING-MODE — kip anahtarı (docs/107 Faz 1.1).
 *
 * `live` üç kapının ÜÇÜ birden açıkken geçerlidir: dağıtım izin veriyor
 * (`services.iyzico.mode=live`), süperadmin panelden açıkça açtı
 * (`platform_settings` billing.mode) ve kasada Iyzico üretim anahtarı var.
 * Biri kapalıysa etkin kip sandbox'tır ve sebep ADIYLA söylenir. Kasa
 * boşken `live` AÇILAMAZ (422). Her kip değişimi denetim satırı bırakır.
 */
final class BillingModeTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/admin/settings/billing-mode';

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin-mode@example.test', 'email_verified_at' => now()]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id,
            'role' => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function fillVault(): void
    {
        $this->app->make(PlatformCredentialAdminPort::class)->put(CredentialProvider::Iyzico, [
            'api_key' => 'live-api-key-01',
            'secret_key' => 'live-secret-key-01',
        ], byUserId: null);
    }

    #[Test]
    public function the_switch_is_superadmin_only_and_enumeration_safe(): void
    {
        $owner = User::factory()->create(['email' => 'owner-mode@example.test', 'email_verified_at' => now()]);

        $this->withHeaders($this->jsonHeaders())->getJson(self::URI)->assertStatus(401);
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(self::URI)->assertStatus(404);
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(self::URI, ['mode' => 'live'])->assertStatus(404);

        self::assertSame(0, DB::table('platform_settings')->count());
    }

    #[Test]
    public function the_default_is_sandbox_and_live_cannot_be_enabled_with_an_empty_vault(): void
    {
        config()->set('services.iyzico.mode', 'live');
        $admin = $this->admin();

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson(self::URI)
            ->assertOk()
            ->assertJson([
                'requested' => 'sandbox',
                'effective' => 'sandbox',
                'deployment_mode' => 'live',
                'vault_configured' => false,
            ]);

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson(self::URI, ['mode' => 'live'])
            ->assertStatus(422)
            ->assertJson(['reason' => 'vault_missing']);

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson(self::URI, ['mode' => 'production'])
            ->assertStatus(422);

        self::assertSame(0, DB::table('platform_settings')->where('key', 'billing.mode')->count());
        self::assertSame(0, DB::table('platform_audits')->where('scope', 'billing.mode')->count(), 'Reddedilen bir istek denetime yazılmaz — hiçbir şey değişmedi.');
    }

    #[Test]
    public function live_cannot_be_enabled_when_the_deployment_itself_only_permits_sandbox(): void
    {
        config()->set('services.iyzico.mode', 'sandbox');
        $this->fillVault();
        $admin = $this->admin();

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson(self::URI, ['mode' => 'live'])
            ->assertStatus(422)
            ->assertJson(['reason' => 'deployment_mode_sandbox']);
    }

    #[Test]
    public function live_is_enabled_only_with_a_filled_vault_and_a_live_deployment_and_every_change_is_audited(): void
    {
        config()->set('services.iyzico.mode', 'live');
        $this->fillVault();
        $admin = $this->admin();

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson(self::URI, ['mode' => 'live'])
            ->assertOk()
            ->assertJson(['requested' => 'live', 'effective' => 'live', 'vault_configured' => true, 'reasons' => []]);

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson(self::URI, ['mode' => 'sandbox'])
            ->assertOk()
            ->assertJson(['requested' => 'sandbox', 'effective' => 'sandbox']);

        $audits = DB::table('platform_audits')->where('scope', 'billing.mode')->orderBy('id')->get();
        self::assertCount(2, $audits);
        self::assertSame('live', $audits[0]->action);
        self::assertSame($admin->id, (int) $audits[0]->actor_user_id);
        self::assertSame('sandbox', $audits[1]->action);
    }

    #[Test]
    public function the_effective_mode_falls_back_to_sandbox_when_the_vault_is_disabled_after_enabling(): void
    {
        config()->set('services.iyzico.mode', 'live');
        $this->fillVault();
        $admin = $this->admin();

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson(self::URI, ['mode' => 'live'])
            ->assertOk()
            ->assertJsonPath('effective', 'live');

        $this->app->make(PlatformCredentialAdminPort::class)->disable(CredentialProvider::Iyzico);

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson(self::URI)
            ->assertOk()
            ->assertJson(['requested' => 'live', 'effective' => 'sandbox', 'vault_configured' => false])
            ->assertJsonFragment(['reasons' => ['vault_missing']]);
    }
}
