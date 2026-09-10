<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Application\Mail\Port\MailTransportSelectorPort;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Çerçevenin şifre sıfırlama bildirimi — yalnız GÖNDERİCİSİ seçilmiş hâli.
 *
 * SAHİBİN GÖRDÜĞÜ ARIZA. Superadmin panelden Mailgun anahtarı girilmişti;
 * iletişim formu çalışıyor, kayıt doğrulama e-postası geliyordu. "Şifremi
 * unuttum" ise hiçbir şey getirmiyordu — hata da vermiyordu. Sebep, ekranda
 * hiç görünmeyen bir çatallanmaydı: kasadan gönderici seçen yollar bunu
 * açıkça yapıyor (`User::deliverEmailVerificationLink`), çerçevenin
 * `ResetPassword` bildirimi ise hiçbir gönderici SEÇMİYOR ve posta kanalı
 * onu `mail.default`'a veriyordu. Üretimde o değer `log`tur: e-posta
 * "gönderilmiş" sayılıp bir dosyaya yazılıyor, kullanıcıya asla ulaşmıyordu.
 *
 * BU SINIF NE YAPMAZ. Konu metnini, gövdeyi, EN/TR çevirilerini, token'ı,
 * bağlantı adresini, geçerlilik süresini ve throttle davranışını
 * DEĞİŞTİRMEZ — hepsi `parent::toMail()` içinden, çerçevenin kendi
 * kurallarıyla gelir. `ResetPassword::toMailUsing()` ile kurulmuş bir
 * özelleştirme varsa o da korunur; bu yüzden dönen değer bir `MailMessage`
 * ya da bir `Mailable` olabilir ve ikisi de aynı `mailer()` sözleşmesini
 * taşır.
 *
 * Tek eklediği satır, mesajın hangi gönderici üzerinden çıkacağıdır.
 */
final class VaultResetPassword extends ResetPassword
{
    /**
     * @param  mixed  $notifiable
     * @return MailMessage|Mailable
     */
    public function toMail($notifiable)
    {
        $message = parent::toMail($notifiable);

        /*
            Gönderici KURULUM ANINDA değil, mesaj hazırlanırken seçilir:
            kasadaki anahtar bildirimin kuyruğa girmesiyle gönderilmesi
            arasında değişmiş olabilir ve doğru olan, gönderim anındaki
            kimliktir.
        */
        if ($message instanceof MailMessage || $message instanceof Mailable) {
            $message->mailer(app(MailTransportSelectorPort::class)->select());
        }

        return $message;
    }
}
