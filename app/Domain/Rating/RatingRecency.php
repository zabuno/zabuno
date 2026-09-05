<?php

declare(strict_types=1);

namespace App\Domain\Rating;

use InvalidArgumentException;

/**
 * ZAMAN SÖNÜMÜ — `docs/116` §2.
 *
 * Üç yıllık oy bugünkü tabağı anlatmaz. Sönüm yoksa, bir kez iyi puan almış
 * bir ürün sonsuza kadar iyi kalır — restoran şefini değiştirse bile. Bu,
 * misafire bugün olmayan bir şeyi bugün varmış gibi göstermektir.
 *
 * NEDEN YARI ÖMÜR, NEDEN "SON 90 GÜN" DEĞİL?
 *
 * Pencere yaklaşımı ("yalnız son 90 günü say") bir uçurum yaratır: 90.
 * günün gecesi bir oy tam ağırlıkla sayılırken 91. günün sabahı hiç
 * sayılmaz. O gece hiçbir şey olmadığı hâlde ürünün puanı sıçrar ve sahip
 * bunun sebebini hiçbir yerde bulamaz. Üstel sönümde böyle bir kenar yoktur:
 * her oy her gün biraz hafifler.
 *
 * FORMÜL: `w(t) = 2^(-t / yarıÖmür)`. Yarı ömürde tam olarak 0,5 verir —
 * "yarı ömür" sözcüğünün anlamı budur ve testi bu eşitliği dondurur.
 */
final class RatingRecency
{
    public function __construct(
        /**
         * Bir oyun ağırlığının yarıya inmesi için geçmesi gereken gün.
         *
         * Sıfır ya da negatif bir yarı ömür sönüm değildir: sıfırda her oy
         * anında yok olur, negatifte eski oy YENİDEN AĞIRLAŞIR — yani
         * geçmiş bugünden önemli hâle gelir.
         */
        public readonly float $halfLifeDays,
    ) {
        if ($halfLifeDays <= 0.0) {
            throw new InvalidArgumentException('Rating recency half-life must be greater than zero days.');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): self
    {
        return new self((float) ($definition['half_life_days'] ?? 0.0));
    }

    /**
     * `$days` gün önce verilmiş bir oyun bugünkü ağırlığı (0 < w <= 1).
     *
     * GELECEKTEN GELEN OY BUGÜNKÜ OYDUR. Sunucu saatleri kayabilir ve bir
     * sinyalin `observed_at`'i birkaç saniye ileride olabilir; negatif gün
     * farkı 1'den büyük bir ağırlık üretirdi, yani gelecekteki bir oy
     * bugünkünden ağır olurdu. Burada tavan 1,0'dır.
     */
    public function weightAfterDays(float $days): float
    {
        if ($days <= 0.0) {
            return 1.0;
        }

        return 2 ** (-$days / $this->halfLifeDays);
    }
}
