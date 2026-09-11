<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Mail;

use App\Application\Billing\Port\SubscriptionGraceReminderNotifierPort;
use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Mail\SubscriptionGraceReminder;
use App\Support\Localization\SiteText;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Hatırlatma, kasadan seçilen taşıyıcıyla gider — `docs/93` deseni,
 * `MailDataRightsNotifier` ile aynı sözleşme.
 *
 * `log` SÜRÜCÜSÜ GÖNDERİM DEĞİLDİR: günlüğe yazılan bir e-posta kimseye
 * ulaşmaz. Ona damga basmak, sahibin hiç gelmeyecek bir postayı beklemesi
 * ve hatırlatmanın bir daha HİÇ denenmemesi demekti. Damga atılmaz,
 * hatırlatma borç kalır.
 *
 * SEÇİM DE `try` İÇİNDEDİR. `MailTransportSelectorPort` yapılandırma
 * arızasında istisna atar; dışarıda kalsaydı tek bir bozuk kurulum bütün
 * taramayı düşürür ve hiçbir sahip postasını alamazdı.
 *
 * ═══ DİL: KİMSENİN SEÇMEDİĞİ BİR DİLE BAĞLANMAZ ═══
 *
 * `users` tablosunda dil sütunu YOKTUR ve bu paket öyle bir şema açmaz.
 * Metin `SiteText::pick(null)` ile kaynak dile düşer — yani uygulamanın o
 * anki diline göre DEĞİŞMEZ. Zamanlayıcıdan koşan bir işin "o anki dili"
 * zaten kimsenin seçtiği bir dil değildir; ona bağlanan bir gövde, aynı
 * sahibe iki farklı koşuda iki farklı dilde yazardı.
 *
 * ═══ GÜN SAYISI DEĞİL TARİH ═══
 *
 * "7 gün kaldı" cümlesi, e-postanın okunduğu güne göre yanlışlaşır. Bu depo
 * aynı dersi veri hakları bildiriminde bir kez öğrendi: tarih kayıttan
 * gelir, sayı hesaplanmaz.
 */
final readonly class MailSubscriptionGraceReminderNotifier implements SubscriptionGraceReminderNotifierPort
{
    private const NO_OUTBOUND_TRANSPORT = 'log';

    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private MailTransportSelectorPort $mailTransport,
        private SiteText $siteText,
    ) {}

    public function notify(
        string $recipientEmail,
        DateTimeInterface $periodEndsAt,
        DateTimeInterface $graceEndsAt,
    ): string|false|null {
        try {
            $mailer = $this->mailTransport->select();

            if ($mailer === self::NO_OUTBOUND_TRANSPORT) {
                return false;
            }

            $locale = SiteText::pick(null);

            $text = [
                'subject' => $this->siteText->get('site.billing.graceReminder.subject', $locale),
                'greeting' => $this->siteText->get('site.billing.graceReminder.greeting', $locale),
                'body' => $this->siteText->get('site.billing.graceReminder.body', $locale),
                'periodEnded' => str_replace(
                    '{date}',
                    $periodEndsAt->format(self::DATE_FORMAT),
                    $this->siteText->get('site.billing.graceReminder.periodEnded', $locale),
                ),
                'graceEnds' => str_replace(
                    '{date}',
                    $graceEndsAt->format(self::DATE_FORMAT),
                    $this->siteText->get('site.billing.graceReminder.graceEnds', $locale),
                ),
                'safety' => $this->siteText->get('site.billing.graceReminder.safety', $locale),
                'action' => $this->siteText->get('site.billing.graceReminder.action', $locale),
            ];

            /*
                ADRES GÖVDEDE DEĞİL, YAPILANDIRMADAN. Sahip ödemeyi nereden
                yapacağını okumadan bu postayı kapatmamalı; ama adres
                katalogdan gelseydi, alan adı bir gün değiştiğinde çeviri
                dosyalarında kalırdı.
            */
            $actionUrl = rtrim(url('/app'), '/').'#billing';

            /*
                HER ALICI KENDİ POSTASINI ALIR. Tek postaya iki sahip
                yazmak, bir adresin düşmesiyle diğerini de düşürürdü —
                çağıran zaten alıcı alıcı ilerler ve damgayı alıcı başına
                basar.
            */
            Mail::mailer($mailer)
                ->to($recipientEmail)
                ->send(new SubscriptionGraceReminder($text, $actionUrl));

            return null;
        } catch (Throwable $exception) {
            /*
                SEBEP GÜNLÜĞE, KIRPILMIŞ HÂLİ ÇAĞIRANA. Sağlayıcı cevabı uç
                adresini ya da kimlik bilgisi artığını taşıyabilir; ekrana
                hiç çıkmaz.
            */
            Log::warning('Ödemesiz süre hatırlatması gönderilemedi.', [
                'period_ends_at' => $periodEndsAt->format(self::DATE_FORMAT),
                'reason' => $exception->getMessage(),
            ]);

            $reason = trim($exception->getMessage());

            return mb_substr($reason !== '' ? $reason : $exception::class, 0, 190);
        }
    }
}
