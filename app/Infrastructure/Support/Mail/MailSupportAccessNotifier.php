<?php

declare(strict_types=1);

namespace App\Infrastructure\Support\Mail;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Support\Dto\SupportAccessSessionRow;
use App\Application\Support\Port\SupportAccessNotifierPort;
use App\Domain\Tenancy\MembershipRole;
use App\Mail\SupportAccessOpened;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sahibe haber verir — `docs/133` §3.
 *
 * ALICI SAHİPTİR, "ekip" DEĞİL. Bir restoranın mutfak hesabına düşen
 * "hesabınıza bakıldı" e-postası ne bir karar aldırır ne bir soru
 * cevaplar; hesabın kendisinden sorumlu olan kişidir bunu bilmesi gereken.
 * Birden çok sahip varsa hepsine gider.
 *
 * `log` SÜRÜCÜSÜ HİÇ DENEMEZ (`MailSupportNotifier` ile aynı kural,
 * `docs/93`): günlüğe yazılmış bir e-posta kimseye ulaşmamıştır ve ona
 * "gönderildi" demek yalan olurdu. Sahibin bu durumda göreceği yer
 * panelidir — kayıt orada zaten duruyor.
 */
final readonly class MailSupportAccessNotifier implements SupportAccessNotifierPort
{
    private const NO_OUTBOUND_TRANSPORT = 'log';

    public function __construct(
        private MailTransportSelectorPort $mailTransport,
    ) {}

    public function notifyWorkspaceOwners(SupportAccessSessionRow $session, string $workspaceName): string|false|null
    {
        $recipients = DB::table('workspace_memberships as wm')
            ->join('users as u', 'u.id', '=', 'wm.user_id')
            ->where('wm.workspace_id', $session->workspaceId)
            ->where('wm.role', MembershipRole::Owner->value)
            ->pluck('u.email')
            ->all();

        // Sahibi olmayan bir çalışma alanı vardır (devir yarım kalmıştır) ve
        // bu bir hata değildir; gönderilecek adres yoksa gönderim de yok.
        if ($recipients === []) {
            return false;
        }

        $mailer = $this->mailTransport->select();

        if ($mailer === self::NO_OUTBOUND_TRANSPORT) {
            return false;
        }

        try {
            Mail::mailer($mailer)
                ->to($recipients)
                ->send(new SupportAccessOpened(
                    $workspaceName,
                    $session->reason,
                    $session->startedAt,
                    $session->expiresAt,
                    $session->actorEmail,
                ));

            return null;
        } catch (Throwable $exception) {
            Log::warning('Destek erişimi bildirimi kiracıya gönderilemedi.', [
                'support_access_session_id' => $session->id,
                'reason' => $exception->getMessage(),
            ]);

            $reason = trim($exception->getMessage());

            return mb_substr($reason !== '' ? $reason : $exception::class, 0, 190);
        }
    }
}
