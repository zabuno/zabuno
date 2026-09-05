<?php

declare(strict_types=1);

namespace App\Models;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Mail\VerifyEmailMail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * Dispatches a single Mailable (S1WP02A-MAIL-01) carrying a 60-minute
     * temporary signed verification.verify URL, instead of the framework's
     * default notification-based flow. Called once by the Registered event
     * listener on initial registration and once per Fortify resend request
     * (`/email/verification-notification`) — never both for the same call.
     */
    public function sendEmailVerificationNotification(): void
    {
        /*
            ÇERÇEVENİN ÇAĞIRDIĞI YOL, SONUCU YUTAR (`docs/110` P0-06).

            Bu metodu kayıt akışındaki `Registered` dinleyicisi çağırır.
            Öncesinde taşıyıcı patladığında istisna oradan yukarı çıkıyor ve
            KAYIT İSTEĞİNİ 500 yapıyordu: hesap açılmış oluyor ama kişi
            ekranda sunucu hatası görüyordu; tekrar denediğinde "bu e-posta
            zaten kayıtlı" duvarına çarpıyor ve elinde ne çalışan bir hesap
            ne de doğrulama bağlantısı kalıyordu.

            Sonucu yutmak, onu GİZLEMEK değildir: sebep günlüğe yazılır ve
            kullanıcıya yeniden gönderme yolu açıktır. O yol sonucu yutmaz —
            bkz. `SendEmailVerificationNotificationController`.
        */
        $this->deliverEmailVerificationLink();
    }

    /**
     * Doğrulama bağlantısını gönderir ve GERÇEKTEN çıkıp çıkmadığını döner.
     *
     * Ayrı bir metot olmasının sebebi tek satır tasarrufu değil: çerçevenin
     * sözleşmesi `void` döner ve o sözleşmeyle "gönderildi mi?" sorusu
     * sorulamaz. Yeniden gönderme ekranı tam olarak o soruyu sorar; cevabı
     * olmadan ekranda "doğrulama e-postası gönderildi" yazardı — hiç
     * çıkmamış bir e-posta için bile.
     */
    public function deliverEmailVerificationLink(): bool
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );

        try {
            /*
                SÜRÜCÜ KASADAN SEÇİLİR.

                Burası çıplak `Mail::to(...)` idi, yani her zaman
                `mail.default`. Superadmin panelden Mailgun anahtarını
                girdiğinde iletişim formu çalışmaya başlıyor, kayıt
                doğrulaması hâlâ üretimdeki `log` sürücüsüne düşüyordu — ve
                iki yüzey de ekranda aynı görünüyordu.
            */
            Mail::mailer(app(MailTransportSelectorPort::class)->select())
                ->to($this->email)
                ->send(new VerifyEmailMail($this->name, $verificationUrl));

            return true;
        } catch (Throwable $exception) {
            /*
                AYRINTI GÜNLÜĞE, DURUM EKRANA.

                Sağlayıcı cevapları uç adresini, alan adını, hatta yanıt
                gövdesini taşıyabilir. E-posta adresi de günlüğe yazılmaz;
                kullanıcı id'si o satırı tek başına bulunabilir kılar.
            */
            Log::warning('Doğrulama e-postası gönderilemedi.', [
                'user_id' => $this->getKey(),
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
