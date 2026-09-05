<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\UseCase;

use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Domain\MenuCatalog\ServiceDayTimeline;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * MİSAFİRİN SORUSUNUN CEVABI: "şu an hangi menü?"
 *
 * Karekod ve şubenin kalıcı genel adresi bir MENÜYE bağlıdır ve bağlı
 * kalır — sahibin kuralı: *"Basılı kod hiç değişmez."* Değişen şey, o
 * bağın ARKASINDA ne olduğudur: adres şubeye götürür, saat menüyü seçer
 * (`docs/109` §7.1).
 *
 * Saat ŞUBENİN saatidir. Sunucunun saati ya da sabit bir `Europe/Istanbul`,
 * Berlin'deki bir şubenin kahvaltısını bir saat geç açardı.
 *
 * CEVAPSIZ KALMAZ: şubede hiç geçiş tanımlanmamışsa gün bütünüyle girilen
 * menüye aittir. Bugünkü tek menülü şubelerin davranışı budur ve bu
 * yüzden onlar için hiçbir şey değişmez.
 */
final class ResolveServingMenu
{
    public function __construct(private readonly MenuSchedulePort $schedule) {}

    /**
     * @param  int  $addressedMenuId  Karekodun/adresin işaret ettiği menü.
     * @return int Şu an servis edilmesi gereken menü.
     */
    public function forMenu(int $addressedMenuId): int
    {
        $locationId = $this->schedule->locationIdForMenu($addressedMenuId);

        if ($locationId === null) {
            return $addressedMenuId;
        }

        return $this->forLocation($locationId) ?? $addressedMenuId;
    }

    /** Şubede şu an servis edilen menü; hiç menü yoksa `null`. */
    public function forLocation(int $locationId): ?int
    {
        $switches = $this->schedule->switchesForLocation($locationId);

        if ($switches === []) {
            return $this->schedule->anchorMenuId($locationId);
        }

        try {
            $timeline = ServiceDayTimeline::fromSwitches($switches);
        } catch (InvalidArgumentException) {
            /*
                Veri bozulmuşsa (iki geçiş aynı dakikada) misafire hata
                gösterilmez: gün ÇIPA menüsüne düşer. Karekodun bir menü
                açmaması, yanlış menü açmasından daha kötüdür.
            */
            return $this->schedule->anchorMenuId($locationId);
        }

        $timezone = $this->schedule->timezoneForLocation($locationId);
        $localNow = Carbon::now($timezone);

        return $timeline->menuIdAt($localNow->hour * 60 + $localNow->minute)
            ?? $this->schedule->anchorMenuId($locationId);
    }
}
