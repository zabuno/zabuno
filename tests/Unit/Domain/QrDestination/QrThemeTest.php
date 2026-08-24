<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\QrDestination;

use App\Domain\QrDestination\QrLayout;
use App\Domain\QrDestination\QrTheme;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * S1-WP04b6 RED — proves the not-yet-implemented App\Domain\QrDestination\
 * QrTheme enum and the JSON-serializable App\Domain\QrDestination\QrLayout
 * value object (frozen MASTER contract handed to this writer).
 *
 * Today App\Domain\QrDestination\QrTheme and App\Domain\QrDestination\
 * QrLayout do not exist, so every test below fails RED with an
 * enum/class-not-found error, not a logic assertion failure.
 *
 * Frozen contract:
 *   - Exact case-sensitive theme keys: classic|minimal|bold|rounded|
 *     branded|highContrast.
 *   - QrLayout is JSON-serializable with exactly: schemaVersion=1, theme,
 *     foregroundRgb, backgroundRgb, quietZonePixels=24,
 *     minContrastRatio=7.0.
 *   - Exact palettes (foreground/background hex, uppercase, no '#'):
 *       classic       000000 / FFFFFF
 *       minimal       1F2937 / F9FAFB
 *       bold          111827 / FDE68A
 *       rounded       064E3B / D1FAE5
 *       branded       1E3A8A / DBEAFE
 *       highContrast  000000 / FFFF00
 *   - Every palette's real computed WCAG contrast ratio must be >= 7.0.
 *
 * Requirement IDs: QR-THEME-KEYS-01, QR-LAYOUT-SCHEMA-01,
 * QR-THEME-PALETTE-01, QR-THEME-CONTRAST-01.
 */
final class QrThemeTest extends TestCase
{
    private const array EXPECTED_PALETTES = [
        'classic' => ['foregroundRgb' => '000000', 'backgroundRgb' => 'FFFFFF'],
        'minimal' => ['foregroundRgb' => '1F2937', 'backgroundRgb' => 'F9FAFB'],
        'bold' => ['foregroundRgb' => '111827', 'backgroundRgb' => 'FDE68A'],
        'rounded' => ['foregroundRgb' => '064E3B', 'backgroundRgb' => 'D1FAE5'],
        'branded' => ['foregroundRgb' => '1E3A8A', 'backgroundRgb' => 'DBEAFE'],
        'highContrast' => ['foregroundRgb' => '000000', 'backgroundRgb' => 'FFFF00'],
    ];

    // --- QR-THEME-KEYS-01 ---------------------------------------------------

    public function test_qr_theme_enum_declares_exactly_the_six_frozen_case_sensitive_keys(): void
    {
        $enumClass = QrTheme::class;

        self::assertTrue(enum_exists($enumClass), 'App\\Domain\\QrDestination\\QrTheme enum mevcut değil.');

        $values = array_map(static fn ($case) => $case->value, $enumClass::cases());

        self::assertCount(6, $values, 'QrTheme tam olarak altı tema içermeli.');
        self::assertSame(
            ['classic', 'minimal', 'bold', 'rounded', 'branded', 'highContrast'],
            $values,
            'QR-THEME-KEYS-01: tema anahtarları tam olarak bu case-sensitive sırayla ve yazımla olmalı.'
        );
    }

    // --- QR-LAYOUT-SCHEMA-01 -------------------------------------------------

