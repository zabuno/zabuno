<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

/**
 * Karekodun TARANABİLİRLİK kısıtı — `docs/104` Döngü 10.
 *
 * Tema bir zevk meselesi değildir: bir karekod okunamazsa masadaki kart ölü
 * kâğıttır ve bunu ilk fark eden kişi, telefonunu kartın üstünde sallayan
 * misafirdir. İki kural pazarlıksızdır ve ikisi de araştırmadan gelir:
 *
 *   1. **Koyu modül, AÇIK zemin üstünde.** Ters kontrast (açık modül, koyu
 *      zemin) birçok tarayıcıda hiç okunmaz — tarayıcılar koyu-üstüne-açık
 *      varsayar. Bu, "bazı telefonlarda çalışır" demektir; yani bir destek
 *      talebidir.
 *   2. **Kontrast oranı yeterli olmalı.** Sektör tavsiyesi ≥ %40 kontrast;
 *      bu ürün WCAG'ın oran ölçüsüyle 4:1 tabanını kullanır — düşük ışıkta,
 *      buğulu bir telefon kamerasıyla ve yıpranmış bir kâğıtla da okunmalı.
 *
 * Bu sınıf hiçbir şey ÇİZMEZ; yalnız "bu renk çifti basılabilir mi" sorusunu
 * yanıtlar. Karar noktası tek olmalı: iki ayrı yerde hesaplanan bir kısıt,
 * bir gün iki farklı cevap verir.
 */
final class QrContrast
{
    /** Taban oran. Altındaki her çift REDDEDİLİR, uyarılmaz. */
    public const float MIN_RATIO = 4.0;

    /** @return float 1.0 (aynı renk) .. 21.0 (siyah-beyaz) */
    public static function ratio(string $foregroundRgb, string $backgroundRgb): float
    {
        $foreground = self::relativeLuminance($foregroundRgb);
        $background = self::relativeLuminance($backgroundRgb);

        $lighter = max($foreground, $background);
        $darker = min($foreground, $background);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Bu çift basılabilir mi?
     *
     * "Yeterince koyu" DEĞİL, "zeminden koyu" aranır: ikisi de koyu ama
     * kontrastı yüksek bir çift (siyah üstüne parlak sarı gibi) oranı
     * geçebilir ama ters kontrast olduğu için taranmaz.
     */
    public static function isScannable(string $foregroundRgb, string $backgroundRgb): bool
    {
        if (self::relativeLuminance($foregroundRgb) >= self::relativeLuminance($backgroundRgb)) {
            return false;
        }

        return self::ratio($foregroundRgb, $backgroundRgb) >= self::MIN_RATIO;
    }

    /** WCAG 2.x bağıl parlaklık. */
    private static function relativeLuminance(string $rgb): float
    {
        $hex = ltrim($rgb, '#');

        if (strlen($hex) !== 6 || preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            /*
                Okunamayan bir renk SİYAH sayılır: bilinmeyen bir değeri
                "muhtemelen açıktır" diye kabul etmek, taranamayan bir kod
                basmanın yoludur. Şüphe hâlinde güvenli taraf koyuluktur.
            */
            return 0.0;
        }

        $channels = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];

        $linear = array_map(
            static fn (float $channel): float => $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
