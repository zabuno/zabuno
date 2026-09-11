<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PRD-MAILGUN — "şifremi unuttum" e-postası GERÇEKTEN çıkar mı?
 *
 * Sahibin bildirdiği arıza: superadmin panelden Mailgun kimliği girilmiş,
 * iletişim formu ve kayıt doğrulaması çalışıyor, ama şifre sıfırlama
 * e-postası hiç gelmiyor. Sebep, ekranda görünmeyen bir çatallanmaydı:
 * doğrulama e-postası kasadan seçilen göndericiyi kullanıyor
 * (`User::deliverEmailVerificationLink`), şifre sıfırlama ise çerçevenin
 * `ResetPassword` bildirimiyle gidiyor ve o bildirim hiçbir gönderici
 * SEÇMİYOR — `mail.default`'a düşüyor, üretimde `log`. Yani e-posta
 * "gönderildi" sayılıyor, bir dosyaya yazılıyor ve kimseye ulaşmıyor.
 *
 * BU DOSYA `Notification::fake()` KULLANMAZ. Sahte bildirim katmanı tam
 * olarak kırık olan yeri — bildirimin hangi gönderici üzerinden postaya
 * verildiğini — atlar; `Notification::assertSentTo` arıza sürerken de
 * yeşil yanardı. Burada bildirim gerçek posta kanalından geçer, yalnız
 * taşıyıcı belleğe yazan `array` sürücüsüdür: ağ yok, sağlayıcı çağrısı
 * yok, gerçek alıcı yok.
 */
