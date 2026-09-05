<?php

declare(strict_types=1);

namespace App\Infrastructure\Team\Mail;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Team\Dto\IssuedTeamInvitation;
use App\Application\Team\Port\TeamInvitationNotifierPort;
use App\Mail\TeamInvitationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Daveti kasadan seçilen taşıyıcıyla gönderir (`docs/93`, `docs/110` P0-06).
 *
 * SÜRÜCÜ KASADAN SEÇİLİR. Öncesinde davet çıplak `Mail::to(...)` ile
 * gidiyordu, yani her zaman `mail.default` üzerinden. Superadmin panelden
 * Mailgun anahtarını girdiğinde iletişim formu çalışmaya başlıyor, davet
 * hâlâ üretimdeki `log` sürücüsüne düşüyordu — ve ekranda ikisi de aynı
 * görünüyordu.
 *
 * SEBEP LOGA, DURUM EKRANA. Sağlayıcı cevapları uç adresini, alan adını,
 * hatta yanıt gövdesinin tamamını taşıyabilir; bunlar günlüğe yazılır.
 * Çağıran yalnız kırpılmış bir sebep alır ve onu da veritabanına koyar,
 * kullanıcıya değil.
 */
final readonly class MailTeamInvitationNotifier implements TeamInvitationNotifierPort
{
    public function __construct(
        private MailTransportSelectorPort $mailTransport,
    ) {}

    public function notify(IssuedTeamInvitation $invitation): ?string
    {
        try {
            Mail::mailer($this->mailTransport->select())
                ->to($invitation->email)
                ->send(new TeamInvitationMail(
                    $invitation->workspaceName,
                    $invitation->role,
                    url("/invitations/{$invitation->rawToken}"),
                    $invitation->expiresAt,
                ));

            return null;
        } catch (Throwable $exception) {
            /*
                DAVET EDİLENİN ADRESİ GÜNLÜĞE YAZILMAZ.

                Günlükler operasyon ekibinin görebildiği bir yüzeydir ve bir
                arıza kaydının bedeli, üçüncü bir kişinin e-posta adresini
                oraya kopyalamak olmamalı. Davet id'si o satırı zaten tek
                başına bulunabilir kılar.
            */
            Log::warning('Ekip daveti e-postası gönderilemedi.', [
                'invitation_id' => $invitation->id,
                'reason' => $exception->getMessage(),
            ]);

            /*
                Sebep KIRPILIR: sütun sınırlı ve bir yığın izi orada okunmaz;
                ilk cümle "neden gitmedi" sorusunu cevaplar. Aynı sınır
                `contact_messages` tarafında da 190'dır (`docs/93`).
            */
            $reason = trim($exception->getMessage());

            /*
                BOŞ SEBEP DE BİR SEBEPTİR.

                Bazı taşıyıcı istisnaları boş mesajla gelir. Boş dizeyi
                olduğu gibi kaydetmek, satırı "hiç denenmedi" hâline
                düşürürdü — yani bilinen bir arıza, bilinmezliğe dönerdi.
                İstisnanın SINIFI hiçbir zaman boş değildir.
            */
            return mb_substr($reason !== '' ? $reason : $exception::class, 0, 190);
        }
    }
}
