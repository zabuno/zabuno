<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

use JsonSerializable;

final readonly class QrLayout implements JsonSerializable
{
    public int $schemaVersion;

    public string $theme;

    public string $foregroundRgb;

    public string $backgroundRgb;

    public int $quietZonePixels;

    public float $minContrastRatio;

    public function __construct(QrTheme $theme)
    {
        $this->schemaVersion = 1;
        $this->theme = $theme->value;
        $this->foregroundRgb = $theme->foregroundRgb();
        $this->backgroundRgb = $theme->backgroundRgb();
        $this->quietZonePixels = 24;
        $this->minContrastRatio = 7.0;
    }

    /**
     * @return array{schemaVersion: int, theme: string, foregroundRgb: string, backgroundRgb: string, quietZonePixels: int, minContrastRatio: float}
     */
    public function jsonSerialize(): array
    {
        return [
            'schemaVersion' => $this->schemaVersion,
            'theme' => $this->theme,
            'foregroundRgb' => $this->foregroundRgb,
            'backgroundRgb' => $this->backgroundRgb,
            'quietZonePixels' => $this->quietZonePixels,
            'minContrastRatio' => $this->minContrastRatio,
        ];
    }
}
