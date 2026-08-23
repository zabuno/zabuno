<?php

declare(strict_types=1);

namespace App\Application\Analytics\Dto;

final class AnalyticsSummary
{
    public function __construct(
        public readonly string $range,
        public readonly int $qrResolveCount,
        public readonly int $menuOpenCount,
        public readonly string $generatedAt,
    ) {}

    /**
     * @return array{range: string, qrResolveCount: int, menuOpenCount: int, generatedAt: string}
     */
    public function toArray(): array
    {
        return [
            'range' => $this->range,
            'qrResolveCount' => $this->qrResolveCount,
            'menuOpenCount' => $this->menuOpenCount,
            'generatedAt' => $this->generatedAt,
        ];
    }
}
