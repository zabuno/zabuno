<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Mail\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MAIL-VAULT — posta gönderici kasadan okur (Vault Faz 3).
 *
 * FF-36 aktarımı Mailgun'u sunucu `.env`'inden getirmişti. Bu faz, kasadan
 * girilen anahtarın env'in ÖNÜNE geçmesini bağlıyor: superadmin UI'dan
 * anahtar girdiği an gönderici onu kullanır, sunucuya dokunmadan.
 */
final class VaultMailConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private function selector(): MailTransportSelectorPort
    {
        return $this->app->make(MailTransportSelectorPort::class);
    }

    private function admin(): PlatformCredentialAdminPort
    {
        return $this->app->make(PlatformCredentialAdminPort::class);
    }

    // --- MAIL-VAULT-USES-VAULT-01 ----------------------------------------

    #[Test]
    public function the_vault_secret_overrides_the_env_secret(): void
    {
        // env: bir Mailgun kimliği zaten var.
        Config::set('services.mailgun.domain', 'env.mailgun.org');
        Config::set('services.mailgun.secret', 'env-secret-1111');

        // Kasa: superadmin farklı bir anahtar girdi.
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'vault.mailgun.org',
            'secret' => 'vault-secret-2222',
        ], byUserId: null);

        $mailer = $this->selector()->select();

        self::assertSame('mailgun', $mailer);
        self::assertSame('vault-secret-2222', config('services.mailgun.secret'), 'MAIL-VAULT: kasa env\'in önüne geçmedi.');
        self::assertSame('vault.mailgun.org', config('services.mailgun.domain'));
    }

    // --- MAIL-VAULT-ENV-FALLBACK-01 --------------------------------------

    #[Test]
    public function an_empty_vault_uses_the_env_credentials(): void
    {
        Config::set('services.mailgun.domain', 'env.mailgun.org');
        Config::set('services.mailgun.secret', 'env-secret-1111');

        $mailer = $this->selector()->select();

        self::assertSame('mailgun', $mailer);
        self::assertSame('env-secret-1111', config('services.mailgun.secret'));
    }

    // --- MAIL-VAULT-NONE-IS-DEFAULT-01 -----------------------------------

    #[Test]
    public function with_no_credential_anywhere_the_default_mailer_stands(): void
    {
        Config::set('services.mailgun.domain', null);
        Config::set('services.mailgun.secret', null);
        Config::set('mail.default', 'log');

        self::assertSame('log', $this->selector()->select(), 'MAIL-VAULT: kimlik yokken varsayılan gönderici kalmalı.');
    }

    // --- MAIL-VAULT-CONTACT-USES-SELECTED-01 -----------------------------

    #[Test]
    public function a_contact_message_is_sent_through_the_vault_configured_mailer(): void
    {
        Mail::fake();

        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'vault.mailgun.org',
            'secret' => 'vault-secret-2222',
        ], byUserId: null);
        Config::set('contact.notify', 'destek@zabuno.com');

        $this->post('/contact', [
            'name' => 'Hüseyin',
            'email' => 'huseyin@example.com',
            'message' => 'Kadıköy\'de 40 masalık bir restoranım var.',
        ])->assertRedirect();

        Mail::assertSent(ContactMessageReceived::class);

        $row = DB::table('contact_messages')->latest('id')->first();
        self::assertNotNull($row->delivered_at);
        // Kasa sırrı iletişim tablosuna sızmaz.
        self::assertStringNotContainsString('vault-secret-2222', (string) json_encode($row));
    }
}
