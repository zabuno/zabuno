<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * P0-06 RED — doğrulama e-postası da SESSİZ DÜŞMEZ (`docs/110` P0-06).
 *
 * `App\Models\User::sendEmailVerificationNotification()` çıplak bir
 * `Mail::to(...)->send(...)` çağırıyordu ve iki ayrı kusuru vardı.
 *
 * BİRİNCİSİ: gönderim kasadan seçilen sürücüyü kullanmıyordu. Superadmin
 * panelden Mailgun anahtarını giriyor, iletişim formu çalışmaya başlıyor,
 * kayıt doğrulaması hâlâ `mail.default` (üretimde `log`) üzerinden hiçbir
 * yere gitmiyordu.
 *
 * İKİNCİSİ: taşıyıcı patlarsa kayıt isteğinin kendisi 500 veriyordu. Kişi
 * ürüne kaydolmuş oluyor ama ekranda bir sunucu hatası görüyor; tekrar
 * denediğinde "bu e-posta zaten kayıtlı" duvarına çarpıyordu.
 *
 * Ama kayıt akışını kurtarmak, YENİDEN GÖNDERME ekranının yalan söylemesine
 * izin vermek değildir: uç her hâlükârda 202 dönerse ekran "doğrulama
 * e-postası gönderildi" yazar ve kullanıcı olmayan bir e-postayı bekler.
 * Bu yüzden iki yol AYRILIR — kayıt yutar ve loglar, yeniden gönderme
 * gerçeği söyler.
 *
 * Requirement IDs: VERIFY-MAIL-VAULT-TRANSPORT-01,
 * VERIFY-MAIL-REGISTRATION-SURVIVES-02, VERIFY-MAIL-RESEND-HONEST-03,
 * VERIFY-MAIL-RESEND-OK-04, VERIFY-MAIL-NO-LEAK-05.
 */
final class VerificationEmailDeliveryHonestyTest extends TestCase
{
    use RefreshDatabase;

    private const RESEND_URI = '/email/verification-notification';

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    // --- VERIFY-MAIL-VAULT-TRANSPORT-01 -----------------------------------

    public function test_the_verification_email_leaves_through_the_vault_selected_transport(): void
    {
        Mail::fake();

        $this->app->bind(MailTransportSelectorPort::class, fn (): MailTransportSelectorPort => new class implements MailTransportSelectorPort
        {
            public function select(): string
            {
                return 'kasadan-secilen-surucu';
            }
        });

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->withHeaders($this->jsonHeaders())->post(self::RESEND_URI);

        // Ad UYDURMA bir addır: varsayılan sürücüyle karışan bir ad
        // (`array`, `log`) seçicinin hiç çağrılmadığı hâlde de geçerdi.
        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail): bool {
            return $mail->mailer === 'kasadan-secilen-surucu';
        });
    }

    // --- VERIFY-MAIL-REGISTRATION-SURVIVES-02 -----------------------------

    public function test_registration_survives_a_transport_that_is_down(): void
    {
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('mailgun ulaşılamıyor'));

        $response = $this->withHeaders($this->jsonHeaders())->postJson('/register', [
            'name' => 'Hüseyin Demir',
            'email' => 'huseyin-verify-hon-02@example.test',
            'password' => 'Cok-Guclu-Parola-1',
            'password_confirmation' => 'Cok-Guclu-Parola-1',
        ]);

        /*
            HESAP AÇILMIŞSA AÇILMIŞ KALIR.

            Taşıyıcı düştü diye kaydı 500'e çevirmek, kişiyi ürüne
            sokamayan bir hesapla baş başa bırakırdı: tekrar denediğinde
            "bu e-posta zaten kayıtlı" duvarına çarpar ve elinde ne hesap ne
            de doğrulama bağlantısı kalır. Kayıt tamamlanır; eksik olan
            teslimattır ve onun yolu yeniden göndermedir.
        */
        self::assertLessThan(
            300,
            $response->getStatusCode(),
            'VERIFY-MAIL-REGISTRATION-SURVIVES-02: taşıyıcı arızası kaydı düşürmemeli.'
        );

        self::assertSame(
            1,
            DB::table('users')->where('email', 'huseyin-verify-hon-02@example.test')->count(),
            'VERIFY-MAIL-REGISTRATION-SURVIVES-02: hesap açılmış olmalı.'
        );
    }

    // --- VERIFY-MAIL-RESEND-HONEST-03 -------------------------------------

    public function test_the_resend_endpoint_never_reports_success_for_an_email_that_did_not_go_out(): void
    {
        $user = User::factory()->unverified()->create();

        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('mailgun ulaşılamıyor'));

        $response = $this->actingAs($user)->withHeaders($this->jsonHeaders())->post(self::RESEND_URI);

        /*
            EKRAN SUNUCUNUN CEVABINI OKUR.

            `VerificationPending` bileşeni `response.ok` değerine bakıp
            "Verification email sent." yazıyor. Uç her hâlükârda 202
            dönseydi o cümle, hiç çıkmamış bir e-posta için de yazılırdı —
            ve kullanıcı gelmeyecek bir postayı beklerdi.
        */
        self::assertGreaterThanOrEqual(
            500,
            $response->getStatusCode(),
            'VERIFY-MAIL-RESEND-HONEST-03: çıkmayan e-posta için başarı dönülemez.'
        );
    }

    // --- VERIFY-MAIL-RESEND-OK-04 -----------------------------------------

    public function test_a_successful_resend_still_answers_with_success_and_exactly_one_email(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->withHeaders($this->jsonHeaders())->post(self::RESEND_URI);

        $response->assertStatus(202);
        Mail::assertSent(VerifyEmailMail::class, 1);
    }

    // --- VERIFY-MAIL-NO-LEAK-05 -------------------------------------------

    public function test_the_provider_answer_never_reaches_the_screen(): void
    {
        $user = User::factory()->unverified()->create();

        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException(
            'Expected response code 250 but got 535 from smtp.gizli-sunucu.example: key=sk-COK-GIZLI'
        ));

        $response = $this->actingAs($user)->withHeaders($this->jsonHeaders())->post(self::RESEND_URI);

        $body = (string) $response->getContent();

        foreach (['sk-COK-GIZLI', 'gizli-sunucu.example', '535'] as $forbidden) {
            self::assertStringNotContainsStringIgnoringCase(
                $forbidden,
                $body,
                "VERIFY-MAIL-NO-LEAK-05: yanıt \"{$forbidden}\" sızdırıyor."
            );
        }
    }
}
