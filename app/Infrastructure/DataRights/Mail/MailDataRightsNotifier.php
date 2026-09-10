<?php

declare(strict_types=1);

namespace App\Infrastructure\DataRights\Mail;

use App\Application\DataRights\Dto\DataRequestRow;
use App\Application\DataRights\Port\DataRightsNotifierPort;
use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Domain\DataRights\DataRequestKind;
use App\Mail\WorkspaceDataRightsNotice;
use App\Support\Localization\SiteText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Haber, kasadan seçilen taşıyıcıyla gider — FF-226, `docs/93` deseni.
 *
 * `log` sürücüsü "dışarı giden taşıyıcı yok" demektir: günlüğe yazılan bir
 * e-posta kimseye ulaşmaz ve ona "gönderildi" damgası basmak, sahibin
 * gelmeyen bir e-postayı beklemesine yol açardı. Damga atılmaz, arşiv yine
 * ekranda durur.
 *
 * SEBEP LOGA, DURUM SATIRA: sağlayıcı cevabı uç adresini taşıyabilir;
 * günlüğe yazılır, çağırana kırpılmış bir sebep döner, ekrana hiç çıkmaz.
 */
final readonly class MailDataRightsNotifier implements DataRightsNotifierPort
{
    private const NO_OUTBOUND_TRANSPORT = 'log';

    public function __construct(
        private MailTransportSelectorPort $mailTransport,
        private SiteText $siteText,
    ) {}

    public function notify(DataRequestRow $request, string $recipientEmail, ?string $downloadUrl): string|false|null
    {
        /*
            SEÇİM DE `try` İÇİNDEDİR. Seçici yapılandırma arızasında istisna
            atar (bkz. `MailTransportSelectorPort`); dışarıda kalsaydı
            silme/dışa aktarma talebi kaydedilmişken çağıran 500 alır ve
            satır bildirim sonucunu hiç yazamazdı — arşiv ekranda durur, ama
            "haber verilemedi" bilgisi kaybolurdu.
        */
        try {
            $mailer = $this->mailTransport->select();

            if ($mailer === self::NO_OUTBOUND_TRANSPORT) {
                return false;
            }

            $locale = SiteText::pick(null);
            $prefix = $request->kind === DataRequestKind::Export ? 'site.dataRights.export' : 'site.dataRights.erasure';

            $detail = $request->kind === DataRequestKind::Export
                ? str_replace('{date}', (string) $request->availableUntil, $this->siteText->get($prefix.'.detail', $locale))
                : str_replace('{date}', (string) $request->scheduledFor, $this->siteText->get($prefix.'.detail', $locale));

            $text = [
                'subject' => $this->siteText->get($prefix.'.subject', $locale),
                'greeting' => $this->siteText->get('site.dataRights.greeting', $locale),
                'body' => $this->siteText->get($prefix.'.body', $locale),
                'detail' => $detail,
                'action' => $downloadUrl === null ? null : $this->siteText->get($prefix.'.action', $locale),
                'note' => $this->siteText->get($prefix.'.note', $locale),
            ];

            Mail::mailer($mailer)->to($recipientEmail)->send(new WorkspaceDataRightsNotice($text, $downloadUrl));

            return null;
        } catch (Throwable $exception) {
            Log::warning('Veri hakkı bildirimi gönderilemedi.', [
                'request_id' => $request->id,
                'reason' => $exception->getMessage(),
            ]);

            $reason = trim($exception->getMessage());

            return mb_substr($reason !== '' ? $reason : $exception::class, 0, 190);
        }
    }
}
