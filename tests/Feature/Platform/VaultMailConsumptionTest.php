<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Mail\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
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

        $row = DB::table('support_requests')->latest('id')->first();
        self::assertNotNull($row->notified_at);
        // Kasa sırrı iletişim tablosuna sızmaz.
        self::assertStringNotContainsString('vault-secret-2222', (string) json_encode($row));
    }

    // --- MAIL-VAULT-ENDPOINT-IS-A-HOST-01 --------------------------------

    /**
     * Kasadaki uç nokta alanı, taşıyıcıya HOST olarak geçer.
     *
     * Superadmin panelde gördüğü şey bir adres alanıdır ve Mailgun'un kendi
     * ekranında yazan değeri — `https://api.eu.mailgun.net/v3` — olduğu gibi
     * yapıştırmak en doğal davranıştır. Ama Symfony taşıyıcısı bu alanı
     * `https://{deger}/v3/{domain}/messages` şablonunun ORTASINA koyar:
     * yapıştırılan tam URL, `https://https://api.eu.mailgun.net/v3/v3/...`
     * gibi var olmayan bir adrese dönüşür. Ekranda hiçbir şey kırmızı
     * olmaz; e-posta yalnızca hiç gitmez.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function officialEndpointForms(): array
    {
        return [
            'çıplak host' => ['api.eu.mailgun.net', 'api.eu.mailgun.net'],
            'şema ile' => ['https://api.eu.mailgun.net', 'api.eu.mailgun.net'],
            'şema + /v3' => ['https://api.eu.mailgun.net/v3', 'api.eu.mailgun.net'],
            'şema + /v3 + eğik çizgi' => ['https://api.eu.mailgun.net/v3/', 'api.eu.mailgun.net'],
            'boşluklu yapıştırma' => ['  https://api.eu.mailgun.net/v3  ', 'api.eu.mailgun.net'],
            'büyük harfli host' => ['https://API.EU.Mailgun.NET/v3', 'api.eu.mailgun.net'],
            'US bölgesi çıplak' => ['api.mailgun.net', 'api.mailgun.net'],
        ];
    }

    #[DataProvider('officialEndpointForms')]
    #[Test]
    public function a_saved_official_endpoint_is_consumed_as_a_bare_host(string $saved, string $expectedHost): void
    {
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'vault.mailgun.org',
            'secret' => 'vault-secret-2222',
            'endpoint' => $saved,
        ], byUserId: null);

        self::assertSame('mailgun', $this->selector()->select());
        self::assertSame(
            $expectedHost,
            config('services.mailgun.endpoint'),
            'MAIL-VAULT: kaydedilmiş resmi uç nokta taşıyıcının beklediği host biçimine çevrilmedi.'
        );
    }

    #[Test]
    public function a_blank_endpoint_falls_back_to_the_default_host(): void
    {
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'vault.mailgun.org',
            'secret' => 'vault-secret-2222',
            'endpoint' => '',
        ], byUserId: null);

        self::assertSame('mailgun', $this->selector()->select());
        self::assertSame('api.mailgun.net', config('services.mailgun.endpoint'));
    }

    #[Test]
    public function the_built_transport_actually_targets_the_normalized_host(): void
    {
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'vault.mailgun.org',
            'secret' => 'vault-secret-2222',
            'endpoint' => 'https://api.eu.mailgun.net/v3',
        ], byUserId: null);

        $transport = Mail::mailer($this->selector()->select())->getSymfonyTransport();

        // Taşıyıcı bir ağ çağrısı YAPMAZ; yalnız hedefini söyler.
        self::assertStringContainsString(
            '://api.eu.mailgun.net?',
            (string) $transport,
            'MAIL-VAULT: kurulan taşıyıcı hâlâ yanlış hedefe bakıyor.'
        );
        self::assertStringNotContainsString('https://https', (string) $transport);
        self::assertStringNotContainsString('vault-secret-2222', (string) $transport);
    }

    // --- MAIL-VAULT-ENDPOINT-UNSAFE-IS-REFUSED-01 ------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeEndpointForms(): array
    {
        return [
            'URL içinde kimlik' => ['https://api:key@evil.example.com/v3'],
            'sorgu dizesi' => ['https://api.eu.mailgun.net/v3?key=abc'],
            'çapa' => ['https://api.eu.mailgun.net/v3#fragment'],
            'desteklenmeyen şema' => ['ftp://api.eu.mailgun.net'],
            'javascript şeması' => ['javascript:alert(1)'],
            'taşınamayan yol' => ['https://gateway.example.com/mailgun/api'],
            'boş host' => ['https:///v3'],
            'geçersiz host karakteri' => ['https://api eu mailgun.net/v3'],
        ];
    }

    /**
     * Taşınamayan bir uç nokta SESSİZ BİR YEDEĞE değil, AÇIK BİR ARIZAYA
     * çıkar.
     *
     * Bu davranış bir düzeltmedir. Önceki hâl, reddedilen uç noktada
     * `mail.default`'a dönüyordu; üretimde o değer `log`tur. Yani sahibin
     * yaşadığı arızanın TAM AYNISI geri geliyordu: ekranda "bağlantı
     * gönderildi", dosyada bir satır, posta kutusunda hiçbir şey. Kimlik
     * girilmiş ve yalnız uç nokta yanlış yazılmışken doğru cevap "gönderdim
     * sayılır" değil, "gönderemedim"dir.
     */
    #[DataProvider('unsafeEndpointForms')]
    #[Test]
    public function an_unsafe_endpoint_fails_loudly_instead_of_falling_back_to_the_default_mailer(string $saved): void
    {
        Config::set('mail.default', 'log');
        Config::set('services.mailgun.endpoint', 'api.mailgun.net');
        Config::set('services.mailgun.secret', 'env-secret-1111');

        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'vault.mailgun.org',
            'secret' => 'vault-secret-2222',
            'endpoint' => $saved,
        ], byUserId: null);

        $thrown = null;
        $selected = null;

        // Yakalama bloğunda iddia YOK: PHPUnit'in başarısızlık istisnası da
        // bir `RuntimeException`'dır.
        try {
            $selected = $this->selector()->select();
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        self::assertNotNull(
            $thrown,
            'MAIL-VAULT: taşınamayan uç nokta sessizce yutuldu — seçilen gönderici: '.($selected ?? '?')
        );
        self::assertStringNotContainsString(
            'vault-secret-2222',
            $thrown->getMessage(),
            'MAIL-VAULT: arıza mesajı kasa sırrını taşıyor.'
        );
        self::assertStringNotContainsString(
            trim($saved),
            $thrown->getMessage(),
            'MAIL-VAULT: arıza mesajı kaydedilmiş uç nokta değerini taşıyor — o değer kimlik içerebilir.'
        );
        self::assertSame(
            'api.mailgun.net',
            config('services.mailgun.endpoint'),
            'MAIL-VAULT: reddedilen uç nokta yine de config\'e yazılmış.'
        );
        self::assertSame(
            'env-secret-1111',
            config('services.mailgun.secret'),
            'MAIL-VAULT: uç nokta reddedildiğinde kasa sırrı hiç yüklenmemeli.'
        );
    }

    // --- MAIL-VAULT-ENDPOINT-FAILURE-IS-SANITIZED-01 ---------------------

    /**
     * Reddedilme sebebi tam olarak "değerin İÇİNDE kimlik var" olabilir.
     * O yüzden arıza yüzeyi — mesaj ve günlük — değeri hiç tekrar etmez.
     */
    #[Test]
    public function the_endpoint_failure_surface_repeats_neither_the_endpoint_nor_any_secret(): void
    {
        Config::set('mail.default', 'log');

        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'vault.mailgun.org',
            'secret' => 'vault-secret-2222',
            'endpoint' => 'https://postmaster:vault-secret-2222@gecit.example.com/mailgun/api',
        ], byUserId: null);

        // Günlük de bir arıza yüzeyidir: yazılan her satır toplanır.
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $entry) use (&$logged): void {
            $logged[] = $entry->message.' '.json_encode($entry->context);
        });

        /*
            Yakalama bloğunun içinde HİÇBİR iddia çağrılmaz: PHPUnit'in
            kendi başarısızlık istisnası da bir `RuntimeException`'dır ve
            burada yutulup testi sessizce yeşile çevirirdi.
        */
        $thrown = null;

        try {
            $this->selector()->select();
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        self::assertNotNull($thrown, 'MAIL-VAULT: taşınamayan uç nokta hiç arıza üretmedi.');

        $surfaces = array_merge([$thrown->getMessage()], $logged);

        foreach (['vault-secret-2222', 'gecit.example.com', 'postmaster'] as $needle) {
            foreach ($surfaces as $surface) {
                self::assertStringNotContainsString(
                    $needle,
                    $surface,
                    'MAIL-VAULT: arıza yüzeyi "'.$needle.'" sızdırıyor: '.$surface
                );
            }
        }
    }

    // --- MAIL-VAULT-ABSENT-CREDENTIAL-STILL-FALLS-BACK-01 ----------------

    /**
     * KİMLİK YOKKEN kural değişmez.
     *
     * Mailgun hiç yapılandırılmamışsa ortada düzeltilecek bir yanlış
     * yazım da yoktur: sistem varsayılan göndericide kalır ve hiçbir arıza
     * üretmez. Yukarıdaki sert davranış yalnız "kimlik girilmiş ama uç
     * nokta taşınamıyor" durumuna aittir.
     */
    #[Test]
    public function an_absent_credential_ignores_an_unusable_endpoint_without_failing(): void
    {
        Config::set('services.mailgun.domain', null);
        Config::set('services.mailgun.secret', null);
        Config::set('services.mailgun.endpoint', 'https://api:key@evil.example.com/v3');
        Config::set('mail.default', 'log');

        self::assertSame(
            'log',
            $this->selector()->select(),
            'MAIL-VAULT: kimlik yokken varsayılan gönderici kalmalı — uç nokta ne yazarsa yazsın.'
        );
    }

    // --- MAIL-VAULT-ENDPOINT-HOST-IS-OPERATOR-TRUST-01 -------------------

    /**
     * KAYDA GEÇİRİLMİŞ SINIR: sözdizimsel olarak geçerli HERHANGİ bir host
     * kabul edilir ve Mailgun API anahtarı oraya gider.
     *
     * Normalleştirici bir izin listesi DEĞİLDİR; yalnız taşınabilirlik
     * kontrolüdür. `relay.example.com` yazılırsa istek gerçekten oraya
     * kurulur. Bu, superadmin panelini kullanan operatörün yetkisi
     * dahilinde bilinçli olarak bırakılmış bir ayardır (kendi ara
     * sunucusunu ya da bir Mailgun bölgesini yazabilmesi için) — ama
     * "yanlış host yazılamaz" diye okunmamalıdır. Bu test o cümlenin
     * belgeyle aynı kaldığını garanti eder.
     */
    #[Test]
    public function an_arbitrary_syntactically_valid_host_is_accepted_as_an_operator_choice(): void
    {
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'vault.mailgun.org',
            'secret' => 'vault-secret-2222',
            'endpoint' => 'relay.example.com',
        ], byUserId: null);

        self::assertSame('mailgun', $this->selector()->select());
        self::assertSame(
            'relay.example.com',
            config('services.mailgun.endpoint'),
            'MAIL-VAULT: belgelenen sınır ile davranış ayrıştı — normalleştirici bir izin listesi sanılıyor.'
        );
    }

    // --- MAIL-VAULT-NO-STALE-TRANSPORT-01 --------------------------------

    #[Test]
    public function a_cached_transport_cannot_outlive_a_changed_vault_value(): void
    {
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'first.mailgun.org',
            'secret' => 'first-secret-1111',
            'endpoint' => 'api.mailgun.net',
        ], byUserId: null);

        $first = (string) Mail::mailer($this->selector()->select())->getSymfonyTransport();
        self::assertStringContainsString('domain=first.mailgun.org', $first);

        // Superadmin panelden bölgeyi ve alan adını değiştirdi.
        $this->admin()->put(CredentialProvider::Mailgun, [
            'domain' => 'second.mailgun.org',
            'secret' => 'second-secret-2222',
            'endpoint' => 'https://api.eu.mailgun.net/v3',
        ], byUserId: null);

        $second = (string) Mail::mailer($this->selector()->select())->getSymfonyTransport();

        self::assertStringContainsString(
            'domain=second.mailgun.org',
            $second,
            'MAIL-VAULT: önbellekteki taşıyıcı yeni kasa değerini yendi.'
        );
        self::assertStringContainsString('://api.eu.mailgun.net?', $second);
    }
}
