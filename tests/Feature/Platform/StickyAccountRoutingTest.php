<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

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
 * YAPIŞKAN HESAP EŞLEMESİ — `docs/14` §2a, `docs/95` Faz 3 §Yapışkanlık.
 *
 * Kural şu: bir tenant'ın ilk isteği hangi bağlantıya giderse SONRAKİLER DE
 * oraya gider. Rastgele dağıtım yasak — ve bu bir tercih değil, bir maliyet
 * gerçeği: prompt önbelleği ve oturum bağlamı hesaba bağlıdır. İstekleri iki
 * hesap arasında dağıtmak, her seferinde soğuk önbellekle çalışmak demektir.
 *
 * İkinci kural: sağlıksız bağlantı havuzdan GEÇİCİ olarak düşer ve düşüş
 * denetime yazılır — otomatik silinmez/iptal edilmez, o insan kararıdır.
 */
final class StickyAccountRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function connections(): PlatformConnectionAdminPort
    {
        return $this->app->make(PlatformConnectionAdminPort::class);
    }

    private function routing(): AccountRoutingPort
    {
        return $this->app->make(AccountRoutingPort::class);
    }

    private function resolver(): CredentialResolverPort
    {
        return $this->app->make(CredentialResolverPort::class);
    }

    private function workspaceId(string $name = 'Zeytin'): int
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        return (int) DB::table('workspaces')->insertGetId([
            'name' => $name, 'slug' => 'rt-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function platformConnection(string $label, string $key): int
    {
        return $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            $label,
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => $key],
            null,
        );
    }

    // --- ROUTE-STICKY-01 --------------------------------------------------

    #[Test]
    public function a_workspace_keeps_the_same_connection_across_calls(): void
    {
        $this->platformConnection('Birinci', 'sk-first-1111');
        $this->platformConnection('İkinci', 'sk-second-2222');

        $workspaceId = $this->workspaceId();

        $first = $this->resolver()->resolveFor($workspaceId, CredentialProvider::OpenAi);
        $second = $this->resolver()->resolveFor($workspaceId, CredentialProvider::OpenAi);
        $third = $this->resolver()->resolveFor($workspaceId, CredentialProvider::OpenAi);

        self::assertSame('sk-first-1111', $first->values['api_key']);
        self::assertSame($first->connectionId, $second->connectionId);
        self::assertSame($first->connectionId, $third->connectionId);

        // Ve eşleme KALICI — bir sonraki istek yeni bir süreçte de aynı yere gider.
        self::assertSame(1, DB::table('ai_connection_assignments')->count());
    }

    #[Test]
    public function two_workspaces_may_land_on_different_connections_but_each_stays_put(): void
    {
        $first = $this->platformConnection('Birinci', 'sk-first-1111');
        $this->platformConnection('İkinci', 'sk-second-2222');

        $a = $this->workspaceId('A');
        $b = $this->workspaceId('B');

        // A birinciye yapışır.
        $this->resolver()->resolveFor($a, CredentialProvider::OpenAi);

        // B'yi elle ikinciye bağla — yapışkanlık onu orada tutmalı.
        DB::table('ai_connection_assignments')->insert([
            'workspace_id' => $b,
            'provider' => 'openai',
            'connection_id' => DB::table('platform_credential_connections')
                ->where('label', 'İkinci')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertSame(
            'sk-second-2222',
            $this->resolver()->resolveFor($b, CredentialProvider::OpenAi)->values['api_key'],
        );
        self::assertSame(
            $first,
            $this->resolver()->resolveFor($a, CredentialProvider::OpenAi)->connectionId,
        );
    }

    // --- ROUTE-HEALTH-01 --------------------------------------------------

    #[Test]
    public function an_unhealthy_connection_leaves_the_pool_and_the_tenant_is_reassigned(): void
    {
        $first = $this->platformConnection('Birinci', 'sk-first-1111');
        $second = $this->platformConnection('İkinci', 'sk-second-2222');

        $workspaceId = $this->workspaceId();
        self::assertSame(
            $first,
            $this->resolver()->resolveFor($workspaceId, CredentialProvider::OpenAi)->connectionId,
        );

        $this->routing()->markUnhealthy($first);

        $resolved = $this->resolver()->resolveFor($workspaceId, CredentialProvider::OpenAi);

        self::assertSame($second, $resolved->connectionId);
        self::assertSame('sk-second-2222', $resolved->values['api_key']);

        // Sağlıksız bağlantı SİLİNMEDİ — yalnız havuzdan düştü.
        self::assertNotNull($this->connections()->connection($first));
        self::assertSame('unhealthy', $this->connections()->connection($first)?->health->value);
    }

    #[Test]
    public function a_health_change_is_written_to_the_audit_trail(): void
    {
        $id = $this->platformConnection('Tek', 'sk-only-3333');

        $this->routing()->markUnhealthy($id);
        $this->routing()->markHealthy($id);

        $actions = DB::table('platform_credential_audits')
            ->where('connection_id', $id)->orderBy('id')->pluck('action')->all();

        self::assertSame(['created', 'health:unhealthy', 'health:healthy'], $actions);
    }

    #[Test]
    public function repeating_the_same_health_verdict_does_not_flood_the_audit_trail(): void
    {
        $id = $this->platformConnection('Tek', 'sk-only-4444');

        $this->routing()->markHealthy($id);
        $this->routing()->markHealthy($id);
        $this->routing()->markHealthy($id);

        // "Hâlâ sağlıklı" bir OLAY değildir. Her yoklamayı yazsaydık,
        // gerçek olay (bir hesabın düşmesi) gürültüde kaybolurdu.
        $actions = DB::table('platform_credential_audits')
            ->where('connection_id', $id)->pluck('action')->all();

        self::assertSame(['created', 'health:healthy'], $actions);
    }

    // --- ROUTE-BYOK-01 ----------------------------------------------------

    /**
     * TENANT'IN KENDİ ANAHTARI PLATFORMUNKİNİ YENER — ve yalnız onunkini.
     *
     * BYOK'un anlamı budur: müşteri kendi hesabını getirdiyse, faturası ona
     * gider ve isteği kendi kotasından çalışır. Aksi hâlde anahtarı girmenin
     * hiçbir etkisi olmazdı.
     */
    #[Test]
    public function a_workspace_with_its_own_key_uses_it_instead_of_the_platform_account(): void
    {
        $this->platformConnection('Platform', 'sk-platform-1111');

        $mine = $this->workspaceId('Benim');
        $neighbour = $this->workspaceId('Komşu');

        $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'Benim kendi anahtarım',
            CredentialScope::TenantByok,
            $mine,
            ['api_key' => 'sk-mine-9999'],
            null,
        );

        self::assertSame(
            'sk-mine-9999',
            $this->resolver()->resolveFor($mine, CredentialProvider::OpenAi)->values['api_key'],
        );

        // Komşu onu ASLA görmez — yapısal sınır, filtre değil.
        self::assertSame(
            'sk-platform-1111',
            $this->resolver()->resolveFor($neighbour, CredentialProvider::OpenAi)->values['api_key'],
        );
    }

    #[Test]
    public function a_disabled_byok_key_falls_back_to_the_platform_account(): void
    {
        $this->platformConnection('Platform', 'sk-platform-1111');
        $workspaceId = $this->workspaceId();

        $byok = $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'Kendi anahtarım',
            CredentialScope::TenantByok,
            $workspaceId,
            ['api_key' => 'sk-mine-9999'],
            null,
        );

        $this->resolver()->resolveFor($workspaceId, CredentialProvider::OpenAi);
        $this->connections()->disableConnection($byok, null);

        // Müşteri kendi anahtarını kapattı diye ÜRÜN durmaz — platform
        // hesabına döner. (Faturalandırma politikası ayrı bir konudur ve
        // burada karar verilmez.)
        self::assertSame(
            'sk-platform-1111',
            $this->resolver()->resolveFor($workspaceId, CredentialProvider::OpenAi)->values['api_key'],
        );
    }

    // --- ROUTE-EMPTY-01 ---------------------------------------------------

    #[Test]
    public function with_no_connection_at_all_the_resolution_is_empty_and_nothing_throws(): void
    {
        $resolved = $this->resolver()->resolveFor($this->workspaceId(), CredentialProvider::OpenAi);

        self::assertSame([], $resolved->values);
        self::assertNull($resolved->connectionId);
        self::assertSame(0, DB::table('ai_connection_assignments')->count());
    }

    #[Test]
    public function a_deleted_workspace_takes_its_assignment_with_it(): void
    {
        $this->platformConnection('Birinci', 'sk-first-1111');
        $workspaceId = $this->workspaceId();

        $this->resolver()->resolveFor($workspaceId, CredentialProvider::OpenAi);
        self::assertSame(1, DB::table('ai_connection_assignments')->count());

        DB::table('workspaces')->where('id', $workspaceId)->delete();

        self::assertSame(0, DB::table('ai_connection_assignments')->count());
    }
}
