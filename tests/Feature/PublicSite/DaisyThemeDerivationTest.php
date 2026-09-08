<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use Tests\TestCase;

/**
 * DAISY-THEME-01…05 — daisyUI teması KURUMSAL JETONLARDAN türer.
 *
 * ── SAHİBİN EZMESİ (2026-09-08 akşamı) ───────────────────────────────────
 *
 * Bu kapı ilk yazıldığında (aynı günün sabahı) temanın PANELİN marka
 * jetonlarından (`--aep-*`) türemesini şart koşuyordu. Sahip akşam o kısıtı
 * kaldırdı: *"temanın mevcut marka jetonlarını sikerim, yeter artık."*
 *
 * Sebep ölçülmüştü: daisyUI kuruldu, tema kuruldu, bu kapı yeşile döndü ve
 * ekranda hiçbir şey değişmedi — çünkü panelin jetonları sekiz saat bakılan
 * bir ekran için seçilmişti. Ayrıntı ve gerekçe: `docs/145`.
 *
 * ── Kapının kendisi DEĞİŞMEDİ, yalnız ADRESİ değişti ─────────────────────
 *
 * Kural hâlâ tek cümle: temada ham bir renk yoktur, her renk var olan bir
 * jetona işaret eder. Değişen tek şey, o jetonların hangi dosyada yaşadığı:
 * `resources/css/aep/tokens/*` yerine `resources/css/site-identity.css`.
 *
 * Kullanıcı yolculuğu: sahip kurumsal sitenin morunu bir ton koyulaştırmak
 * ister. Tek bir dosya değişir (`site-identity.css` §1) ve o gün kurumsal
 * sitenin tamamı döner — PANEL ise dönmez, çünkü artık aynı kaynağa
 * bakmıyorlar ve bu bilinçli bir karardır.
 *
 * ── Ne ölçülüyor, ne ölçülmüyor ──────────────────────────────────────────
 *
 * Ölçülen: temada ham bir renk YOK, her renk var olan bir `--zc-*` jetonuna
 * işaret ediyor, tema panelin `--aep-*` jetonlarından bir tekini bile
 * ANMIYOR, daisyUI'nin hazır temaları kapalı, 28 değişkenin hepsi iki temada
 * da yazılmış, ve derlenmiş CSS'te ÖNEKSİZ bir daisyUI sınıfı yok.
 *
 * Ölçülmeyen: rengin GÜZEL olup olmadığı. Kontrast oranları ise artık
 * ölçülüyor ama burada değil — `CorporateIdentityContrastTest` (KIMLIK-02/03)
 * onları jeton katmanının kendisinde hesaplar.
 */
final class DaisyThemeDerivationTest extends TestCase
{
    private const THEME_FILE = 'resources/css/daisy-theme.css';

    /** Kurumsal jetonların TEK tanımı (`docs/145`). */
    private const IDENTITY_FILE = 'resources/css/site-identity.css';

    /**
     * daisyUI 5'in bir temada beklediği DEĞİŞKENLERİN TAMAMI.
     *
     * Liste elle yazılmadı: `node_modules/daisyui/themes.css` içindeki hazır
     * `light` temasından okunuyor (bkz. `themeVariables()`), yani daisyUI bir
     * gün yeni bir değişken eklerse bu kapı onu ister — biz fark etmeden
     * daisyUI'nin varsayılanına düşmek yerine.
     */
    private function themeVariables(): array
    {
        $path = base_path('node_modules/daisyui/themes.css');

        self::assertFileExists(
            $path,
            'daisyUI paketi kurulu değil; tema türetimi ÖLÇÜLEMEDİ. '
            .'Ölçülemeyen bir sonuç yeşil gösterilmez — `npm ci` çalıştırın.'
        );

        $css = (string) file_get_contents($path);

        // İlk tema bloğu hazır `light` temasıdır; değişken adları hepsinde aynı.
        self::assertSame(1, preg_match('#\[data-theme=light\]\{(.*?)\}#s', $css, $match));

        preg_match_all('#(--[a-z0-9-]+):#', $match[1], $names);

        $variables = array_values(array_unique($names[1]));

        self::assertGreaterThanOrEqual(28, count($variables), 'daisyUI tema değişkenleri okunamadı.');

        return $variables;
    }

