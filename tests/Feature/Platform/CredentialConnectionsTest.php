<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialConnection;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ÇOK-BAĞLANTI KASASI — `docs/95` Faz 3 "Şema evrimi".
 *
 * Bugüne kadar `platform_credentials.provider` UNIQUE'ti: bir sağlayıcının
 * kasada yalnız BİR satırı olabiliyordu. Sahibin istediği "N tane hesap
 * ekle" düğmesi bu kısıt kalkmadan çalışamaz — ve kısıt yalnız bir kolaylık
 * meselesi değil: `docs/96` Faz 3, aynı modelin (OpenAI `gpt-4o-mini`) toplu
 * içe aktarma için AYRI bir hesapta çalışmasını istiyor, çünkü izolasyonun
 * amacı paylaşılan kotayı korumak.
 *
 * `Provider → Connection (N adet)` hiyerarşisi
 * (`modules/ai-provider-account-vault.md`) burada koda dökülür.
 */
final class CredentialConnectionsTest extends TestCase
{
    use RefreshDatabase;

    private function connections(): PlatformConnectionAdminPort
    {
        return $this->app->make(PlatformConnectionAdminPort::class);
    }

    private function legacy(): PlatformCredentialAdminPort
    {
        return $this->app->make(PlatformCredentialAdminPort::class);
    }

    private function resolver(): CredentialResolverPort
    {
        return $this->app->make(CredentialResolverPort::class);
    }

    private function workspaceId(): int
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        return (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'conn-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- CONN-MANY-PER-PROVIDER-01 ---------------------------------------

    #[Test]
    public function one_provider_can_hold_several_named_connections(): void
    {
        $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'OpenAI — Menü İçe Aktarma',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-import-1111'],
            null,
        );

        $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'OpenAI — Toplu İçe Aktarma',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-bulk-2222'],
            null,
        );

        $rows = $this->connections()->connections(CredentialProvider::OpenAi);

