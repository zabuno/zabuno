<?php

declare(strict_types=1);

namespace App\Application\Publication\UseCase;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

/**
 * "Planla" düğmesinin arkasındaki saat seçenekleri.
 *
 * Seçenekler SUNUCUDA üretilir. Tarayıcıda hesaplansaydı, Berlin'den panele
 * giren bir ortak "bu gece 03:00" dediğinde saat sunucunun değil o
 * bilgisayarın saati olurdu. Tarayıcı yalnız sunucunun ürettiği ANI geri
 * gönderir ve o anı okunabilir saate çevirir — hesap yapmaz.
 *
 * SAAT DİLİMİ ŞUBENİNDİR, sabit değildir (`docs/62`). Bir zamanlar burada
 * sabit bir `Europe/Istanbul` duruyordu; aynı markanın Berlin şubesi "bu
 * gece 03:00" dediğinde menü Berlin'de 01:00'de, servis hâlâ sürerken
 * değişirdi. Hatanın en kötü yanı görünmezliğiydi: tek şubeli bir işletmede
 * sabit dilim doğru görünmeye devam eder.
 *
 * SAKLAMA UTC KALIR. Bu sınıf mutlak ANLAR üretir; şubenin duvar saati
 * yalnız o anların HESAPLANDIĞI yerdir. Yerel saat saklansaydı, yaz saati
 * biten gecede aynı duvar saati iki kez yaşanır ve yayının hangi geçişte
 * çıkacağını kimse söyleyemezdi.
 */
final class BuildScheduleOptions
{
    /**
     * Planın en fazla ne kadar ileriye kurulabileceği.
     *
     * Sınır KEYFİ DEĞİL: donmuş bir snapshot ne kadar uzun beklerse, o
     * kadar çok "sahibin unuttuğu içerik" taşır. Bir ayın ötesine kurulan
     * yayın, kurulduğunda hatırlanan bir karar olmaktan çıkar.
     */
    public const MAX_HORIZON_DAYS = 30;

    /**
     * Şubenin duvar saatine göre seçenekler.
     *
     * Saat dilimi TANINMIYORSA hiç seçenek üretilmez. Yedek bir dilime
     * düşmek, sahibin okuduğu saatle menünün gerçekten değişeceği anı
     * ayırırdı — ve bu tam olarak düzeltilen hatadır. Boş liste ise
     * dürüsttür: hemen yayınlamak her zaman açıktır.
     *
     * @return list<array{key:string,scheduledFor:string}>
     */
    public static function forNow(CarbonInterface $now, ?string $timeZone): array
    {
        if ($timeZone === null) {
            return [];
        }

        try {
            $local = Carbon::instance($now->toDateTime())->setTimezone($timeZone);
        } catch (Throwable) {
            // Şube satırında tanınmayan bir kimlik varsa (elle düzeltilmiş
            // bir satır, emekliye ayrılmış bir dilim) saat uydurulmaz.
            return [];
        }

        return [
            // Servis kapandıktan sonra: masada kimse yokken menü değişir.
            ['key' => 'tonight', 'scheduledFor' => self::nextAt($local, 3, 0)],
            // Kapılar açılmadan önce: sabah ilk misafir yeni menüyü görür.
            ['key' => 'tomorrowMorning', 'scheduledFor' => self::nextAt($local, 9, 0)],
            // Haftalık menü değişimi için.
            ['key' => 'nextMonday', 'scheduledFor' => self::nextMondayAt($local, 9, 0)],
        ];
    }

    public static function isWithinHorizon(CarbonInterface $now, CarbonInterface $scheduledFor): bool
    {
        return $scheduledFor->greaterThan($now)
            && $scheduledFor->lessThanOrEqualTo($now->clone()->addDays(self::MAX_HORIZON_DAYS));
    }

    private static function nextAt(Carbon $local, int $hour, int $minute): string
    {
        $candidate = $local->clone()->setTime($hour, $minute);

        if (! $candidate->greaterThan($local)) {
            $candidate = $candidate->addDay();
        }

        return $candidate->utc()->toISOString();
    }

    private static function nextMondayAt(Carbon $local, int $hour, int $minute): string
    {
        $candidate = $local->clone()->setTime($hour, $minute);

        // "Gelecek Pazartesi" bugün Pazartesi ve saat henüz gelmediyse
        // BUGÜNDÜR: sahibi bir hafta bekletmek, düğmenin sözünü tutmamaktır.
        if (! ($candidate->isMonday() && $candidate->greaterThan($local))) {
            $candidate = $candidate->next(CarbonInterface::MONDAY)->setTime($hour, $minute);
        }

        return $candidate->utc()->toISOString();
    }
}