    public function test_qr_layout_is_json_serializable_with_exactly_the_frozen_schema_fields(): void
    {
        $layoutClass = QrLayout::class;

        self::assertTrue(class_exists($layoutClass), 'App\\Domain\\QrDestination\\QrLayout sınıfı mevcut değil.');

        $themeClass = QrTheme::class;
        self::assertTrue(enum_exists($themeClass), 'App\\Domain\\QrDestination\\QrTheme enum mevcut değil.');

        $layout = new $layoutClass($themeClass::from('classic'));

        self::assertInstanceOf(\JsonSerializable::class, $layout, 'QR-LAYOUT-SCHEMA-01: QrLayout JsonSerializable olmalı.');

        self::assertSame(7.0, $layout->minContrastRatio, 'QR-LAYOUT-SCHEMA-01: minContrastRatio özelliği sabit 7.0 olmalı (strict, doğrudan property).');

        $encoded = json_decode(json_encode($layout), true);

        self::assertIsArray($encoded);
        self::assertSame(1, $encoded['schemaVersion'] ?? null, 'QR-LAYOUT-SCHEMA-01: schemaVersion sabit 1 olmalı.');
        self::assertSame('classic', $encoded['theme'] ?? null, 'QR-LAYOUT-SCHEMA-01: theme alanı doğru olmalı.');
        self::assertSame('000000', $encoded['foregroundRgb'] ?? null, 'QR-LAYOUT-SCHEMA-01: classic foregroundRgb 000000 olmalı.');
        self::assertSame('FFFFFF', $encoded['backgroundRgb'] ?? null, 'QR-LAYOUT-SCHEMA-01: classic backgroundRgb FFFFFF olmalı.');
        self::assertSame(24, $encoded['quietZonePixels'] ?? null, 'QR-LAYOUT-SCHEMA-01: quietZonePixels sabit 24 olmalı.');
        self::assertIsNumeric($encoded['minContrastRatio'] ?? null, 'QR-LAYOUT-SCHEMA-01: minContrastRatio JSON alanı sayısal olmalı.');
        self::assertEqualsWithDelta(
            7.0,
            $encoded['minContrastRatio'],
            0.0,
            'QR-LAYOUT-SCHEMA-01: minContrastRatio JSON round-trip\'i sayısal olarak tam 7.0 değerine eşit olmalı (PHP 8.3, JSON_PRESERVE_ZERO_FRACTION olmadan 7.0\'ı 7 olarak encode eder — bu strict float identity değil, sayısal eşitlik kanıtıdır).'
        );

        self::assertSame(
            ['schemaVersion', 'theme', 'foregroundRgb', 'backgroundRgb', 'quietZonePixels', 'minContrastRatio'],
            array_keys($encoded),
            'QR-LAYOUT-SCHEMA-01: JSON şeması tam olarak bu altı alanı, bu sırayla taşımalı — fazlası/eksiği yok.'
        );
    }

    // --- QR-THEME-PALETTE-01 / QR-THEME-CONTRAST-01 --------------------------

    public static function themeProvider(): array
    {
        return [
            'classic' => ['classic'],
            'minimal' => ['minimal'],
            'bold' => ['bold'],
            'rounded' => ['rounded'],
            'branded' => ['branded'],
            'highContrast' => ['highContrast'],
        ];
    }

    #[DataProvider('themeProvider')]
    public function test_each_theme_resolves_to_its_exact_frozen_palette_with_real_wcag_contrast_at_least_seven(string $themeKey): void
    {
        $themeClass = QrTheme::class;
        self::assertTrue(enum_exists($themeClass), 'App\\Domain\\QrDestination\\QrTheme enum mevcut değil.');

        $layoutClass = QrLayout::class;
        self::assertTrue(class_exists($layoutClass), 'App\\Domain\\QrDestination\\QrLayout sınıfı mevcut değil.');

        $case = $themeClass::from($themeKey);
        $layout = new $layoutClass($case);

        $expected = self::EXPECTED_PALETTES[$themeKey];

        self::assertSame(
            $expected['foregroundRgb'],
            $layout->foregroundRgb,
            "QR-THEME-PALETTE-01: {$themeKey} foregroundRgb tam olarak {$expected['foregroundRgb']} olmalı."
        );
        self::assertSame(
            $expected['backgroundRgb'],
            $layout->backgroundRgb,
            "QR-THEME-PALETTE-01: {$themeKey} backgroundRgb tam olarak {$expected['backgroundRgb']} olmalı."
        );

        $ratio = $this->wcagContrastRatio($layout->foregroundRgb, $layout->backgroundRgb);

        self::assertGreaterThanOrEqual(
            7.0,
            $ratio,
            "QR-THEME-CONTRAST-01: {$themeKey} paleti gerçek WCAG kontrast oranı >= 7.0 olmalı, hesaplanan: {$ratio}."
        );
    }

    private function wcagContrastRatio(string $hexA, string $hexB): float
    {
        $lumA = $this->relativeLuminance($hexA);
        $lumB = $this->relativeLuminance($hexB);

        $lighter = max($lumA, $lumB);
        $darker = min($lumA, $lumB);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $channel = static function (float $c): float {
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }
}