        self::assertCount(2, $rows);
        self::assertSame(
            ['OpenAI — Menü İçe Aktarma', 'OpenAI — Toplu İçe Aktarma'],
            array_map(static fn (CredentialConnection $c): string => $c->label, $rows),
        );
    }

    // --- CONN-SECRET-PER-CONNECTION-01 -----------------------------------

    /**
     * HER BAĞLANTININ SIRRI KENDİNİNDİR — ve hiçbiri geri okunmaz.
     *
     * Aynı sağlayıcının iki hesabı aynı sırrı paylaşmaz; paylaşsalardı
     * "ayrı hesap" zaten bir şey ifade etmezdi.
     */
    #[Test]
    public function each_connection_encrypts_its_own_secret_and_returns_only_a_mask(): void
    {
        $first = $this->connections()->createConnection(
            CredentialProvider::Gemini,
            'Gemini — Görüntü',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'gm-vision-key-aaaa'],
            null,
        );

        $second = $this->connections()->createConnection(
            CredentialProvider::Gemini,
            'Gemini — Metin',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'gm-text-key-bbbb'],
            null,
        );

        $rows = DB::table('platform_credential_connections')->get();
        $blob = (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        self::assertStringNotContainsString('gm-vision-key-aaaa', $blob);
        self::assertStringNotContainsString('gm-text-key-bbbb', $blob);

        $maskOf = function (int $id): string {
            $connection = $this->connections()->connection($id);
            self::assertNotNull($connection);

            foreach ($connection->fields as $field) {
                if ($field->name === 'api_key') {
                    return (string) $field->preview;
                }
            }

            self::fail('api_key alanı yok.');
        };

        self::assertSame('••••aaaa', $maskOf($first));
        self::assertSame('••••bbbb', $maskOf($second));
    }

    // --- CONN-LEGACY-COMPATIBLE-01 ---------------------------------------

    /**
     * ESKİ SAĞLAYICI-DÜZEYİ YAZMA ÇALIŞMAYA DEVAM EDER.
     *
     * Panel ve mevcut uçlar (`PUT /api/admin/credentials/{provider}`) bugün
     * sağlayıcı düzeyinde yazıyor. Bu paket şemayı değiştirir ama YÜZEYİ
     * kırmaz: sağlayıcı düzeyindeki yazma, o sağlayıcının VARSAYILAN
     * bağlantısına gider ve yoksa onu yaratır. Kırıcı bir göç, çalışan bir
     * paneli hiçbir kazanç karşılığı bozardı.
     */
    #[Test]
    public function the_provider_level_write_targets_the_default_connection(): void
    {
        $this->legacy()->put(CredentialProvider::Mailgun, [
            'domain' => 'a.mailgun.org',
            'secret' => 'legacy-secret-cccc',
        ], null);

        $rows = $this->connections()->connections(CredentialProvider::Mailgun);

        self::assertCount(1, $rows);
        self::assertSame(CredentialScope::PlatformOwned, $rows[0]->scope);
        self::assertNull($rows[0]->workspaceId);

        // Ve tüketici tarafı hiç değişmemiş gibi çözer.
        self::assertSame(
            'legacy-secret-cccc',
            $this->resolver()->resolve(CredentialProvider::Mailgun)['secret'],
        );
        self::assertTrue($this->legacy()->status(CredentialProvider::Mailgun)->configured);
    }

    // --- CONN-DISABLED-FALLS-TO-SIBLING-01 -------------------------------

    #[Test]
    public function a_disabled_connection_steps_aside_and_a_sibling_serves(): void
    {
        $first = $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'Birinci',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-first-1111'],
            null,
        );

        $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'İkinci',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-second-2222'],
            null,
        );

        self::assertSame('sk-first-1111', $this->resolver()->resolve(CredentialProvider::OpenAi)['api_key']);

        $this->connections()->disableConnection($first, null);

        self::assertSame('sk-second-2222', $this->resolver()->resolve(CredentialProvider::OpenAi)['api_key']);
        self::assertTrue($this->resolver()->isConfigured(CredentialProvider::OpenAi));
    }

    #[Test]
    public function disabling_every_connection_leaves_the_provider_unconfigured(): void
    {
        $id = $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'Tek',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-only-3333'],
            null,
        );

        $this->connections()->disableConnection($id, null);

        self::assertFalse($this->resolver()->isConfigured(CredentialProvider::OpenAi));
        self::assertSame([], $this->resolver()->resolve(CredentialProvider::OpenAi));
        // Kapatmak SİLMEK değildir: kayıt ve denetim izi yerinde kalır.
        self::assertCount(1, $this->connections()->connections(CredentialProvider::OpenAi));
    }

    // --- CONN-BYOK-ISOLATION-01 ------------------------------------------

    /**
     * BYOK BİR FİLTRE DEĞİL, YAPISAL BİR SINIRDIR.
     *
     * Bir tenant'ın kendi anahtarı, platform düzeyindeki bir çözümde
     * ADAY BİLE OLMAZ — "unuttuk filtrelemeyi" diye bir hata biçimi
     * kalmasın diye sorgu düzeyinde ayrılır (`docs/95` Faz 3 §BYOK).
     */
    #[Test]
    public function a_tenant_byok_connection_never_serves_a_platform_level_resolve(): void
    {
        $workspaceId = $this->workspaceId();

        $this->connections()->createConnection(
            CredentialProvider::Anthropic,
            'Restoranın kendi Claude anahtarı',
            CredentialScope::TenantByok,
            $workspaceId,
            ['api_key' => 'sk-ant-tenant-4444'],
            null,
        );

        self::assertFalse(
            $this->resolver()->isConfigured(CredentialProvider::Anthropic),
            'BYOK: tenant anahtarı platform çözümüne sızdı.',
        );
        self::assertSame([], $this->resolver()->resolve(CredentialProvider::Anthropic));
    }

    #[Test]
    public function a_byok_connection_must_name_its_workspace_and_a_platform_one_must_not(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->connections()->createConnection(
            CredentialProvider::Anthropic,
            'Sahipsiz BYOK',
            CredentialScope::TenantByok,
            null,
            ['api_key' => 'sk-ant-5555'],
            null,
        );
    }

    #[Test]
    public function a_platform_connection_cannot_claim_a_workspace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->connections()->createConnection(
            CredentialProvider::Anthropic,
            'Karışık kapsam',
            CredentialScope::PlatformOwned,
            $this->workspaceId(),
            ['api_key' => 'sk-ant-6666'],
            null,
        );
    }

    // --- CONN-LABEL-REQUIRED-01 ------------------------------------------

    /**
     * ETİKET ZORUNLUDUR ÇÜNKÜ AYIRT EDİCİ TEK ŞEYDİR.
     *
     * Aynı sağlayıcının iki bağlantısı panelde yan yana durur ve sır
     * görünmez; etiketsiz iki kart arasında superadmin hangisinin hangisi
     * olduğunu ASLA bilemez.
     */
    #[Test]
    public function a_connection_without_a_label_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            '   ',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-7777'],
            null,
        );
    }

    #[Test]
    public function a_field_outside_the_provider_schema_is_still_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'Arka kapı',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-8888', 'bogus_backdoor' => 'evil'],
            null,
        );
    }

    // --- CONN-UPDATE-PRESERVES-SECRET-01 ---------------------------------

    #[Test]
    public function updating_a_connection_without_the_secret_preserves_it(): void
    {
        $id = $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'Döndürülecek',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-original-9999', 'organization' => 'org-a'],
            null,
        );

        $this->connections()->updateConnection($id, ['organization' => 'org-b'], null);

        $resolved = $this->resolver()->resolve(CredentialProvider::OpenAi);
        self::assertSame('sk-original-9999', $resolved['api_key']);
        self::assertSame('org-b', $resolved['organization']);
    }

    // --- CONN-AUDIT-01 ----------------------------------------------------

    #[Test]
    public function every_write_leaves_a_secret_free_audit_row_naming_the_connection(): void
    {
        $id = $this->connections()->createConnection(
            CredentialProvider::OpenAi,
            'Denetlenen',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-audited-0001'],
            null,
        );

        $this->connections()->disableConnection($id, null);

        $audits = DB::table('platform_credential_audits')->orderBy('id')->get();

        self::assertCount(2, $audits);
        self::assertSame(['created', 'disabled'], $audits->pluck('action')->all());
        self::assertSame([$id, $id], array_map('intval', $audits->pluck('connection_id')->all()));

        $blob = (string) json_encode($audits, JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('sk-audited-0001', $blob);
        self::assertStringNotContainsString('••••', $blob, 'Denetim izi maske bile taşımaz.');
    }
}
