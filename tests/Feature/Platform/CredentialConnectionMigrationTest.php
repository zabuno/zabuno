<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GÖÇ VERİYİ TAŞIR, ATMAZ — `docs/95` Faz 3 şema evrimi.
 *
 * Bu testin sebebi somut: normal test paketi hep BOŞ bir veritabanından
 * başlar, dolayısıyla göçün kopyalama döngüsü hiçbir zaman çalışmaz ve
 * "yeşil paket" onun doğru olduğunu KANITLAMAZ. Oysa üretimde o döngü tam
 * bir kez çalışır ve yanlışsa, sahibin kasaya girdiği Mailgun/OpenAI
 * anahtarları geri dönüşü olmadan kaybolur.
 *
 * Burada göç gerçekten geriye alınır, eski şemaya gerçek bir satır yazılır
 * ve göç yeniden ileri sürülür.
 */
final class CredentialConnectionMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_existing_single_row_vault_becomes_a_default_connection_without_losing_the_secret(): void
    {
        // Göçü geriye al: eski `platform_credentials` tablosu geri gelir.
        Artisan::call('migrate:rollback', ['--step' => 1]);

        self::assertTrue(Schema::hasTable('platform_credentials'), 'Geri alma eski tabloyu kurmadı.');
        self::assertFalse(Schema::hasTable('platform_credential_connections'));

        // Sahibin kasaya girmiş olduğu gerçek bir kayıt — sır ŞİFRELİ.
        $secret = 'mg-real-key-that-must-survive-7a3f';

        DB::table('platform_credentials')->insert([
            'provider' => 'mailgun',
            'plain_fields' => json_encode(['domain' => 'mg.zabuno.com', 'endpoint' => 'api.eu.mailgun.net']),
            'secret_ciphertext' => Crypt::encryptString(json_encode(['secret' => $secret])),
            'secret_hints' => json_encode(['secret' => '••••7a3f']),
            'state' => 'active',
            'last_rotated_at' => now(),
            'set_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ve göçü yeniden ileri sür.
        Artisan::call('migrate');

        self::assertFalse(
            Schema::hasTable('platform_credentials'),
            'Göç eski tabloyu bırakmış — iki kaynak, iki gerçek.',
        );

        $connections = $this->app->make(PlatformConnectionAdminPort::class)
            ->connections(CredentialProvider::Mailgun);

        self::assertCount(1, $connections);
        self::assertSame('Varsayılan', $connections[0]->label);
        self::assertSame(CredentialScope::PlatformOwned, $connections[0]->scope);
        self::assertTrue($connections[0]->configured);

        // ASIL KANIT: sır hâlâ çözülebiliyor. Göç onu çözmedi, olduğu gibi
        // taşıdı — ve tüketici tarafı hiçbir şey olmamış gibi okuyor.
        $resolved = $this->app->make(CredentialResolverPort::class)
            ->resolve(CredentialProvider::Mailgun);

        self::assertSame($secret, $resolved['secret']);
        self::assertSame('mg.zabuno.com', $resolved['domain']);
        self::assertSame('api.eu.mailgun.net', $resolved['endpoint']);
    }

    #[Test]
    public function rolling_back_returns_the_oldest_platform_connection_to_the_old_table(): void
    {
        $admin = $this->app->make(PlatformConnectionAdminPort::class);

        $admin->createConnection(
            CredentialProvider::OpenAi,
            'İlk',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-oldest-1111'],
            null,
        );
        $admin->createConnection(
            CredentialProvider::OpenAi,
            'İkinci',
            CredentialScope::PlatformOwned,
            null,
            ['api_key' => 'sk-newer-2222'],
            null,
        );

        Artisan::call('migrate:rollback', ['--step' => 1]);

        $rows = DB::table('platform_credentials')->where('provider', 'openai')->get();

        // Eski şemada sağlayıcı başına tek satır var; hangisinin "asıl"
        // olduğunu uydurmak yerine EN ESKİSİ seçilir — belirlenebilir bir
        // kural, rastgele bir tercih değil.
        self::assertCount(1, $rows);
        self::assertSame(
            'sk-oldest-1111',
            json_decode(Crypt::decryptString((string) $rows[0]->secret_ciphertext), true)['api_key'],
        );
    }
}
