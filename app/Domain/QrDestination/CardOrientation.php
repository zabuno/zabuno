<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

enum CardOrientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';

    /**
     * Ölçüyü yöne göre çevirir.
     *
     * Yön ayrı bir seçim olduğu için ölçü listesi ikiye katlanmaz: "A4 dikey"
     * ve "A4 yatay" iki ayrı ölçü değil, aynı ölçünün iki yönüdür.
     *
     * @return array{0: float, 1: float}
     */
    public function apply(CardSize $size): array
    {
        [$width, $height] = $size->dimensionsMm();

        return $this === self::Landscape ? [$height, $width] : [$width, $height];
    }
}