final class PasswordResetDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const FORGOT_PASSWORD_URI = '/forgot-password';

    private const RESET_PASSWORD_URI = '/reset-password';

    private const LOGIN_URI = '/login';

    private const VALID_PASSWORD = 'correct-horse-battery-staple-1';

    private const NEW_PASSWORD = 'new-correct-horse-battery-2';

    /** Kasadan seçilmiş gibi davranan, belleğe yazan gönderici adı. */
    private const SELECTED_MAILER = 'vault-probe';

    /**
     * Taşıyıcının host alanına SIĞMAYAN, üstelik içinde kimlik taşıyan
     * bir uç nokta — panele yapıştırılabilecek en kötü hâli.
     */
    private const UNUSABLE_ENDPOINT = 'https://postmaster:probe-secret-3333@gecit.example.com/mailgun/api';

    private const MAILGUN_SECRET = 'probe-secret-3333';

    protected function setUp(): void
    {
        parent::setUp();

        // Varsayılan gönderici de belleğe yazar: böylece "hangi kutuya
        // düştü?" sorusu iki tarafı da sayarak cevaplanabilir.
        Config::set('mail.default', 'array');
        Config::set('mail.mailers.'.self::SELECTED_MAILER, ['transport' => 'array']);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $email = 'ada@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make(self::VALID_PASSWORD),
        ]);
    }

    /**
     * Kasa seçicisinin yerine, hangi göndericinin seçildiğini
     * ispatlanabilir kılan sabit bir ad koyar.
     */
    private function selectMailer(string $name): void
    {
        $this->app->bind(MailTransportSelectorPort::class, fn (): MailTransportSelectorPort => new class($name) implements MailTransportSelectorPort
        {
            public function __construct(private readonly string $name) {}

            public function select(): string
            {
                return $this->name;
            }
        });
    }

    private function mailbox(string $mailer): ArrayTransport
    {
        $transport = Mail::mailer($mailer)->getSymfonyTransport();

        self::assertInstanceOf(ArrayTransport::class, $transport);

        return $transport;
    }

    // --- PRD-MAILGUN-SELECTED-MAILER-01 ---------------------------------

    #[Test]
    public function the_password_reset_mail_leaves_through_the_selected_mailer(): void
    {
        $this->selectMailer(self::SELECTED_MAILER);
        $user = $this->verifiedUser();

        $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email])
            ->assertOk();

        self::assertCount(
            1,
            $this->mailbox(self::SELECTED_MAILER)->messages(),
            'PRD-MAILGUN: şifre sıfırlama e-postası kasadan seçilen göndericiye verilmedi.'
        );
    }

    #[Test]
    public function the_password_reset_mail_does_not_fall_back_to_the_default_mailer(): void
    {
        $this->selectMailer(self::SELECTED_MAILER);
        $user = $this->verifiedUser();

        $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email])
            ->assertOk();

        self::assertCount(
            0,
            $this->mailbox('array')->messages(),
            'PRD-MAILGUN: e-posta hâlâ mail.default göndericisine düşüyor — üretimde bu `log` demektir.'
        );
    }

    // --- PRD-MAILGUN-FRAMEWORK-SEMANTICS-01 ------------------------------

    #[Test]
    public function the_selected_mailer_still_carries_the_framework_reset_message(): void
    {
        $this->selectMailer(self::SELECTED_MAILER);
        $user = $this->verifiedUser();

        $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);

        $message = $this->mailbox(self::SELECTED_MAILER)->messages()->first();
        self::assertNotNull($message, 'PRD-MAILGUN: seçilen göndericiye hiç mesaj ulaşmadı.');

        $email = $message->getOriginalMessage();

        self::assertSame(
            trans('Reset your password'),
            $email->getSubject(),
            'PRD-MAILGUN: çerçevenin mevcut konu metni korunmalı, yeniden yazılmamalı.'
        );
        self::assertStringContainsString(
            self::RESET_PASSWORD_URI.'/',
            (string) $email->getHtmlBody(),
            'PRD-MAILGUN: gövde çerçevenin reset bağlantısını taşımalı.'
        );
        self::assertStringContainsString(
            (string) config('auth.passwords.users.expire'),
            (string) $email->getHtmlBody(),
            'PRD-MAILGUN: çerçevenin süre satırı (expire dakikası) korunmalı.'
        );
    }

    #[Test]
    public function the_token_inside_the_delivered_mail_still_resets_the_password(): void
    {
        $this->selectMailer(self::SELECTED_MAILER);
        $user = $this->verifiedUser();

        $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);

        $message = $this->mailbox(self::SELECTED_MAILER)->messages()->first();
        self::assertNotNull($message, 'PRD-MAILGUN: seçilen göndericiye hiç mesaj ulaşmadı.');

        $body = (string) $message->getOriginalMessage()->getHtmlBody();
        self::assertSame(
            1,
            preg_match('#'.preg_quote(self::RESET_PASSWORD_URI, '#').'/([A-Za-z0-9]+)\?#', $body, $matches),
            'PRD-MAILGUN: gönderilen e-postadan reset token okunamadı.'
        );

        $this->withHeaders($this->jsonHeaders())->post(self::RESET_PASSWORD_URI, [
            'token' => $matches[1],
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSuccessful();

        $this->withHeaders($this->jsonHeaders())->post(self::LOGIN_URI, [
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
        ])->assertSuccessful();
    }

    // --- PRD-MAILGUN-ENUMERATION-SAFE-01 ---------------------------------

    #[Test]
    public function an_unknown_email_still_gets_the_generic_answer_and_sends_nothing(): void
    {
        $this->selectMailer(self::SELECTED_MAILER);
        $user = $this->verifiedUser();

        $known = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);
        $unknown = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => 'no-such-account@example.com']);

        $known->assertStatus($unknown->getStatusCode());
        self::assertSame($known->getContent(), $unknown->getContent());
        self::assertCount(
            1,
            $this->mailbox(self::SELECTED_MAILER)->messages(),
            'PRD-MAILGUN: yalnız var olan hesap için tek bir e-posta çıkmalı.'
        );
    }

    // --- PRD-MAILGUN-BAD-ENDPOINT-NO-FAKE-SUCCESS-01 ---------------------

    /**
     * Kimlik GİRİLMİŞ ama uç nokta yanlış yazılmış: sonuç "gönderildi"
     * OLAMAZ.
     *
     * Bu, sahibin yaşadığı arızanın en sinsi biçimidir. Superadmin panelde
     * her kutu dolu görünür, ekranda "bağlantı gönderildi" yazar ve posta
     * kutusuna hiçbir şey düşmez — çünkü sistem, taşıyamadığı uç noktayı
     * görünce sessizce `mail.default`'a dönüyordu; üretimde o değer `log`.
     * Burada seçici gerçek olanıdır (sahte seçici bağlanmaz): yanlış
     * yazılmış uç nokta artık generic başarı yanıtına yutulmuyor ve
     * hiçbir posta kutusuna sahte bir mesaj bırakmıyor.
     */
    #[Test]
    public function a_configured_but_unusable_mailgun_endpoint_cannot_report_a_sent_reset(): void
    {
        Config::set('services.mailgun.domain', 'vault.mailgun.org');
        Config::set('services.mailgun.secret', self::MAILGUN_SECRET);
        Config::set('services.mailgun.endpoint', self::UNUSABLE_ENDPOINT);

        $user = $this->verifiedUser();

        $response = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);

        self::assertNotSame(
            200,
            $response->getStatusCode(),
            'PRD-MAILGUN: taşınamayan uç nokta hâlâ generic başarı yanıtı veriyor — sahte başarı geri geldi.'
        );
        self::assertCount(
            0,
            $this->mailbox('array')->messages(),
            'PRD-MAILGUN: uç nokta taşınamazken e-posta yine varsayılan göndericiye düştü.'
        );

        // Arıza yüzeyi sırrı da hedefi de tekrar etmez.
        $body = (string) $response->getContent();
        foreach ([self::MAILGUN_SECRET, 'gecit.example.com', 'postmaster'] as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $body,
                'PRD-MAILGUN: hata yanıtı "'.$needle.'" sızdırıyor.'
            );
        }
    }

    /**
     * Aynı yanlış uç nokta, KİMLİK YOKKEN hiçbir şeyi bozmaz.
     *
     * Mailgun hiç yapılandırılmamışsa düzeltilecek bir yazım da yoktur:
     * akış varsayılan gönderici üzerinden eskisi gibi yürür. Yukarıdaki
     * sert davranışın kapsamı budur ve burada sınırı çizilir.
     */
    #[Test]
    public function an_unusable_endpoint_without_credentials_leaves_the_journey_untouched(): void
    {
        Config::set('services.mailgun.domain', null);
        Config::set('services.mailgun.secret', null);
        Config::set('services.mailgun.endpoint', self::UNUSABLE_ENDPOINT);

        $user = $this->verifiedUser();

        $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email])
            ->assertOk();

        self::assertCount(
            1,
            $this->mailbox('array')->messages(),
            'PRD-MAILGUN: kimlik yokken sıfırlama e-postası varsayılan göndericiden çıkmalı.'
        );
    }

    /**
     * ARIZA, KAYITLI ADRESLE KAYITSIZ ADRESİ AYIRT EDEMEZ.
     *
     * İlk düzeltmede arıza doğru yerde patlıyordu ama ÇOK GEÇ: hesap
     * arandıktan sonra, bildirim hazırlanırken. Sonuç iki farklı cevaptı —
     * kayıtlı adres 500, kayıtsız adres 200. Bu fark bir sızıntıdır ve
     * ölçeklenir: saldırgan elindeki listeyi tek tek yazar, hangi
     * adreslerin bu sistemde hesabı olduğunu cevapların RENGİNDEN okur.
     * Hiçbir metin sızmasa bile liste sızmış olur.
     *
     * Ön kontrol hesap aranmadan önce yapıldığı için artık iki istek de
     * BAYT BAYT aynı cevabı alır.
     */
    #[Test]
    public function an_unusable_endpoint_answers_known_and_unknown_emails_identically(): void
    {
        Config::set('services.mailgun.domain', 'vault.mailgun.org');
        Config::set('services.mailgun.secret', self::MAILGUN_SECRET);
        Config::set('services.mailgun.endpoint', self::UNUSABLE_ENDPOINT);

        $user = $this->verifiedUser();

        $known = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);
        $unknown = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => 'no-such-account@example.com']);

        self::assertSame(
            $unknown->getStatusCode(),
            $known->getStatusCode(),
            'PRD-MAILGUN: arıza kayıtlı adresle kayıtsız adresi ayırt ediyor — hesap sayma oracle\'ı.'
        );
        self::assertSame($unknown->getContent(), $known->getContent());
        self::assertNotSame(500, $known->getStatusCode());
    }

    /**
     * Arızalı taşıyıcının cevabı "gönderdik" DEĞİLDİR.
     *
     * Sahibin şikâyetinin özü buydu: ekranda "bağlantı gönderildi" yazıyor,
     * posta kutusuna hiçbir şey düşmüyordu. Ekran, bilmediği bir şeyi
     * söylememelidir.
     */
    #[Test]
    public function an_unusable_endpoint_never_claims_a_reset_link_was_sent(): void
    {
        Config::set('services.mailgun.domain', 'vault.mailgun.org');
        Config::set('services.mailgun.secret', self::MAILGUN_SECRET);
        Config::set('services.mailgun.endpoint', self::UNUSABLE_ENDPOINT);

        $user = $this->verifiedUser();

        $response = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);

        self::assertStringNotContainsString(
            (string) trans('passwords.sent'),
            (string) $response->getContent(),
            'PRD-MAILGUN: gönderemediğimiz hâlde "gönderdik" diyoruz.'
        );
        self::assertStringContainsString(
            (string) trans('auth.password_reset_unavailable'),
            (string) $response->getContent(),
            'PRD-MAILGUN: kullanıcıya ne olduğunu söyleyen arındırılmış cevap dönmedi.'
        );
    }

    // --- PRD-MAILGUN-NO-FAKE-SUCCESS-01 ----------------------------------

    #[Test]
    public function a_broken_mailer_selection_is_not_reported_as_a_sent_mail(): void
    {
        // Seçilen gönderici yapılandırmada yok: bu bir yapılandırma
        // arızasıdır ve sessizce "gönderildi" sayılmamalıdır.
        $this->selectMailer('mailer-that-does-not-exist');
        $user = $this->verifiedUser();

        $response = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);

        self::assertNotSame(
            200,
            $response->getStatusCode(),
            'PRD-MAILGUN: taşıyıcı hatası generic başarı yanıtına yutulmamalı.'
        );
        self::assertCount(0, $this->mailbox('array')->messages());

        // Yapılandırmada karşılığı olmayan bir gönderici adı da aynı
        // çatallanmayı üretirdi; ön kontrol seçileni KURMAYI da denediği
        // için bu yol da kayıtlı/kayıtsız ayrımı yapmaz.
        $unknown = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => 'no-such-account@example.com']);

        self::assertSame($unknown->getStatusCode(), $response->getStatusCode());
        self::assertSame($unknown->getContent(), $response->getContent());
    }

    // --- PRD-MAILGUN-PRODUCTION-NO-TRANSPORT-01 --------------------------

    /**
     * KİMLİK HİÇ GİRİLMEMİŞ: ÜRETİMDE BU DA "GÖNDERİLDİ" DEĞİLDİR.
     *
     * Yanlış yazılmış uç nokta ayrıca yakalanır; buradaki hâl ondan daha
     * sadedir — Mailgun alanları hiç doldurulmamıştır. Seçici o zaman
     * `mail.default`'a döner; üretimde o değer giden bir yola bağlı
     * değilse (`log` gibi) mesaj sunucudaki bir dosyaya yazılır, ekranda
     * "bağlantı gönderildi" belirir, posta kutusuna hiçbir şey düşmez.
     * Burada üretim ortamında aynı yolculuk yürütülür ve cevabın
     * "gönderdik" OLMADIĞI sabitlenir.
     */
    #[Test]
    public function production_without_any_mailgun_credential_never_claims_a_reset_link_was_sent(): void
    {
        Config::set('app.env', 'production');
        Config::set('services.mailgun.domain', null);
        Config::set('services.mailgun.secret', null);

        $user = $this->verifiedUser();

        $response = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);

        self::assertNotSame(
            200,
            $response->getStatusCode(),
            'PRD-MAILGUN: üretimde giden yol yokken hâlâ generic başarı yanıtı dönüyor.'
        );
        self::assertStringContainsString(
            (string) trans('auth.password_reset_unavailable'),
            (string) $response->getContent(),
            'PRD-MAILGUN: kullanıcıya arındırılmış "gönderemedik" cevabı dönmedi.'
        );
        self::assertCount(
            0,
            $this->mailbox('array')->messages(),
            'PRD-MAILGUN: üretimde e-posta yine sessiz varsayılan göndericiye düştü.'
        );
    }

    /**
     * Aynı engel, kayıtlı adresle kayıtsız adresi AYIRT ETMEZ.
     *
     * Ön kontrol hesap aranmadan önce yürüdüğü için bu yol da hesap sayma
     * oracle'ı üretmez: iki istek de bayt bayt aynı cevabı alır.
     */
    #[Test]
    public function production_without_any_mailgun_credential_answers_known_and_unknown_emails_identically(): void
    {
        Config::set('app.env', 'production');
        Config::set('services.mailgun.domain', null);
        Config::set('services.mailgun.secret', null);

        $user = $this->verifiedUser();

        $known = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);
        $unknown = $this->withHeaders($this->jsonHeaders())
            ->post(self::FORGOT_PASSWORD_URI, ['email' => 'no-such-account@example.com']);

        self::assertSame($unknown->getStatusCode(), $known->getStatusCode());
        self::assertSame($unknown->getContent(), $known->getContent());
        self::assertNotSame(500, $known->getStatusCode());
    }

    // --- PRD-MAILGUN-HTML-VISIBLE-FAILURE-01 -----------------------------

    /**
     * TARAYICIDAN GELEN KİŞİ SEBEBİ EKRANDA GÖRÜR.
     *
     * JSON istemcisi 503'ü okur; formu dolduran insan okumaz — o yalnız
     * geri döndüğü sayfaya bakar. Bu kontrol daha önce rota yığınından
     * ÖNCE (`RouteMatched`) koşuyordu ve orada oturum henüz kurulmadığı
     * için hata kesesi kaydedilemiyordu: kullanıcı boş bir forma dönüyor,
     * hiçbir sebep göremiyordu. Ara katmana taşındıktan sonra yönlendirme
     * oturumun arkasında üretilir ve hata forma kadar gelir.
     */
    #[Test]
    public function the_browser_journey_sees_the_english_failure_on_the_form(): void
    {
        Config::set('services.mailgun.domain', 'vault.mailgun.org');
        Config::set('services.mailgun.secret', self::MAILGUN_SECRET);
        Config::set('services.mailgun.endpoint', self::UNUSABLE_ENDPOINT);

        $user = $this->verifiedUser();

        $response = $this->from(self::FORGOT_PASSWORD_URI)
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);

        $response->assertRedirect(self::FORGOT_PASSWORD_URI);
        $response->assertSessionHasErrors([
            'email' => (string) trans('auth.password_reset_unavailable', [], 'en'),
        ]);
        self::assertCount(
            0,
            $this->mailbox('array')->messages(),
            'PRD-MAILGUN: HTML akışında da hiçbir posta çıkmamalı.'
        );
    }

    /**
     * Aynı sebep, Türkçe arayüzde Türkçe görünür.
     *
     * Dil pazarlığı rota yığınının içinde yapılır (`NegotiateLocale`);
     * kontrol o yığından önce koşsaydı metin İngilizce donardı.
     */
    #[Test]
    public function the_browser_journey_sees_the_turkish_failure_on_the_form(): void
    {
        Config::set('services.mailgun.domain', 'vault.mailgun.org');
        Config::set('services.mailgun.secret', self::MAILGUN_SECRET);
        Config::set('services.mailgun.endpoint', self::UNUSABLE_ENDPOINT);

        $user = $this->verifiedUser();

        $response = $this->from(self::FORGOT_PASSWORD_URI)
            ->withHeaders(['Accept-Language' => 'tr'])
            ->post(self::FORGOT_PASSWORD_URI, ['email' => $user->email]);

        $response->assertRedirect(self::FORGOT_PASSWORD_URI);
        $response->assertSessionHasErrors([
            'email' => (string) trans('auth.password_reset_unavailable', [], 'tr'),
        ]);
    }
}
