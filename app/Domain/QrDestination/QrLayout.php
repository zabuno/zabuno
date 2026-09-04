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

    /**
     * Markanın rengi taranabilir olmadığı için klasiğe DÜŞÜLDÜ mü (FF-112)?
     *
     * Sessizce düşmek bir yalandır: sahip "markalı" seçmiştir ve çıktının
     * neden siyah olduğunu bilmelidir. Bu bayrak ekrana taşınır.
     */
    public bool $fellBackToClassic;

    public function __construct(
        QrTheme $theme,
        bool $fellBackToClassic = false,
        ?string $foregroundOverride = null,
    ) {
        $this->schemaVersion = 1;
        $this->theme = $theme->value;
        $this->foregroundRgb = $foregroundOverride ?? $theme->foregroundRgb();
        $this->backgroundRgb = $theme->backgroundRgb();
        $this->quietZonePixels = 24;
        $this->minContrastRatio = 7.0;
        $this->fellBackToClassic = $fellBackToClassic;
    }

    /**
     * "Markalı" tema GERÇEKTEN markanın rengini kullanır (FF-112).
     *
     * Eskiden `Branded` sabit bir laciverttiı: adı "markalı"ydı ama kiracının
     * markasıyla hiçbir ilgisi yoktu. Adı yaptığı işi söylemeyen bir kontrolü
     * kullanıcı bir kez dener ve bir daha güvenmez.
     *
     * Marka rengi TARANABİLİR DEĞİLSE klasiğe düşülür ve bu söylenir: açık
     * sarı bir marka rengiyle basılan kod, masada okunmayan bir karttır ve
     * bunu ilk fark eden kişi misafirdir.
     */
    public static function branded(?string $brandPrimaryColor): self
    {
        $background = QrTheme::Classic->backgroundRgb();
        $foreground = $brandPrimaryColor === null ? null : ltrim($brandPrimaryColor, '#');

        if ($foreground === null || ! QrContrast::isScannable($foreground, $background)) {
            return new self(QrTheme::Classic, fellBackToClassic: true);
        }

        return new self(QrTheme::Branded, foregroundOverride: strtoupper($foreground));
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
