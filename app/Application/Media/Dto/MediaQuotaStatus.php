<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

/**
 * Bir çalışma alanının kota durumu — sahibin ekranda okuduğu sayılar.
 *
 * `blockedReason` doluysa yeni yükleme durur; canlı menü teslimi hiçbir
 * zaman bu nesneye bakmaz (`docs/49` Faz 7 madde 2).
 */
final class MediaQuotaStatus
{
    public function __construct(
        public readonly string $planCode,
        public readonly string $planLabel,
        public readonly int $originalBytesUsed,
        public readonly int $originalBytesLimit,
        public readonly int $assetsUsed,
        public readonly int $assetsLimit,
        public readonly int $monthlyUploadsUsed,
        public readonly ?int $monthlyUploadsLimit,
        public readonly int $trashRetentionDays,
        public readonly ?string $blockedReason = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'planCode' => $this->planCode,
            'planLabel' => $this->planLabel,
            'originalBytesUsed' => $this->originalBytesUsed,
            'originalBytesLimit' => $this->originalBytesLimit,
            'assetsUsed' => $this->assetsUsed,
            'assetsLimit' => $this->assetsLimit,
            'monthlyUploadsUsed' => $this->monthlyUploadsUsed,
            'monthlyUploadsLimit' => $this->monthlyUploadsLimit,
            'trashRetentionDays' => $this->trashRetentionDays,
            'blockedReason' => $this->blockedReason,
        ];
    }
}
