<?php

declare(strict_types=1);

namespace App\Domain\Branding;

/**
 * Rampanın rolleri ve HER ROLÜN KENDİ EŞİĞİ.
 *
 * "Marka rengi AA geçsin" tek bir cümle değildir: aynı ton bir düğmenin
 * zemini olarak, bir fiyatın metin rengi olarak ve 2 px'lik bir çizgi
 * olarak üç ayrı şey yapar ve üçünün ölçüsü de ayrıdır. WCAG 2.2 bunu
 * zaten ayırıyor:
 *
 *   · 1.4.3 (AA, normal metin) → 4.5:1
 *   · 1.4.11 (metin olmayan arayüz bileşeni ve grafik) → 3:1
 *
 * Tek bir eşiği her yere uygulamak iki yönde de yanlış olurdu: 3:1'i metne
 * uygulamak menüyü okunmaz bırakır, 4.5:1'i ince bir çizgiye uygulamak ise
 * kiracının rengini gereksiz yere koyulaştırıp markayı bozar.
 */
enum BrandRampRole: string
{
    /** Marka dolgusu: birincil düğme zemini, seçili çip zemini. */
    case AccentSurface = 'accent-surface';

    /** O dolgunun ÜSTÜNDEKİ yazı. Türetilmez, iki üründen biri SEÇİLİR. */
    case OnAccentSurface = 'on-accent-surface';

    /** Marka renginin METİN olarak kullanımı: fiyat, bağlantı, etkin sekme. */
    case AccentInk = 'accent-ink';

    /** Yumuşak marka tonu: çip zemini, seçili satır. Üstünde metin durur. */
    case AccentSoftSurface = 'accent-soft-surface';

    /** Marka çizgisi: üst şerit, kategori altı çizgi, kenarlık. Metin değil. */
    case AccentBorder = 'accent-border';

    /**
     * Bu rolün geçmek zorunda olduğu WCAG 2.2 oranı.
     *
     * Metin rolleri 4.5 (1.4.3 AA); metin olmayan roller 3.0 (1.4.11).
     */
    public function floor(): float
    {
        return match ($this) {
            self::AccentSurface, self::OnAccentSurface, self::AccentInk, self::AccentSoftSurface => 4.5,
            self::AccentBorder => 3.0,
        };
    }

    /**
     * Rolün yazıldığı tasarım sistemi token'ı.
     *
     * Rampa BİLEŞENE değil TOKEN'a yazılır (`DS-RAW-PALETTE-BANNED-01`):
     * bileşen token okur, renk üretmez. Adlar `docs/113` §2.2'deki
     * kaynak→depo eşlemesinden gelir; uydurulmuş bir ad alanı açmak,
     * misafir yüzeyini tasarım sisteminden bir kez daha koparırdı.
     */
    public function token(): string
    {
        return match ($this) {
            self::AccentSurface => '--aep-accent-primary',
            self::OnAccentSurface => '--aep-on-accent-primary',
            self::AccentInk => '--aep-accent-secondary-text',
            self::AccentSoftSurface => '--aep-accent-secondary-fill-soft',
            self::AccentBorder => '--aep-accent-secondary',
        };
    }
}