    private function theme(): string
    {
        return (string) file_get_contents(base_path(self::THEME_FILE));
    }

    /**
     * `@plugin "daisyui/theme" { … }` bloklarının GÖVDELERİ.
     *
     * @return array<string, string> tema adı → gövde
     */
    private function themeBlocks(): array
    {
        preg_match_all(
            '#@plugin\s+"daisyui/theme"\s*\{(.*?)\n\}#s',
            $this->theme(),
            $matches,
        );

        $blocks = [];

        foreach ($matches[1] as $body) {
            self::assertSame(1, preg_match("#name:\s*'([a-z-]+)'#", $body, $name), 'Adsız bir tema bloğu var.');
            $blocks[$name[1]] = $body;
        }

        return $blocks;
    }

    // --- DAISY-THEME-01 --------------------------------------------------------

    public function test_the_theme_file_contains_no_literal_colour(): void
    {
        /*
            Yorum satırları ölçüm dışında: bir gerekçe metninde `#003399`
            geçmesi bir renk SEÇİMİ değil, bir açıklamadır.
        */
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->theme());

        foreach (['#', 'rgb(', 'rgba(', 'hsl(', 'oklch(', 'lab(', 'color-mix('] as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $css,
                "DAISY-THEME-01: temada ham bir renk ifadesi var ([{$needle}]). "
                .'Her renk `var(--zc-…)` olmak zorunda; aksi hâlde depoda ikinci bir renk kaynağı doğar.'
            );
        }

        /*
            PANELİN JETONLARI TEMADA ANILMAZ.

            Bu satır sahibin ezme kararının kapıdaki karşılığıdır ve tersi de
            doğrudur: kurumsal tema bir gün yeniden `--aep-*` okumaya
            başlarsa, o an sessizce panelin görsel diline geri dönmüş olur.
            Böyle bir dönüş bir KARAR olmalı, bir kopyala-yapıştır değil.
        */
        self::assertStringNotContainsString(
            '--aep-',
            $css,
            'DAISY-THEME-01: kurumsal tema panelin marka jetonlarına (`--aep-*`) geri bağlanmış. '
            .'Sahibin 2026-09-08 kararı bunu kaldırdı (`docs/145` §1); kurumsal renk kaynağı '
            .'`site-identity.css`tir.'
        );
    }

    // --- DAISY-THEME-02 --------------------------------------------------------

    public function test_every_theme_colour_points_at_a_brand_token_that_really_exists(): void
    {
        $defined = $this->brandTokens();

        self::assertNotSame([], $defined, 'Marka jetonları okunamadı — ölçüm dayanaksız.');

        foreach ($this->themeBlocks() as $name => $body) {
            preg_match_all('#(--color-[a-z0-9-]+):\s*([^;]+);#', $body, $declarations, PREG_SET_ORDER);

            self::assertNotSame([], $declarations, "[{$name}] temasında hiç renk yok — ölçüm dayanaksız.");

            foreach ($declarations as [, $variable, $value]) {
                self::assertSame(
                    1,
                    preg_match('#^var\((--zc-[a-z0-9-]+)\)$#', trim($value), $reference),
                    "DAISY-THEME-02: [{$name}] temasında [{$variable}] bir kurumsal jetona işaret etmiyor: {$value}"
                );

                self::assertContains(
                    $reference[1],
                    $defined,
                    "DAISY-THEME-02: [{$name}] temasındaki [{$variable}], `site-identity.css` içinde TANIMSIZ "
                    ."olan [{$reference[1]}] jetonuna işaret ediyor — zincir kopuk."
                );
            }
        }
    }

    // --- DAISY-THEME-03 --------------------------------------------------------

    public function test_both_themes_declare_every_daisyui_variable(): void
    {
        /*
            Adı hazır bir temayla çakışan özel tema, daisyUI tarafından o hazır
            temanın ÜSTÜNE bindirilir (`node_modules/daisyui/theme/index.js`).
            Yani yazılmayan her değişken, sessizce daisyUI'nin kendi rengine
            düşer — depoya girmeyen, kimsenin göremediği ikinci bir kaynak.
        */
        $required = $this->themeVariables();
        $blocks = $this->themeBlocks();

        self::assertSame(['light', 'dark'], array_keys($blocks), 'Beklenen iki tema bulunamadı.');

        foreach ($blocks as $name => $body) {
            foreach ($required as $variable) {
                self::assertStringContainsString(
                    $variable.':',
                    $body,
                    "DAISY-THEME-03: [{$name}] teması [{$variable}] değişkenini yazmıyor; "
                    .'daisyUI o değeri kendi hazır temasından doldurur.'
                );
            }
        }
    }

    // --- DAISY-THEME-04 --------------------------------------------------------

    public function test_daisyui_builtin_themes_are_switched_off(): void
    {
        self::assertSame(
            1,
            preg_match('#@plugin\s+"daisyui"\s*\{(.*?)\}#s', $this->theme(), $options),
            'daisyUI eklentisi CSS\'ten yüklenmiyor — Tailwind v4 kurulumu `@plugin` ile yapılır.'
        );

        self::assertStringContainsString(
            'themes: false',
            $options[1],
            'DAISY-THEME-04: hazır temalar kapatılmamış. Açık bırakıldığında daisyUI KENDİ renklerini '
            .'`:root`a yazar ve marka jetonlarının yanında ikinci bir renk kaynağı doğar.'
        );

        self::assertStringContainsString(
            "prefix: 'dz-'",
            $options[1],
            'DAISY-THEME-04: `dz-` öneki kaldırılmış. Öneksiz daisyUI, panelin `flowbite-react` '
            .'sınıflarıyla aynı isim uzayını paylaşır.'
        );
    }

    // --- DAISY-THEME-05 --------------------------------------------------------

    public function test_the_theme_switch_uses_the_same_attribute_as_the_brand_tokens(): void
    {
        /*
            `partials/theme-bootstrap.blade.php` kök öğeye `data-theme` yazar ve
            `site-identity.css` koyu değerlerine `[data-theme="dark"]` ile
            geçer. daisyUI teması başka bir adla anılsaydı, ziyaretçi koyu
            temaya geçtiğinde kurumsal jetonlar koyulaşır, daisyUI açık kalırdı.
        */
        $bootstrap = (string) file_get_contents(
            resource_path('views/partials/theme-bootstrap.blade.php')
        );

        foreach (['light', 'dark'] as $name) {
            self::assertStringContainsString(
                "'".$name."'",
                $bootstrap,
                "DAISY-THEME-05: kabuk `data-theme=\"{$name}\"` yazmıyor; tema adları ayrışmış."
            );
        }

        self::assertSame(
            ['light', 'dark'],
            array_keys($this->themeBlocks()),
            'DAISY-THEME-05: daisyUI tema adları `data-theme` değerleriyle aynı olmalı.'
        );
    }

    // --- Yardımcılar -----------------------------------------------------------

    /**
     * `site-identity.css` içinde GERÇEKTEN tanımlı kurumsal jetonlar.
     *
     * Tek dosya, çünkü kurumsal kimliğin tek tanımı orada (`docs/145` §1).
     * Bir gün ikinci bir dosyaya bölünürse bu yardımcı da onunla birlikte
     * genişletilir — ama bölünme bir karar olarak görünür olur.
     *
     * @return list<string>
     */
    private function brandTokens(): array
    {
        $css = (string) file_get_contents(base_path(self::IDENTITY_FILE));

        preg_match_all('#(--zc-[a-z0-9-]+):#', $css, $names);

        return array_values(array_unique($names[1]));
    }
}
