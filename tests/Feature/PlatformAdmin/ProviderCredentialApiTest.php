<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CRED-API — superadmin sağlayıcı kasası yüzeyi (Vault Faz 2).
 *
 * Kasa çekirdeği (Faz 1) şifreli saklıyor ve maskeli okuyor; bu faz onu
 * yalnız-yazılır bir superadmin API'ının arkasına koyuyor. Sır HİÇBİR
 * cevaba çıkmaz, yetki yüzeyi enumeration-safe, ve her yazma bir audit
 * satırı bırakır — sırsız.
 */
final class ProviderCredentialApiTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'mg-live-secret-value-9b1c0';

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $email): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    private function grantSuperAdmin(User $user): void
    {
        if (! Schema::hasTable('platform_role_assignments')) {
            self::fail('CRED-API: production platform_role_assignments tablosu yok.');
        }

        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id,
            'role' => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function admin(): User
    {
        $user = $this->verifiedUser('admin@example.test');
        $this->grantSuperAdmin($user);

        return $user;
    }

    // --- CRED-API-AUTHZ-01 -----------------------------------------------

    #[Test]
    public function a_guest_is_unauthenticated_not_route_missing(): void
    {
        $this->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/credentials')
            ->assertStatus(401);
    }

    #[Test]
    public function a_verified_non_admin_is_denied_enumeration_safely(): void
    {
        $owner = $this->verifiedUser('owner@example.test');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/credentials')
            ->assertStatus(404);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson('/api/admin/credentials/mailgun', ['domain' => 'x.mailgun.org', 'secret' => 'y'])
            ->assertStatus(404);
    }

    // --- CRED-API-LIST-01 ------------------------------------------------

    #[Test]
    public function the_list_shows_every_provider_unconfigured_at_first(): void
    {
        $response = $this->actingAs($this->admin())->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/credentials')
            ->assertStatus(200);

        $providers = array_column($response->json(), 'provider');
        sort($providers);
        self::assertSame(
            ['anthropic', 'custom_endpoint', 'gemini', 'iyzico', 'kimi', 'mailgun', 'openai'],
            $providers,
        );

        foreach ($response->json() as $entry) {
            self::assertFalse($entry['configured']);
        }
    }

    // --- CRED-API-STORE-01 + SECRET-NEVER-IN-RESPONSE-01 -----------------

    #[Test]
    public function storing_a_credential_never_echoes_the_secret_and_persists_encrypted(): void
    {
        $admin = $this->admin();

        $store = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson('/api/admin/credentials/mailgun', [
                'domain' => 'sandbox123.mailgun.org',
                'secret' => self::SECRET,
                'endpoint' => 'api.mailgun.net',
            ])
            ->assertStatus(200);

        self::assertStringNotContainsString(self::SECRET, $store->getContent(), 'CRED-API: sır PUT cevabında görünüyor.');
        self::assertTrue($store->json('configured'));

        // Diskte şifreli — düz sır yok.
        $row = DB::table('platform_credentials')->where('provider', 'mailgun')->first();
        self::assertStringNotContainsString(self::SECRET, (string) json_encode($row));

        // Sonraki GET de sırrı sızdırmaz, ama configured=true gösterir.
        $list = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/credentials')
            ->assertStatus(200);

        self::assertStringNotContainsString(self::SECRET, $list->getContent(), 'CRED-API: sır GET listesinde görünüyor.');
        $mailgun = collect($list->json())->firstWhere('provider', 'mailgun');
        self::assertTrue($mailgun['configured']);
    }

    // --- CRED-API-UNKNOWN-FIELD-01 ---------------------------------------

    #[Test]
    public function a_field_outside_the_schema_is_a_422_not_a_500(): void
    {
        $this->actingAs($this->admin())->withHeaders($this->jsonHeaders())
            ->putJson('/api/admin/credentials/mailgun', [
                'domain' => 'x.mailgun.org',
                'secret' => 'y',
                'bogus_backdoor' => 'evil',
            ])
            ->assertStatus(422);
    }

    // --- CRED-API-UNKNOWN-PROVIDER-01 ------------------------------------

    #[Test]
    public function an_unknown_provider_is_a_404(): void
    {
        $this->actingAs($this->admin())->withHeaders($this->jsonHeaders())
            ->putJson('/api/admin/credentials/not-a-provider', ['x' => 'y'])
            ->assertStatus(404);
    }

    // --- CRED-API-DISABLE-01 ---------------------------------------------

    #[Test]
    public function disabling_a_provider_flips_its_state(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson('/api/admin/credentials/openai', ['api_key' => 'sk-live-2222'])
            ->assertStatus(200);

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson('/api/admin/credentials/openai/disable')
            ->assertStatus(200);

        $list = $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->getJson('/api/admin/credentials')->json();
        $openai = collect($list)->firstWhere('provider', 'openai');

        self::assertSame('disabled', $openai['state']);
        self::assertFalse($openai['configured']);
    }

    // --- CRED-API-AUDIT-01 -----------------------------------------------

    #[Test]
    public function every_write_leaves_an_audit_row_without_the_secret(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->putJson('/api/admin/credentials/mailgun', ['domain' => 'x.mailgun.org', 'secret' => self::SECRET])
            ->assertStatus(200);

        $this->actingAs($admin)->withHeaders($this->jsonHeaders())
            ->postJson('/api/admin/credentials/mailgun/disable')
            ->assertStatus(200);

        $audits = DB::table('platform_credential_audits')->where('provider', 'mailgun')->get();
        self::assertCount(2, $audits);

        $actions = $audits->pluck('action')->sort()->values()->all();
        self::assertSame(['disabled', 'set'], $actions);

        foreach ($audits as $audit) {
            self::assertSame($admin->id, (int) $audit->actor_user_id);
            self::assertStringNotContainsString(self::SECRET, (string) json_encode($audit), 'CRED-API-AUDIT: sır audit satırında.');
        }
    }
}
