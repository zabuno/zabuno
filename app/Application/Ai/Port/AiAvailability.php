<?php

declare(strict_types=1);

namespace App\Application\Ai\Port;

/**
 * "AI çalışmıyor" tek bir durum değildir.
 *
 * `docs/47` Kural 5'in AI karşılığı: engellenmiş bir durum nedenini VE
 * çözümünü söyler. Bütçe dolduğunda "tekrar deneyin" demek, analitikteki
 * 402 hatasının aynısı olurdu.
 */
enum AiAvailability: string
{
    case Available = 'available';

    /** Global ya da tenant kapatma anahtarı. Çözüm: yönetici açar. */
    case KillSwitch = 'kill_switch';

    /** Tenant bütçesi doldu. ÜRÜN DURMAZ, yalnız AI durur. */
    case BudgetExhausted = 'budget_exhausted';

    /** Bu yetenek için yapılandırılmış aday model yok. */
    case NoRoute = 'no_route';

    public function isAvailable(): bool
    {
        return $this === self::Available;
    }
}
