<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Platform\Credential\CredentialProvider;
use App\Infrastructure\Platform\Credential\StickyAccountRouter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AMAÇ boyutu (`docs/97` R30 → FF-75): toplu trafik `purpose=batch`
 * etiketli bağlantıya yapışır; etkileşimli yapışkanlık değişmez.
 */
final class BatchPurposeRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function connection(string $label, ?string $purpose = null): int
    {
        return (int) DB::table('platform_credential_connections')->insertGetId([
            'provider' => CredentialProvider::OpenAi->value, 'label' => $label, 'scope' => 'platform_owned',
            'plain_fields' => json_encode($purpose === null ? [] : ['purpose' => $purpose]),
            'state' => 'active', 'health_status' => 'unknown', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function workspace(): int
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        return (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'rt-'.bin2hex(random_bytes(3)), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[Test]
    public function batch_purpose_prefers_the_connection_tagged_for_batch_and_sticks_separately(): void
    {
        $interactive = $this->connection('Varsayılan');
        $batch = $this->connection('OpenAI — Toplu', 'batch');
        $workspaceId = $this->workspace();
        $router = app(StickyAccountRouter::class);

        self::assertSame([$interactive, $batch], $router->candidates($workspaceId, CredentialProvider::OpenAi));
        self::assertSame([$batch, $interactive], $router->candidates($workspaceId, CredentialProvider::OpenAi, 'batch'));

        $router->remember($workspaceId, CredentialProvider::OpenAi, $interactive);
        $router->remember($workspaceId, CredentialProvider::OpenAi, $batch, 'batch');

        self::assertSame(2, DB::table('ai_connection_assignments')->where('workspace_id', $workspaceId)->count(), 'İki amaç, iki yapışkanlık.');
        self::assertSame([$interactive, $batch], $router->candidates($workspaceId, CredentialProvider::OpenAi));
        self::assertSame([$batch, $interactive], $router->candidates($workspaceId, CredentialProvider::OpenAi, 'batch'));
    }

    #[Test]
    public function without_a_tagged_connection_batch_traffic_uses_the_interactive_order(): void
    {
        $a = $this->connection('A');
        $b = $this->connection('B');
        $router = app(StickyAccountRouter::class);

        self::assertSame([$a, $b], $router->candidates($this->workspace(), CredentialProvider::OpenAi, 'batch'));
    }
}
