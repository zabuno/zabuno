<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CONN-API — superadmin çok-bağlantı yüzeyi (`docs/95` Faz 3).
 *
 * Sağlayıcı-düzeyi uçlar (Faz 2) bir sağlayıcının TEK kaydını yönetiyordu.
 * Bunlar aynı disiplini N bağlantıya taşır: sır hiçbir cevaba çıkmaz, yetki
 * yüzeyi enumeration-safe (yetkisiz kullanıcı 404 görür, 403 değil — bir
 * kaynağın VARLIĞI bile bilgi), her yazma sırsız bir audit satırı bırakır.
 */
final class ConnectionApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id,
            'role' => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function workspaceId(): int
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        return (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'ca-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- CONN-API-AUTHZ-01 ------------------------------------------------

    #[Test]
    public function a_non_superadmin_cannot_see_or_write_connections(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->withHeaders($this->headers())
            ->getJson('/api/admin/connections')->assertStatus(404);

        $this->actingAs($stranger)->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'openai', 'label' => 'X', 'scope' => 'platform_owned',
                'fields' => ['api_key' => 'sk-1111'],
            ])->assertStatus(404);
    }

    // --- CONN-API-SCHEMA-01 -----------------------------------------------

    /**
     * LİSTE, ŞEMAYI DA TAŞIR — çünkü ekleme formu ondan ÖNCE gelir.
     *
     * Panel "yeni bağlantı ekle" formunu, henüz hiçbir bağlantı yokken
     * çizmek zorunda: hangi sağlayıcının hangi alanları istediğini bir
     * yerden öğrenmeli. Ayrı bir uç yapmak, panelin her açılışta iki istek
     * atmasını gerektirirdi.
     */
    #[Test]
    public function the_list_carries_the_field_schema_of_every_provider(): void
    {
        $response = $this->actingAs($this->admin())->withHeaders($this->headers())
            ->getJson('/api/admin/connections')->assertStatus(200);

        $providers = collect($response->json('providers'));

        self::assertSame(
            ['mailgun', 'iyzico', 'openai', 'gemini', 'anthropic', 'kimi', 'custom_endpoint'],
            $providers->pluck('provider')->all(),
        );

        $custom = $providers->firstWhere('provider', 'custom_endpoint');
        self::assertSame(
            [
                ['name' => 'base_url', 'secret' => false, 'required' => true, 'default' => null],
                ['name' => 'api_key', 'secret' => true, 'required' => false, 'default' => null],
            ],
            $custom['fields'],
        );

        self::assertSame([], $response->json('connections'));
    }

    // --- CONN-API-CREATE-01 -----------------------------------------------

    #[Test]
    public function creating_a_connection_never_echoes_the_secret_and_stores_it_encrypted(): void
    {
        $secret = 'sk-live-must-not-leak-4b2a';

        $response = $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'openai',
                'label' => 'OpenAI — Toplu İçe Aktarma',
                'scope' => 'platform_owned',
                'fields' => ['api_key' => $secret, 'organization' => 'org-zabuno'],
            ])->assertStatus(201);

        self::assertStringNotContainsString($secret, $response->getContent());
        self::assertSame('OpenAI — Toplu İçe Aktarma', $response->json('label'));
        self::assertSame('••••4b2a', collect($response->json('fields'))
            ->firstWhere('name', 'api_key')['preview']);
        self::assertTrue($response->json('configured'));
        self::assertSame('unknown', $response->json('health'));

        $row = DB::table('platform_credential_connections')->first();
        self::assertStringNotContainsString($secret, (string) json_encode($row));
    }

    #[Test]
    public function two_connections_of_the_same_provider_live_side_by_side(): void
    {
        $admin = $this->admin();

        foreach (['Birinci', 'İkinci'] as $label) {
            $this->actingAs($admin)->withHeaders($this->headers())
                ->postJson('/api/admin/connections', [
                    'provider' => 'gemini', 'label' => $label, 'scope' => 'platform_owned',
                    'fields' => ['api_key' => 'gm-'.$label],
                ])->assertStatus(201);
        }

        $list = $this->actingAs($admin)->withHeaders($this->headers())
            ->getJson('/api/admin/connections')->json('connections');

        self::assertCount(2, $list);
        self::assertSame(['Birinci', 'İkinci'], array_column($list, 'label'));
    }

    // --- CONN-API-VALIDATION-01 -------------------------------------------

    #[Test]
    public function an_unknown_provider_or_field_or_empty_label_is_rejected_with_422(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'skynet', 'label' => 'X', 'scope' => 'platform_owned', 'fields' => [],
            ])->assertStatus(422);

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'openai', 'label' => '  ', 'scope' => 'platform_owned',
                'fields' => ['api_key' => 'sk-1'],
            ])->assertStatus(422);

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'openai', 'label' => 'Arka kapı', 'scope' => 'platform_owned',
                'fields' => ['api_key' => 'sk-1', 'bogus_backdoor' => 'evil'],
            ])->assertStatus(422);
    }

    #[Test]
    public function a_byok_connection_without_a_workspace_is_rejected(): void
    {
        $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'anthropic', 'label' => 'Sahipsiz', 'scope' => 'tenant_byok',
                'fields' => ['api_key' => 'sk-ant-1'],
            ])->assertStatus(422);
    }

    #[Test]
    public function a_byok_connection_names_its_workspace_and_is_listed_as_such(): void
    {
        $workspaceId = $this->workspaceId();

        $created = $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'anthropic', 'label' => 'Zeytin kendi anahtarı',
                'scope' => 'tenant_byok', 'workspaceId' => $workspaceId,
                'fields' => ['api_key' => 'sk-ant-tenant-1'],
            ])->assertStatus(201);

        self::assertSame('tenant_byok', $created->json('scope'));
        self::assertSame($workspaceId, $created->json('workspaceId'));
    }

    // --- CONN-API-UPDATE-01 -----------------------------------------------

    #[Test]
    public function updating_without_the_secret_preserves_it_and_can_rename(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'openai', 'label' => 'Eski ad', 'scope' => 'platform_owned',
                'fields' => ['api_key' => 'sk-keep-me-7777', 'organization' => 'org-a'],
            ])->json('id');

        $updated = $this->actingAs($admin)->withHeaders($this->headers())
            ->putJson("/api/admin/connections/{$id}", [
                'label' => 'Yeni ad',
                'fields' => ['organization' => 'org-b'],
            ])->assertStatus(200);

        self::assertSame('Yeni ad', $updated->json('label'));
        self::assertSame('••••7777', collect($updated->json('fields'))
            ->firstWhere('name', 'api_key')['preview']);
        self::assertSame('org-b', collect($updated->json('fields'))
            ->firstWhere('name', 'organization')['preview']);
    }

    // --- CONN-API-DISABLE-01 ----------------------------------------------

    #[Test]
    public function a_connection_can_be_disabled_and_enabled_again_without_being_deleted(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'kimi', 'label' => 'Kimi — Taslak', 'scope' => 'platform_owned',
                'fields' => ['api_key' => 'km-1111'],
            ])->json('id');

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson("/api/admin/connections/{$id}/disable")->assertStatus(200);

        $list = $this->actingAs($admin)->withHeaders($this->headers())
            ->getJson('/api/admin/connections')->json('connections');
        self::assertSame('disabled', $list[0]['state']);
        self::assertFalse($list[0]['configured']);

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson("/api/admin/connections/{$id}/enable")->assertStatus(200);

        $list = $this->actingAs($admin)->withHeaders($this->headers())
            ->getJson('/api/admin/connections')->json('connections');
        self::assertSame('active', $list[0]['state']);
        // Kapatmak silmek değildir: kayıt hep orada.
        self::assertCount(1, $list);
    }

    #[Test]
    public function a_missing_connection_answers_404_not_500(): void
    {
        $this->actingAs($this->admin())->withHeaders($this->headers())
            ->putJson('/api/admin/connections/99999', ['fields' => []])->assertStatus(404);

        $this->actingAs($this->admin())->withHeaders($this->headers())
            ->postJson('/api/admin/connections/99999/disable')->assertStatus(404);
    }

    // --- CONN-API-AUDIT-01 ------------------------------------------------

    #[Test]
    public function every_write_leaves_a_secret_free_audit_row_naming_the_actor(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/connections', [
                'provider' => 'openai', 'label' => 'Denetlenen', 'scope' => 'platform_owned',
                'fields' => ['api_key' => 'sk-audited-2222'],
            ])->json('id');

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson("/api/admin/connections/{$id}/disable")->assertStatus(200);

        $audits = DB::table('platform_credential_audits')->orderBy('id')->get();

        self::assertSame(['created', 'disabled'], $audits->pluck('action')->all());
        foreach ($audits as $audit) {
            self::assertSame($admin->id, (int) $audit->actor_user_id);
            self::assertSame($id, (int) $audit->connection_id);
            self::assertStringNotContainsString('sk-audited-2222', (string) json_encode($audit));
        }
    }
}
