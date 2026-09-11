<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use DateTimeInterface;

/**
 * Ödemesiz süre hatırlatmasını gönderir — `docs/93` deseni.
 *
 * İSTİSNA FIRLATMAZ. Bir alıcının düşmesi taramayı kesmemelidir: kalan
 * sahipler postasını almalı ve düşen alıcı ertesi günkü koşuda yeniden
 * denenmelidir (`DataRightsNotifierPort` ile aynı ders).
 *
 * ÜÇ CEVAP, ÜÇ FARKLI SONUÇ:
 *
 * - `null` — posta gerçekten çıktı. Çağıran damgayı basar.
 * - dize — denendi ve düştü. Kırpılmış, arındırılmış sebeptir; çağıran
 *   damga BASMAZ, böylece hatırlatma borç kalır ve yarın yeniden denenir.
 * - `false` — HİÇ DENENMEDİ, çünkü dışarı giden bir taşıyıcı yok (`log`).
 *   Bu bir arıza değildir ve damga basılmaz: taşıyıcı girildiği gün posta
 *   çıkar.
 */
interface SubscriptionGraceReminderNotifierPort
{
    public function notify(
        string $recipientEmail,
        DateTimeInterface $periodEndsAt,
        DateTimeInterface $graceEndsAt,
    ): string|false|null;
}
