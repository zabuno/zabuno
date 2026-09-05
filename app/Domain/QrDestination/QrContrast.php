<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

use App\Domain\Branding\SrgbColor;

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

    /**
     * WCAG 2.x bağıl parlaklık — hesabın kendisi `SrgbColor`'dadır.
     *
     * Formül bir zamanlar burada da yazılıydı ve FF-174 marka rampası aynı
     * formülü ikinci kez yazmak üzereydi. Bu dosyanın kendi başlığı ne
     * yapılacağını söylüyordu: *"karar noktası tek olmalı."* Karekodun
     * TARANABİLİRLİK kuralı burada kalır; RENK MATEMATİĞİ tek yerde durur.
     */
    private static function relativeLuminance(string $rgb): float
    {
        /*
            Okunamayan bir renk SİYAH sayılır: bilinmeyen bir değeri
            "muhtemelen açıktır" diye kabul etmek, taranamayan bir kod
            basmanın yoludur. Şüphe hâlinde güvenli taraf koyuluktur.
        */
        return SrgbColor::tryFromHex('#'.ltrim($rgb, '#'))?->relativeLuminance() ?? 0.0;
    }
}
