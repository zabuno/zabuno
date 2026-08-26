<?php

declare(strict_types=1);

namespace App\Domain\Money;

use InvalidArgumentException;

/**
 * Deterministik oranlama — CORE-12.
 *
 * Restoran sahibi ayın 10'unda plan yükseltirse, kalan 21 günün ücreti
 * hesaplanır. Bu hesap iki nedenle float ile yapılmaz:
 *
 * 1. Float aritmetiği kayar: 0.1 + 0.2 !== 0.3. Kuruş bazında tam sayı kayar.
 * 2. Bölme her zaman tam bölünmez. 100 kuruşu 3'e bölmek 33.33… verir.
 *    Üç parçayı da 33'e yuvarlarsak 1 kuruş yok olur; 34'e yuvarlarsak
 *    yoktan 2 kuruş yaratırız. İkisi de defterin dengesini bozar.
 *
 * Bu yüzden artık kuruşlar "en büyük kalan" kuralıyla baştan sona dağıtılır:
 * parçaların toplamı HER ZAMAN girdiye eşittir ve aynı girdi her çalıştırmada
 * aynı çıktıyı verir.
 */
final class Proration
{
    /**
     * Bir tutarı, kullanılan/toplam gün oranında böler.
     * Geriye [kullanılan pay, kalan pay] döner; ikisinin toplamı tam tutardır.
     *
     * @return array{0: Money, 1: Money}
     */
    public static function byPeriod(Money $total, int $elapsedUnits, int $totalUnits): array
    {
        if ($totalUnits <= 0) {
            throw new InvalidArgumentException('Toplam dönem uzunluğu pozitif olmalıdır.');
        }

        if ($elapsedUnits < 0 || $elapsedUnits > $totalUnits) {
            throw new InvalidArgumentException('Geçen dönem, toplam dönemin dışında olamaz.');
        }

        $minor = $total->minorAmount();
        $elapsedShare = intdiv($minor * $elapsedUnits, $totalUnits);

        // Bölmeden artan kuruş, hangi tarafın kesri büyükse oraya gider —
        // ve her koşulda toplam korunur.
        $remainder = $minor * $elapsedUnits - $elapsedShare * $totalUnits;
        if ($remainder * 2 >= $totalUnits) {
            $elapsedShare++;
        }

        return [
            Money::fromMinorAmount($elapsedShare, $total->currencyCode()),
            Money::fromMinorAmount($minor - $elapsedShare, $total->currencyCode()),
        ];
    }

    /**
     * Bir tutarı eşit parçalara böler; artan kuruşları baştan başlayarak
     * birer birer dağıtır. Parçaların toplamı her zaman girdiye eşittir.
     *
     * @return list<Money>
     */
    public static function split(Money $total, int $parts): array
    {
        if ($parts <= 0) {
            throw new InvalidArgumentException('Parça sayısı pozitif olmalıdır.');
        }

        $minor = $total->minorAmount();
        $base = intdiv($minor, $parts);
        $remainder = $minor - $base * $parts;

        $slices = [];
        for ($index = 0; $index < $parts; $index++) {
            $slices[] = Money::fromMinorAmount(
                $index < $remainder ? $base + 1 : $base,
                $total->currencyCode(),
            );
        }

        return $slices;
    }
}
