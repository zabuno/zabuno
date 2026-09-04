<?php

declare(strict_types=1);

namespace Tests\Unit\QrDestination;

use App\Domain\QrDestination\QrContrast;
use App\Domain\QrDestination\QrLayout;
use App\Domain\QrDestination\QrTheme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * QRTHEME-SCANNABLE-01 — FF-112, `docs/104` Döngü 10.
 *
 * Tema bir zevk meselesi değildir: okunmayan bir karekod, masadaki ölü
 * kâğıttır ve bunu ilk fark eden kişi telefonunu kartın üstünde sallayan
 * misafirdir.
 */
final class QrContrastTest extends TestCase
{
    public function test_black_on_white_is_scannable(): void
    {
        self::assertTrue(QrContrast::isScannable('000000', 'FFFFFF'));
        self::assertEqualsWithDelta(21.0, QrContrast::ratio('000000', 'FFFFFF'), 0.01);
    }

    public function test_inverse_contrast_is_refused_even_when_the_ratio_is_huge(): void
    {
        // Açık modül / koyu zemin: oran 21:1 ama tarayıcıların çoğu
        // koyu-üstüne-açık varsayar ve bunu HİÇ okumaz.
        self::assertFalse(QrContrast::isScannable('FFFFFF', '000000'));
    }

    public function test_a_pale_brand_colour_is_refused(): void
    {
        // Açık sarı üstüne beyaz: göze güzel, kameraya görünmez.
        self::assertFalse(QrContrast::isScannable('FFE066', 'FFFFFF'));
    }

    public function test_an_unreadable_colour_value_is_treated_as_black(): void
    {
        // Şüphe hâlinde güvenli taraf KOYULUKTUR: bilinmeyen bir değeri
        // "muhtemelen açıktır" saymak, taranamayan bir kod basmanın yoludur.
        self::assertTrue(QrContrast::isScannable('zzzzzz', 'FFFFFF'));
    }

    /** @return list<array{0: QrTheme}> */
    public static function themes(): array
    {
        return array_map(static fn (QrTheme $theme): array => [$theme], QrTheme::cases());
    }

    #[DataProvider('themes')]
    public function test_every_shipped_theme_is_scannable(QrTheme $theme): void
    {
        self::assertTrue(
            QrContrast::isScannable($theme->foregroundRgb(), $theme->backgroundRgb()),
            "QRTHEME-SCANNABLE-01: '{$theme->value}' teması taranabilir olmalı — ürün okunmayan bir tema sunamaz.",
        );
    }

    public function test_the_branded_theme_uses_the_real_brand_colour(): void
    {
        $layout = QrLayout::branded('#1B4332');

        self::assertSame('branded', $layout->theme);
        self::assertSame('1B4332', $layout->foregroundRgb);
        self::assertSame('FFFFFF', $layout->backgroundRgb);
        self::assertFalse($layout->fellBackToClassic);
    }

    public function test_an_unscannable_brand_colour_falls_back_to_classic_and_says_so(): void
    {
        $layout = QrLayout::branded('#FFE066');

        self::assertSame('classic', $layout->theme);
        self::assertSame('000000', $layout->foregroundRgb);
        // Sessizce düşmek bir yalandır: sahip "markalı" seçti, çıktının neden
        // siyah olduğunu bilmeli.
        self::assertTrue($layout->fellBackToClassic);
    }

    public function test_a_brand_with_no_colour_falls_back_without_inventing_one(): void
    {
        $layout = QrLayout::branded(null);

        self::assertSame('classic', $layout->theme);
        self::assertTrue($layout->fellBackToClassic);
    }
}
