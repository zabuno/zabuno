<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * VAULT — platform sağlayıcı kimlik-bilgisi kasası (Faz 1).
 *
 * Buradaki her kapı bir GÜVENLİK özelliğini donduruyor. Sır asla düz yazıya
 * çıkmaz, asla geri okunmaz, kasa boşsa env yedeğe düşer. Kanonik disiplin:
 * `modules/ai-provider-account-vault.md` §Security / §Data retention.
 */
final class PlatformCredentialVaultTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): PlatformCredentialAdminPort
    {
        return $this->app->make(PlatformCredentialAdminPort::class);
    }

    private function resolver(): CredentialResolverPort
    {
        return $this->app->make(CredentialResolverPort::class);
    }

    // --- VAULT-ENCRYPTED-AT-REST-01 --------------------------------------

    #[Test]
    public function the_secret_is_never_stored_as_plaintext(): void
    {
        $secret = 'mg-super-secret-value-b1c0';

        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'sandbox123.mailgun.org',
            'secret' => $secret,
            'endpoint' => 'api.mailgun.net',
        ], byUserId: null);

        $row = DB::table('platform_credentials')->where('provider', 'mailgun')->first();
        self::assertNotNull($row, 'VAULT: satır yazılmadı.');

        $blob = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::assertStringNotContainsString(
            $secret,
            (string) $blob,
            'VAULT-ENCRYPTED-AT-REST-01: sır satırda DÜZ yazıyla duruyor.',
        );

        // Ama tüketici çözebilmeli — şifreleme geri döndürülebilir olmalı.
        self::assertSame($secret, $this->resolver()->resolve(CredentialProvider::Mailgun)['secret']);
    }

    // --- VAULT-MASKED-READBACK-01 ----------------------------------------

    #[Test]
    public function the_admin_surface_returns_a_mask_never_the_secret(): void
    {
        $secret = 'mg-super-secret-value-b1c0';

        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'sandbox123.mailgun.org',
            'secret' => $secret,
            'endpoint' => 'api.mailgun.net',
        ], byUserId: null);

        $status = $this->admin()->status(CredentialProvider::Mailgun);
        $json = json_encode($status->toArray(), JSON_UNESCAPED_UNICODE);

        self::assertStringNotContainsString(
            $secret,
            (string) $json,
            'VAULT-MASKED-READBACK-01: tam sır maskeli durumda görünüyor.',
        );

        self::assertTrue($status->configured);

        $byName = [];
        foreach ($status->fields as $field) {
            $byName[$field->name] = $field;
        }

        // Sır alan: set + maskeli son 4; tam değer değil.
        self::assertTrue($byName['secret']->isSet);
        self::assertNotNull($byName['secret']->preview);
        self::assertStringEndsWith('b1c0', (string) $byName['secret']->preview);
        self::assertStringNotContainsString('super-secret', (string) $byName['secret']->preview);

        // Düz alan: tam değer görünebilir — sır değil.
        self::assertSame('sandbox123.mailgun.org', $byName['domain']->preview);
    }

    // --- VAULT-ENV-FALLBACK-01 -------------------------------------------

    #[Test]
    public function an_empty_vault_falls_back_to_env_and_the_vault_wins_when_set(): void
    {
        Config::set('services.mailgun.domain', 'envdomain.mailgun.org');
        Config::set('services.mailgun.secret', 'env-secret-9999');
        Config::set('services.mailgun.endpoint', 'api.mailgun.net');

        // Kasa boş: env yedeğe düşer.
        self::assertSame('env-secret-9999', $this->resolver()->resolve(CredentialProvider::Mailgun)['secret']);

        // Kasa doldurulunca env'in önüne geçer.
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'vaultdomain.mailgun.org',
            'secret' => 'vault-secret-0001',
        ], byUserId: null);

        self::assertSame('vault-secret-0001', $this->resolver()->resolve(CredentialProvider::Mailgun)['secret']);
    }

    // --- VAULT-ROTATE-PRESERVES-UNTOUCHED-SECRET-01 ----------------------

    #[Test]
    public function omitting_a_secret_on_update_preserves_the_previous_one(): void
    {
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'a.mailgun.org',
            'secret' => 'first-secret-aaaa',
        ], byUserId: null);

        // Panel sırrı geri okuyamaz; yalnız domain'i değiştiriyor, secret boş.
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'b.mailgun.org',
        ], byUserId: null);

        $resolved = $this->resolver()->resolve(CredentialProvider::Mailgun);
        self::assertSame('b.mailgun.org', $resolved['domain']);
        self::assertSame('first-secret-aaaa', $resolved['secret'], 'VAULT-ROTATE: dokunulmayan sır kayboldu.');
    }

    // --- VAULT-DISABLE-01 ------------------------------------------------

    #[Test]
    public function a_disabled_provider_is_not_resolved(): void
    {
        $this->admin()->put(CredentialProvider::OpenAi, [
            'api_key' => 'sk-live-should-not-leak-2222',
        ], byUserId: null);

        self::assertTrue($this->resolver()->isConfigured(CredentialProvider::OpenAi));

        $this->admin()->disable(CredentialProvider::OpenAi);

        self::assertFalse($this->resolver()->isConfigured(CredentialProvider::OpenAi));
        self::assertSame([], $this->resolver()->resolve(CredentialProvider::OpenAi));
        self::assertSame('disabled', $this->admin()->status(CredentialProvider::OpenAi)->state);
    }

    // --- VAULT-UNKNOWN-FIELD-REJECTED-01 ---------------------------------

    #[Test]
    public function a_field_outside_the_provider_schema_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'a.mailgun.org',
            'secret' => 'x',
            'bogus_backdoor' => 'evil',
        ], byUserId: null);
    }

    // --- VAULT-ALL-LISTS-EVERY-PROVIDER-01 -------------------------------

    #[Test]
    public function the_list_shows_every_known_provider_even_unconfigured(): void
    {
        $all = $this->admin()->all();

        $providers = array_map(static fn (CredentialStatus $s): string => $s->provider->value, $all);
        sort($providers);

        self::assertSame(
            ['anthropic', 'custom_endpoint', 'gemini', 'iyzico', 'kimi', 'mailgun', 'openai'],
            $providers,
        );
        foreach ($all as $status) {
            self::assertFalse($status->configured, 'Hiçbiri yapılandırılmadan configured=true olmamalı.');
        }
    }
}
