<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * "Bugün tükendi" — `docs/82` (P1-04).
 *
 * İşaret bir BAYRAK değil, bir ZAMAN DAMGASI. Sebep: "bugün tükendi"
 * cümlesindeki BUGÜN, şubenin kendi saat diliminde bir gündür ve ertesi
 * sabah kendiliğinden geçmelidir.
 *
 * Bayrak olsaydı, sahip her sabah altı ürünü tek tek geri açardı — ya da
 * bunu yapan bir zamanlanmış görev yazardık ve o görev çalışmadığı gün menü
 * sessizce yanlış kalırdı. Damga, HİÇBİR arka plan işi olmadan doğru cevabı
 * verir: sorulduğu anda hesaplanır.
 *
 * Domain katmanı çerçeve BİLMEZ (ADR): burada `Illuminate\Support\Carbon`
 * yok, yalnız PHP'nin kendi tarih tipleri var.
 */
final class StockState
{
    public static function isOutOfStockNow(
        ?string $outOfStockSince,
        string $timezone,
        ?DateTimeImmutable $now = null,
    ): bool {
        if ($outOfStockSince === null || trim($outOfStockSince) === '') {
            return false;
        }

        try {
            $zone = new DateTimeZone($timezone === '' ? 'UTC' : $timezone);
            $marked = new DateTimeImmutable($outOfStockSince);
        } catch (Throwable) {
            // Okunamayan bir damga "tükenmiş" saymaz: belirsizlik, ürünü
            // menüden düşürmenin gerekçesi olamaz.
            return false;
        }

        $now ??= new DateTimeImmutable('now');

        // Yalnız AYNI iş günü. Dün tükenen balık bugün yeniden vardır.
        return $marked->setTimezone($zone)->format('Y-m-d')
            === $now->setTimezone($zone)->format('Y-m-d');
    }
}
