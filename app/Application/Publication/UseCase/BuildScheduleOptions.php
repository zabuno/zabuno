<?php

declare(strict_types=1);

namespace App\Application\Publication\UseCase;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * "Planla" düğmesinin arkasındaki saat seçenekleri.
 *
 * Seçenekler SUNUCUDA üretilir ve `Europe/Istanbul`a göre hesaplanır.
 * Tarayıcıda hesaplansaydı, Berlin'den panele giren bir ortak "bu gece
 * 03:00" dediğinde Türkiye'de saat 04:00 olurdu; restoranın menüsü sahibin
 * beklemediği bir anda değişirdi. Tarayıcı yalnız sunucunun ürettiği ANI
 * geri gönderir ve o anı okunabilir saate çevirir — hesap yapmaz.
 */
final class BuildScheduleOptions
{
    public const TIME_ZONE = 'Europe/Istanbul';

    /**
     * Planın en fazla ne kadar ileriye kurulabileceği.
     *
     * Sınır KEYFİ DEĞİL: donmuş bir snapshot ne kadar uzun beklerse, o
     * kadar çok "sahibin unuttuğu içerik" taşır. Bir ayın ötesine kurulan
     * yayın, kurulduğunda hatırlanan bir karar olmaktan çıkar.
     */
    public const MAX_HORIZON_DAYS = 30;

    /**
     * @return list<array{key:string,scheduledFor:string}>
     */
    public static function forNow(CarbonInterface $now): array
    {
        $local = Carbon::instance($now->toDateTime())->setTimezone(self::TIME_ZONE);

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
