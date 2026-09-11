<?php

declare(strict_types=1);

namespace App\Infrastructure\Support\Mail;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Support\Dto\ReceivedSupportRequest;
use App\Application\Support\Dto\SupportReplyTarget;
use App\Application\Support\Port\SupportNotifierPort;
use App\Domain\Support\SupportChannel;
use App\Domain\Support\SupportReplyOutcome;
use App\Mail\ContactMessageReceived;
use App\Mail\SupportRequestAcknowledged;
use App\Mail\SupportRequestReplied;
use App\Support\Contact\ResponseCommitment;
use App\Support\Localization\SiteText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * İki e-postayı kasadan seçilen taşıyıcıyla gönderir — FF-201.
 *
 * SÜRÜCÜ KASADAN SEÇİLİR (`docs/94` Faz 3): superadmin anahtarı girdiği
 * an her iki posta da çıkar, deploy gerekmez.
 *
 * SEBEP LOGA, DURUM SATIRA. Sağlayıcı cevabı uç adresini ve yanıt
 * gövdesini taşıyabilir; günlüğe yazılır, çağırana kırpılmış bir sebep
 * döner, ekrana hiç çıkmaz (`docs/110` P0-06 dersi).
 */
final readonly class MailSupportNotifier implements SupportNotifierPort
{
    public function __construct(
        private MailTransportSelectorPort $mailTransport,
        private SiteText $siteText,
        private ResponseCommitment $commitment,
    ) {}

    /**
     * `log` sürücüsü "dışarı giden taşıyıcı yok" demektir — bu deponun
     * dağıtım sözleşmesi öyle söylüyor (`docker-compose.yml`: "sürücü
     * ayarlanana kadar varsayılan `log`'tur: göndermez"). Günlüğe yazılan
     * bir e-posta kimseye ulaşmaz; ona damga basılmaz.
     */
    private const NO_OUTBOUND_TRANSPORT = 'log';

    public function acknowledge(ReceivedSupportRequest $request): string|false|null
    {
        /*
            SEÇİM DE BİR GÖNDERİM ADIMIDIR, BU YÜZDEN AYNI AĞIN İÇİNDEDİR.

            Seçici yapılandırma arızasında istisna atar (bkz.
            `MailTransportSelectorPort`). O çağrı `try`'ın dışında kalırsa
            istisna buradan yukarı çıkar: talep veritabanına yazılmıştır,
            ama kullanıcı 500 görür ve satır bildirim sonucunu hiç almaz —
            "gönderemedim" diyebilecekken hiçbir şey diyemeyiz.
        */
        try {
            $mailer = $this->mailTransport->select();

            if ($mailer === self::NO_OUTBOUND_TRANSPORT) {
                return false;
            }

            $locale = SiteText::pick($request->locale);
            $replyTo = self::replyToAddress();

            /*
                METİN KATALOGDAN, gönderenin dilinde. Alındı e-postası bir
                arayüz metnidir: sahibi onu PO dosyasından çevirebilmeli.
                Taahhüt cümlesi de aynı kaynaktan gelir — sayfa, e-posta ve
                panel tek anahtarı okur (`docs/125` §3).
            */
            $text = [
                'subject' => $this->siteText->get('site.support.ack.subject', $locale),
                'greeting' => $this->siteText->get('site.support.ack.greeting', $locale),
                'received' => $this->siteText->get('site.support.ack.received', $locale),
                'subjectLine' => $this->siteText->get('site.support.ack.subjectLine', $locale),
                'keep' => $this->siteText->get('site.support.ack.keep', $locale),
                'panel' => $request->channel === SupportChannel::Panel
                    ? $this->siteText->get('site.support.ack.panel', $locale)
                    : null,
                'reply' => $replyTo !== null
                    ? $this->siteText->get('site.support.ack.reply', $locale)
                    : null,
                'commitment' => $this->commitment->sentence($locale),
            ];

            Mail::mailer($mailer)
                ->to($request->email)
                ->send(new SupportRequestAcknowledged(
                    $request->reference,
                    $request->name,
                    $request->subject,
                    $text,
                    $replyTo,
                ));

            return null;
        } catch (Throwable $exception) {
            Log::warning('Destek alındı e-postası gönderilemedi.', [
                'support_request_id' => $request->id,
                'reason' => $exception->getMessage(),
            ]);

            return self::reason($exception);
        }
    }

    public function notifyOwner(ReceivedSupportRequest $request): string|false|null
    {
        $to = config('contact.notify');

        // Adres yoksa gönderim de yok — ve bu bir hata değildir (`docs/93`).
        if (! is_string($to) || trim($to) === '') {
            return false;
        }

        // Seçim de `try` içindedir; gerekçe `acknowledge()` üstündedir.
        try {
            $mailer = $this->mailTransport->select();

            if ($mailer === self::NO_OUTBOUND_TRANSPORT) {
                return false;
            }

            Mail::mailer($mailer)
                ->to(trim($to))
                ->send(new ContactMessageReceived(
                    $request->reference,
                    $request->name,
                    $request->email,
                    $request->subject,
                    $request->message,
                    $request->channel->value,
                ));

            return null;
        } catch (Throwable $exception) {
            Log::warning('Destek talebi bildirimi sahibe gönderilemedi.', [
                'support_request_id' => $request->id,
                'reason' => $exception->getMessage(),
            ]);

            return self::reason($exception);
        }
    }

    public function reply(SupportReplyTarget $target, string $body): SupportReplyOutcome
    {
        // Seçim de gönderim ağının İÇİNDEDİR; gerekçe `acknowledge()` üstünde.
        try {
            $mailer = $this->mailTransport->select();

            if ($mailer === self::NO_OUTBOUND_TRANSPORT) {
                return SupportReplyOutcome::NoOutboundTransport;
            }

            $locale = SiteText::pick($target->locale);
            $replyTo = self::replyToAddress();

            /*
                ÇERÇEVE KATALOGDAN, TALEBİN DİLİNDE. Müşteri talebini hangi
                dilde yazdıysa cevabın çerçevesini de o dilde okur; ARADAKİ
                gövde süperadminin cümlesidir ve çevrilmez.
            */
            $text = [
                'subject' => $this->siteText->get('site.support.reply.subject', $locale),
                'greeting' => $this->siteText->get('site.support.reply.greeting', $locale),
                'intro' => $this->siteText->get('site.support.reply.intro', $locale),
                'closing' => $this->siteText->get('site.support.reply.closing', $locale),
                'reply' => $replyTo !== null
                    ? $this->siteText->get('site.support.reply.reply', $locale)
                    : null,
            ];

            Mail::mailer($mailer)
                ->to($target->email)
                ->send(new SupportRequestReplied(
                    $target->reference,
                    $target->name,
                    $target->subject,
                    $body,
                    $text,
                    $replyTo,
                ));

            return SupportReplyOutcome::Sent;
        } catch (Throwable $exception) {
            /*
                HAM SEBEP YALNIZ BURADA. Sağlayıcı cümlesi bir anahtar
                taşıyabilir; günlüğe yazılır, çağırana sabit bir kod döner.
                Satır değişmez, aynı gövde yeniden gönderilebilir.
            */
            Log::warning('Destek cevabı gönderilemedi.', [
                'support_request_id' => $target->id,
                'reason' => $exception->getMessage(),
            ]);

            return SupportReplyOutcome::SendFailed;
        }
    }

    /**
     * Sebep KIRPILIR ve boş sebep de bir sebeptir: sütun sınırlı, yığın izi
     * orada okunmaz; boş mesajlı istisnanın SINIFI hiçbir zaman boş değildir.
     */
    private static function reason(Throwable $exception): string
    {
        $reason = trim($exception->getMessage());

        return mb_substr($reason !== '' ? $reason : $exception::class, 0, 190);
    }

    /** `SUPPORT_EMAIL` doluysa cevap adresi, boşsa `null` (`docs/125` §7.2). */
    public static function replyToAddress(): ?string
    {
        $address = config('support.channel_email');

        return is_string($address) && trim($address) !== '' ? trim($address) : null;
    }
}
