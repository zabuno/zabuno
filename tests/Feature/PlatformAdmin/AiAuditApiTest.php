<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Application\Platform\Port\AccountRoutingPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AI DENETİM İZİ OKUNUR — `docs/98` FF-66. Denetim izi okunmuyorsa yoktur.
 */
final class AiAuditApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'İsmail', 'email_verified_at' => now()]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role' => 'super_admin', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    #[Test]
    public function the_trail_names_who_did_what_to_which_connection_and_who_is_pinned_where(): void
    {
        $admin = $this->admin();

        $connectionId = $this->app->make(PlatformConnectionAdminPort::class)->createConnection(
            CredentialProvider::OpenAi, 'OpenAI — Menü', CredentialScope::PlatformOwned, null,
            ['api_key' => 'sk-secret-must-not-leak-1234'], $admin->id,
        );

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'audit-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->app->make(CredentialResolverPort::class)->resolveFor($workspaceId, CredentialProvider::OpenAi);
        $this->app->make(AccountRoutingPort::class)->markUnhealthy($connectionId);

        $response = $this->actingAs($admin)->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/admin/ai/audit')->assertOk();

        $actions = array_column($response->json('audits'), 'action');
        self::assertContains('created', $actions);
        self::assertContains('health:unhealthy', $actions);

        $created = collect($response->json('audits'))->firstWhere('action', 'created');
        self::assertSame('İsmail', $created['actor']);
        self::assertSame('OpenAI — Menü', $created['connectionLabel']);

        $assignment = $response->json('assignments')[0];
        self::assertSame('Zeytin', $assignment['workspaceName']);
        self::assertSame('OpenAI — Menü', $assignment['connectionLabel']);
        self::assertSame('unhealthy', $assignment['health']);

        self::assertStringNotContainsString('sk-secret', $response->getContent());
    }

    #[Test]
    public function a_non_superadmin_gets_404(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/admin/ai/audit')->assertNotFound();
    }
}
