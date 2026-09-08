<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Branding\SrgbColor;
use RuntimeException;

/**
 * `resources/css/site-identity.css` dosyasını OKUR ve jetonları çözer.
 *
 * ── Neden ayrı bir sınıf ─────────────────────────────────────────────────
 *
 * İki kapı aynı dosyayı okuyor: `CorporateIdentityContrastTest` (renklerin
 * gerçekten okunur olduğunu ölçer) ve `CorporateIdentityScopeTest` (panele
 * sızmadığını ölçer). Ayrıştırıcıyı iki testte iki kez yazmak, bir gün
 * ikisinin farklı şeyi "aynı dosya" sanması demekti.
 *
 * ── Ne yapar ─────────────────────────────────────────────────────────────
 *
 * CSS'i katman katman değil, DÜZ okur: `:root` bloklarının hepsini sırayla
 * birleştirir (sonraki öncekini ezer — kaynak sırası kaskadın kendisidir) ve
 * `[data-theme='dark']` bloğunu ayrı toplar. Sonra `var()` zincirlerini
 * yineleyerek çözer.
 *
 * ── Ne YAPMAZ ────────────────────────────────────────────────────────────
 *
 * Bir tarayıcı değildir. `color-mix()`, `calc()` ve gradyan değerlerini
 * hesaplamaz; onları `null` bırakır. Bu bilerek: yarı saydam bir ışıma
 * jetonunun kontrast oranı, ARKASINDAKİ yüzeye bağlıdır ve o yüzey CSS'te
 * yazılı değildir. Ölçülemeyen bir şeyi tahmin edip "geçti" demek, ölçmemekten
 * kötüdür.
 *
 * Ölçülen şey bu yüzden yalnız DÜZ renk taşıyan roller: metin, zemin, kenar,
 * eylem. Zaten okunurluğu belirleyen de onlardır.
 */
final class CorporateIdentityTokens
{
    /** @var array<string, string> ham bildirimler — açık kip (`:root`) */
    private array $light = [];

    /** @var array<string, string> ham bildirimler — koyu kip */
    private array $dark = [];

    public function __construct(string $css)
    {
        $stripped = self::withoutComments($css);

        foreach (self::blocks($stripped) as [$selector, $body]) {
            $declarations = self::declarations($body);

            if ($selector === ':root') {
                $this->light = [...$this->light, ...$declarations];

                continue;
            }

            if ($selector === "[data-theme='dark']") {
                $this->dark = [...$this->dark, ...$declarations];
            }
        }

        if ($this->light === [] || $this->dark === []) {
            throw new RuntimeException(
                'site-identity.css okunamadı: `:root` ya da `[data-theme=\'dark\']` bloğu bulunamadı. '
                .'Ölçüm dayanaksız kalmasın diye kapı burada durur.'
            );
        }
    }

    /** Yalnız §1'in ham renk taşıdığını ölçen kapı için: yorumsuz kaynak. */
    public static function withoutComments(string $css): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /** @return list<string> bu dosyada tanımlı bütün `--zc-*` adları */
    public function names(): array
    {
        return array_values(array_unique([...array_keys($this->light), ...array_keys($this->dark)]));
    }

    /**
     * Bir jetonun verilen kipteki ÇÖZÜLMÜŞ sRGB rengi.
     *
     * `var()` zinciri sonuna kadar takip edilir. Zincir düz bir renge
     * varmıyorsa (`color-mix`, gradyan, uzunluk) `null` döner ve çağıran
     * kapı o rolü ölçmez.
     */
    public function color(string $name, bool $dark): ?SrgbColor
    {
        $value = $this->raw($name, $dark);

        for ($hop = 0; $hop < 12; $hop++) {
            if ($value === null) {
                return null;
            }

            $value = trim($value);

            if (preg_match('#^var\((--[a-z0-9-]+)\)$#', $value, $reference) === 1) {
                $value = $this->raw($reference[1], $dark);

                continue;
            }

            return SrgbColor::tryFromHex($value);
        }

        return null;
    }

    /** İki jeton arasındaki WCAG 2.x kontrast oranı; ölçülemiyorsa `null`. */
    public function contrast(string $foreground, string $background, bool $dark): ?float
    {
        $ink = $this->color($foreground, $dark);
        $ground = $this->color($background, $dark);

        if ($ink === null || $ground === null) {
            return null;
        }

        return $ink->contrastRatio($ground);
    }

    private function raw(string $name, bool $dark): ?string
    {
        if ($dark && array_key_exists($name, $this->dark)) {
            return $this->dark[$name];
        }

        return $this->light[$name] ?? null;
    }

    /**
     * Üst seviye seçici blokları.
     *
     * Satır tabanlı, çünkü bu dosyada iç içe blok yok ve olması da
     * istenmiyor: kimlik katmanı bir bileşen kütüphanesi değil, bir jeton
     * tanımıdır. `@media` blokları atlanır — orada renk TANIMI değil,
     * yalnız erişilebilirlik düzeltmesi yaşar.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function blocks(string $css): array
    {
        $blocks = [];
        $selector = null;
        $body = '';
        $depth = 0;

        foreach (explode("\n", $css) as $line) {
            if ($selector === null) {
                if (preg_match('#^([^\s@][^{]*)\{\s*$#', $line, $match) === 1) {
                    $selector = trim($match[1]);
                    $body = '';
                    $depth = 1;
                }

                continue;
            }

            $depth += substr_count($line, '{') - substr_count($line, '}');

            if ($depth <= 0) {
                $blocks[] = [$selector, $body];
                $selector = null;

                continue;
            }

            $body .= $line."\n";
        }

        return $blocks;
    }

    /** @return array<string, string> */
    private static function declarations(string $body): array
    {
        preg_match_all('#(--[a-z0-9-]+)\s*:\s*([^;]+);#', $body, $matches, PREG_SET_ORDER);

        $declarations = [];

        foreach ($matches as [, $name, $value]) {
            $declarations[$name] = trim(preg_replace('#\s+#', ' ', $value) ?? '');
        }

        return $declarations;
    }
}
