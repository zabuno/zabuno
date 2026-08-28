<?php

declare(strict_types=1);

namespace App\Application\Analytics\Dto;

/**
 * Bir kırılım satırı — `docs/68`.
 *
 * Toplam sayı tek başına "hangi şube" ve "hangi karekod" sorularını
 * cevaplamaz. İki şubesi olan bir işletmede toplam tarama sayısı, birinin
 * hiç taranmadığını gizler.
 */
final class AnalyticsBreakdownRow
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly int $qrResolveCount,
        public readonly int $menuOpenCount,
    ) {}

    /**
     * @return array{id: int, label: string, qrResolveCount: int, menuOpenCount: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'qrResolveCount' => $this->qrResolveCount,
            'menuOpenCount' => $this->menuOpenCount,
        ];
    }
}
