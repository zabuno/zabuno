<?php

declare(strict_types=1);

namespace App\Domain\Branding;

use InvalidArgumentException;

/**
 * Tek bir sRGB rengi ve onun ÖLÇÜLEBİLİR özellikleri.
 *
 * Bu sınıf hiçbir şey çizmez ve hiçbir şeye karar vermez; yalnız "bu iki
 * renk arasındaki kontrast oranı kaçtır" ve "bu rengin tonunu koruyup
 * açıklığını nasıl değiştiririm" sorularını yanıtlar. Karar noktası TEK
 * olmalıdır — `QrContrast` da bağıl parlaklığı buradan okur; iki ayrı yerde
 * hesaplanan bir kısıt, bir gün iki farklı cevap verir.
 *
 * Renk uzayı seçimi: ton (hue) ve doygunluk (saturation) HSL'de tutulur,
 * yalnız açıklık (lightness) oynatılır. Sebep ürünle ilgilidir: kiracı
 * "bordo" der; ürün onu koyulaştırabilir ama MORA ÇEVİREMEZ. Açıklık
 * ekseninde yürümek, kiracının seçtiği tonu yaklaşık koruyan tek harekettir.
 */
final readonly class SrgbColor
{
    private function __construct(
        public int $red,
        public int $green,
        public int $blue,
    ) {}

    /** @throws InvalidArgumentException tanınmayan bir değer tahmin edilmez, reddedilir */
    public static function fromHex(string $hex): self
    {
        $color = self::tryFromHex($hex);

        if ($color === null) {
            throw new InvalidArgumentException(
                sprintf('Renk `#rrggbb` biçiminde olmalı; "%s" okunamadı.', $hex)
            );
        }

        return $color;
    }

    /** Kısa biçim (`#abc`) ve renk adı (`red`) kabul edilmez: depolanan biçim tektir. */
    public static function tryFromHex(?string $hex): ?self
    {
        $value = strtolower(trim((string) $hex));

        if (preg_match('/^#[0-9a-f]{6}$/', $value) !== 1) {
            return null;
        }

        return new self(
            (int) hexdec(substr($value, 1, 2)),
            (int) hexdec(substr($value, 3, 2)),
            (int) hexdec(substr($value, 5, 2)),
        );
    }

    public function toHex(): string
    {
        return sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);
    }

    /** WCAG 2.x bağıl parlaklık (0.0 siyah … 1.0 beyaz). */
    public function relativeLuminance(): float
    {
        $linear = array_map(
            static fn (int $channel): float => ($c = (float) $channel / 255) <= 0.03928
                ? $c / 12.92
                : (($c + 0.055) / 1.055) ** 2.4,
            [$this->red, $this->green, $this->blue],
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /**
     * WCAG 2.x kontrast oranı: 1.0 (aynı renk) … 21.0 (siyah–beyaz).
     *
     * Simetriktir; hangisinin metin hangisinin zemin olduğu oranı
     * değiştirmez. Bu yüzden "hangi çift" kararı çağıranın işidir.
     */
    public function contrastRatio(self $other): float
    {
        $mine = $this->relativeLuminance();
        $theirs = $other->relativeLuminance();

        return (max($mine, $theirs) + 0.05) / (min($mine, $theirs) + 0.05);
    }

    /** 0–360 derece. Gri bir renkte ton yoktur; 0 döner. */
    public function hue(): float
    {
        return $this->toHsl()[0];
    }

    /** 0.0–1.0 */
    public function saturation(): float
    {
        return $this->toHsl()[1];
    }

    /** 0.0–1.0 */
    public function lightness(): float
    {
        return $this->toHsl()[2];
    }

    /** Ton ve doygunluk aynen kalır, yalnız açıklık değişir. */
    public function withLightness(float $lightness): self
    {
        [$hue, $saturation] = $this->toHsl();

        return self::fromHsl($hue, $saturation, max(0.0, min(1.0, $lightness)));
    }

    /** @return array{0: float, 1: float, 2: float} ton (0–360), doygunluk (0–1), açıklık (0–1) */
    public function toHsl(): array
    {
        /*
            KANALLAR AÇIKÇA FLOAT'A ÇEVRİLİR.

            PHP'de `255 / 255` bir TAM SAYI döner ve `0 === 0.0` yanlıştır;
            yani gri bir renkte "doygunluk yok" kısa devresi çalışmaz ve
            hesap sıfıra bölünür. Beyaz bir marka rengi bu satır yüzünden
            500 hatası verirdi.
        */
        $red = (float) $this->red / 255;
        $green = (float) $this->green / 255;
        $blue = (float) $this->blue / 255;

        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $lightness = ($max + $min) / 2;
        $delta = $max - $min;

        if ($delta <= 0.0) {
            return [0.0, 0.0, $lightness];
        }

        $saturation = $lightness > 0.5
            ? $delta / (2 - $max - $min)
            : $delta / ($max + $min);

        $hue = match ($max) {
            $red => fmod(($green - $blue) / $delta, 6.0),
            $green => (($blue - $red) / $delta) + 2,
            default => (($red - $green) / $delta) + 4,
        } * 60;

        return [$hue < 0 ? $hue + 360 : $hue, $saturation, $lightness];
    }

    public static function fromHsl(float $hue, float $saturation, float $lightness): self
    {
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $sector = fmod(($hue < 0 ? $hue + 360 : $hue) / 60, 6.0);
        $second = $chroma * (1 - abs(fmod($sector, 2.0) - 1));
        $offset = $lightness - $chroma / 2;

        [$red, $green, $blue] = match (true) {
            $sector < 1 => [$chroma, $second, 0.0],
            $sector < 2 => [$second, $chroma, 0.0],
            $sector < 3 => [0.0, $chroma, $second],
            $sector < 4 => [0.0, $second, $chroma],
            $sector < 5 => [$second, 0.0, $chroma],
            default => [$chroma, 0.0, $second],
        };

        return new self(
            (int) round(($red + $offset) * 255),
            (int) round(($green + $offset) * 255),
            (int) round(($blue + $offset) * 255),
        );
    }
}
