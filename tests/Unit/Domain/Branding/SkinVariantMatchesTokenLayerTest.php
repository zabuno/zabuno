<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Branding;

use App\Domain\Branding\SkinVariant;
use PHPUnit\Framework\TestCase;

/**
 * BİÇİM EKSENİ UYDURULMADI — DEPODA ZATEN VARDI.
 *
 * `resources/css/aep/tokens/variants.css` altı biçim varyantı tanımlıyor
 * (`data-variant="a".."f"`) ve kendi ilk satırında ne olduğunu söylüyor:
 * *"the ONLY place the 12 micro-axes resolve. Components read tokens, never
 * the variant."* Bugüne kadar hiçbir üretim kodu bu özniteliği tüketmiyordu
 * (`docs/113` §5.3).
 *
 * Kiracıya açılan biçim ekseni işte budur: kiracı bir DEĞER değil, platform
 * tarafından bir kez ölçülmüş bir SEÇENEK seçer. Bu testin işi, sunucudaki
 * seçenek listesinin CSS'teki gerçek listeden ayrılmasını imkânsız
 * kılmaktır — ayrılırsa kiracı, tarayıcıda hiçbir şey yapmayan bir varyant
 * seçer ve bunu ilk fark eden kişi misafir olur.
 */
final class SkinVariantMatchesTokenLayerTest extends TestCase
{
    private const VARIANTS_CSS = __DIR__.'/../../../../resources/css/aep/tokens/variants.css';

    public function test_the_token_layer_is_where_it_is_claimed_to_be(): void
    {
        self::assertFileExists(
            self::VARIANTS_CSS,
            'Biçim ekseni token katmanından gelir; dosya yoksa seçenek listesinin dayanağı da yoktur.',
        );
    }

    public function test_every_offered_variant_actually_resolves_in_the_token_layer(): void
    {
        $declared = self::declaredInCss();

        foreach (SkinVariant::cases() as $variant) {
            self::assertContains(
                $variant->value,
                $declared,
                "Sunucu `{$variant->value}` varyantını sunuyor ama token katmanında karşılığı yok.",
            );
        }
    }

    public function test_no_measured_variant_is_quietly_withheld_from_the_tenant(): void
    {
        self::assertSame(
            self::declaredInCss(),
            array_map(static fn (SkinVariant $variant): string => $variant->value, SkinVariant::cases()),
        );
    }

    /** @return list<string> */
    private static function declaredInCss(): array
    {
        $css = (string) file_get_contents(self::VARIANTS_CSS);

        preg_match_all('/\[data-variant="([a-z])"\]/', $css, $matches);

        $found = array_values(array_unique($matches[1]));
        sort($found);

        return $found;
    }
}
