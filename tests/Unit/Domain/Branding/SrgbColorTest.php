<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Branding;

use App\Domain\Branding\SrgbColor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Kontrast oranı TAHMİN EDİLMEZ, HESAPLANIR.
 *
 * Bu testin tamamı dışarıdan doğrulanabilir sayılara bakar: WCAG 2.x'in
 * kendi örnekleri ve herkesin bildiği uç değerler. Kendi hesabımızı kendi
 * beklentimizle doğrulasaydık, formül yanlış olsa bile test geçerdi.
 */
final class SrgbColorTest extends TestCase
{
    public function test_black_on_white_is_the_maximum_ratio(): void
    {
        $ratio = SrgbColor::fromHex('#000000')->contrastRatio(SrgbColor::fromHex('#ffffff'));

        self::assertEqualsWithDelta(21.0, $ratio, 0.001);
    }

    public function test_a_colour_against_itself_is_the_minimum_ratio(): void
    {
        $ratio = SrgbColor::fromHex('#c8102e')->contrastRatio(SrgbColor::fromHex('#c8102e'));

        self::assertEqualsWithDelta(1.0, $ratio, 0.001);
    }

    /**
     * `#767676`, WCAG'ın kendi belgelerinde geçen sınır griyidir: beyaz
     * üstünde 4.54 verir, yani AA'yı KIL PAYI geçer. Formülde 1/1000'lik
     * bir sapma bile bu sayıyı eşiğin altına düşürür — kapı burada.
     */
    public function test_the_wcag_borderline_grey_measures_just_above_the_aa_floor(): void
    {
        $ratio = SrgbColor::fromHex('#767676')->contrastRatio(SrgbColor::fromHex('#ffffff'));

        self::assertEqualsWithDelta(4.54, $ratio, 0.01);
        self::assertGreaterThanOrEqual(4.5, $ratio);
    }

    /** Bir tık açığı AA'yı geçmez: eşik gerçekten orada duruyor mu? */
    public function test_one_step_lighter_than_the_borderline_grey_fails_aa(): void
    {
        $ratio = SrgbColor::fromHex('#777777')->contrastRatio(SrgbColor::fromHex('#ffffff'));

        self::assertLessThan(4.5, $ratio);
    }

    public function test_the_ratio_does_not_depend_on_which_colour_is_named_first(): void
    {
        $ink = SrgbColor::fromHex('#1f2937');
        $canvas = SrgbColor::fromHex('#ffffff');

        self::assertSame(
            $ink->contrastRatio($canvas),
            $canvas->contrastRatio($ink),
        );
    }

    public function test_hue_and_saturation_survive_a_lightness_change(): void
    {
        $seed = SrgbColor::fromHex('#c8102e');
        $darker = $seed->withLightness(0.2);

        self::assertEqualsWithDelta($seed->hue(), $darker->hue(), 1.0);
        self::assertEqualsWithDelta($seed->saturation(), $darker->saturation(), 0.02);
        self::assertLessThan($seed->relativeLuminance(), $darker->relativeLuminance());
    }

    public function test_a_hex_string_survives_a_round_trip(): void
    {
        self::assertSame('#c8102e', SrgbColor::fromHex('#C8102E')->toHex());
    }

    public function test_an_unreadable_value_is_refused_rather_than_guessed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SrgbColor::fromHex('bordo');
    }

    public function test_the_forgiving_parser_returns_null_instead_of_throwing(): void
    {
        self::assertNull(SrgbColor::tryFromHex('#abc'));
        self::assertNull(SrgbColor::tryFromHex(null));
        self::assertSame('#00ff00', SrgbColor::tryFromHex('#00FF00')?->toHex());
    }
}
