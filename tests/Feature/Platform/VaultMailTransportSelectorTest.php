<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Infrastructure\Mail\VaultMailTransportSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * MAIL-HONESTY — ÜRETİMDE "GÖNDERİLDİ" DEMEK İÇİN GİDEN BİR YOL GEREKİR.
 *
 * Riskli dal, seçicinin masum görünen ilk dalıdır: Mailgun kimliği HİÇ
 * girilmemişse `mail.default` kalır. Üretimde o değer `log` gibi bir
 * göndericiye bağlıysa mesaj sunucudaki bir dosyaya yazılır, çağıran taraf
 * "gönderildi" sanır, kullanıcı posta kutusuna bakmayı sürdürür. Hiçbir
 * yerde kırmızı bir satır belirmez, çünkü teknik olarak hata YOKTUR —
 * sadece e-posta yoktur.
 *
 * Bu dosya o dalı üretimde kapatır ve sınırlarını çizer:
 *
 *   - ÜRETİMDE kimlik yokken etkin taşıyıcı `log`, `array`, `null` ya da
 *     hiç tanımsızsa (adı ne olursa olsun — takma ad da çözülür) seçim
 *     BAŞARISIZ olur; arındırılmış istisnanın sebebi
 *     `no-outbound-transport-configured`'dır.
 *   - GERÇEK bir yedek (SMTP gibi) üretimde olduğu gibi kalır: giden bir
 *     yol vardır, e-posta çıkar.
 *   - Kimlik varsa Mailgun yine seçilir; bu paket o yolu değiştirmez.
 *   - YEREL ve TEST ortamında `log`/`array` dokunulmaz: geliştiricinin
 *     makinesinde posta sağlayıcısı zorunlu değildir.
 *
 * @see VaultMailTransportSelector
 */
final class VaultMailTransportSelectorTest extends TestCase
{
    use RefreshDatabase;

    /** Arındırma kanıtı: yarım girilmiş bir sır mesaja sızmamalı. */
    private const HALF_ENTERED_SECRET = 'half-entered-secret-9999';

    private const REFUSAL_REASON = 'no-outbound-transport-configured';

    protected function setUp(): void
    {
        parent::setUp();

        // Kasa da env de boş: "Mailgun hiç yapılandırılmamış" durumu.
        Config::set('services.mailgun.domain', null);
        Config::set('services.mailgun.secret', null);
    }

    private function selector(): MailTransportSelectorPort
    {
        return $this->app->make(MailTransportSelectorPort::class);
    }

    private function inEnvironment(string $environment): void
    {
        Config::set('app.env', $environment);
    }

    // --- MAIL-HONESTY-NO-SILENT-PRODUCTION-FALLBACK-01 -------------------

    #[Test]
    public function production_refuses_the_log_fallback_when_mailgun_is_not_configured(): void
    {
        $this->inEnvironment('production');
        Config::set('mail.default', 'log');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/'.self::REFUSAL_REASON.'/');

        $this->selector()->select();
    }

    #[Test]
    public function production_refuses_the_array_fallback_when_mailgun_is_not_configured(): void
    {
        $this->inEnvironment('production');
        Config::set('mail.default', 'array');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/'.self::REFUSAL_REASON.'/');

        $this->selector()->select();
    }

    /**
     * TAKMA AD SAKLAMAZ.
     *
     * `mail.default` bir sürücü adı değil, bir GÖNDERİCİ adıdır: operatör
     * ona `bildirim` diyebilir. Yalnız o ada bakan bir kontrol, arkasındaki
     * `log` sürücüsünü göremez ve arıza aynen sürerdi.
     */
    #[Test]
    public function production_refuses_a_named_alias_that_resolves_to_a_silent_transport(): void
    {
        $this->inEnvironment('production');
        Config::set('mail.mailers.bildirim', ['transport' => 'log']);
        Config::set('mail.default', 'bildirim');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/'.self::REFUSAL_REASON.'/');

        $this->selector()->select();
    }

    /**
     * Hiç varsayılan gönderici yoksa da cevap aynıdır: giden yol yok.
     */
    #[Test]
    public function production_refuses_when_no_default_mailer_is_configured_at_all(): void
    {
        $this->inEnvironment('production');
        Config::set('mail.default', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/'.self::REFUSAL_REASON.'/');

        $this->selector()->select();
    }

    /**
     * REDDİN MESAJI SIRRI TEKRAR ETMEZ.
     *
     * Yarım girilmiş bir kurulumda sır kasada/env'de zaten vardır; bu metin
     * günlüğe ve hata izleyicisine düşer. Operatörün ihtiyacı olan tek şey
     * HANGİ ayarın eksik olduğudur.
     */
    #[Test]
    public function the_refusal_never_repeats_a_half_entered_secret(): void
    {
        $this->inEnvironment('production');
        Config::set('services.mailgun.secret', self::HALF_ENTERED_SECRET);
        Config::set('mail.default', 'log');

        try {
            $this->selector()->select();
            self::fail('MAIL-HONESTY: üretimde giden yol yokken seçim başarılı döndü.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString(self::REFUSAL_REASON, $exception->getMessage());
            self::assertStringNotContainsString(
                self::HALF_ENTERED_SECRET,
                $exception->getMessage(),
                'MAIL-HONESTY: ret mesajı yarım girilmiş sırrı sızdırıyor.'
            );
        }
    }

    // --- MAIL-HONESTY-REAL-FALLBACK-SURVIVES-01 --------------------------

    /**
     * GERÇEK BİR YEDEK KORUNUR.
     *
     * Mailgun'a geçmemiş, hâlâ kendi SMTP sunucusundan gönderen bir kurulum
     * bu paketten etkilenmemelidir: orada e-posta gerçekten çıkar.
     */
    #[Test]
    public function production_keeps_a_real_smtp_fallback(): void
    {
        $this->inEnvironment('production');
        Config::set('mail.default', 'smtp');

        self::assertSame(
            'smtp',
            $this->selector()->select(),
            'MAIL-HONESTY: gerçekten gönderen bir yedek de reddedildi.'
        );
    }

    #[Test]
    public function production_still_selects_mailgun_when_the_vault_carries_a_usable_credential(): void
    {
        $this->inEnvironment('production');
        Config::set('mail.default', 'log');

        $this->app->make(PlatformCredentialAdminPort::class)->put(CredentialProvider::Mailgun, [
            'domain' => 'vault.mailgun.org',
            'secret' => 'vault-secret-2222',
        ], byUserId: null);

        self::assertSame(
            'mailgun',
            $this->selector()->select(),
            'MAIL-HONESTY: kimlik varken Mailgun yolu bozuldu.'
        );
    }

    // --- MAIL-HONESTY-DEV-ENVIRONMENTS-UNTOUCHED-01 ----------------------

    #[Test]
    public function the_local_environment_keeps_the_log_mailer(): void
    {
        $this->inEnvironment('local');
        Config::set('mail.default', 'log');

        self::assertSame(
            'log',
            $this->selector()->select(),
            'MAIL-HONESTY: geliştirici makinesinde posta sağlayıcısı zorunlu kılındı.'
        );
    }

    #[Test]
    public function the_testing_environment_keeps_the_array_mailer(): void
    {
        $this->inEnvironment('testing');
        Config::set('mail.default', 'array');

        self::assertSame(
            'array',
            $this->selector()->select(),
            'MAIL-HONESTY: test ortamında belleğe yazan gönderici reddedildi.'
        );
    }
}
