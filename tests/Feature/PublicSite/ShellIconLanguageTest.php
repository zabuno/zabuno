<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * ICON-01…04 — kurumsal sitenin İKON DİLİ: emoji yasak, Phosphor ilk.
 *
 * ── Düzeltilmiş karar ────────────────────────────────────────────────────
 *
 * `docs/118` E6 "kurumsal sitede ikon kullanılmayacaktır" diyordu; kaynağı
 * `docs/119` §1 madde 10'du ve o madde bir GİRDİYDİ, bir karar değil. Sahip
 * 2026-09-08'de düzeltti: *"hayır, Phosphor icon var, emoji yok. Kararı
 * güncelle."*
 *
 * Kural artık iki yüzey için de aynı ve bu, ikisinin "aynı ürün" gibi
 * görünmesinin ikinci ayağı: panel `@phosphor-icons/react` kullanıyor,
 * kurumsal site React YÜKLEMEZ ve aynı aileye sunucu tarafından erişir.
 *
 * ── Neden yol verisi ölçülüyor ───────────────────────────────────────────
 *
 * Bir ikon ailesinin bütün değeri tutarlılığındadır: aynı çizgi kalınlığı,
 * aynı optik ölçü, aynı köşe. Gözle çizilmiş "benzeri" bir SVG o değeri
 * sessizce yok eder ve kimse fark etmez — çünkü ekranda hâlâ bir ikon vardır.
 * Bu yüzden her `d` dizesi paketin kendi tanımıyla karşılaştırılır.
 */
final class ShellIconLanguageTest extends TestCase
{
    /** Bileşendeki ad → Phosphor paketindeki tanım dosyası. */
    private const ICONS = [
        'list' => 'List',
        'x' => 'X',
        'caret-down' => 'CaretDown',
    ];

    /** Kurumsal kabuğun BÜTÜN yüzeyi — hem HTML hem görünüm. */
    private const SHELL_FILES = [
        'views/public/layout.blade.php',
        'views/public/partials/header.blade.php',
        'views/public/partials/footer.blade.php',
        'views/public/partials/consent-banner.blade.php',
        'views/components/phosphor.blade.php',
        'css/site-shell.css',
        'css/daisy-theme.css',
        /* Kurumsal görsel kimlik (`docs/145`). Bir CSS dosyasına emoji
           yazmak tuhaf görünür ama `content: '…'` tam olarak onu yapar ve
           kurala bir istisna açmamak için liste eksiksiz tutulur. */
        'css/site-identity.css',
    ];

    // --- ICON-01 ---------------------------------------------------------------

    public function test_the_shell_carries_no_emoji_anywhere(): void
    {
        /*
            EMOJİ BİR İKON DEĞİLDİR. İşletim sistemine göre başka çizilir,
            ekran okuyucuda uzun bir cümle olarak okunur ("çok yönlü anahtar"),
            yazı tipi yığınına bağlıdır ve marka jetonlarından hiç geçmez.
            Sahibin kuralı bu yüzden mutlak.
        */
        $offenders = [];

        foreach (self::SHELL_FILES as $relative) {
            $contents = (string) file_get_contents(resource_path($relative));

            // Emoji blokları: piktogramlar, taşıma/harita, ek semboller,
            // muhtelif semboller, dingbat'lar ve varyasyon seçicisi.
            if (preg_match('#[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}]#u', $contents) === 1) {
                $offenders[] = $relative;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'ICON-01: kurumsal kabukta emoji var: '.implode(', ', $offenders)
            .' — emoji yasak, Phosphor ilk (`docs/118` E6).'
        );
    }

    // --- ICON-02 ---------------------------------------------------------------

    public function test_every_icon_path_is_copied_verbatim_from_the_phosphor_package(): void
    {
        $component = (string) file_get_contents(resource_path('views/components/phosphor.blade.php'));

        preg_match_all("#'([a-z-]+)' => '([^']+)',#", $component, $matches, PREG_SET_ORDER);

        $declared = [];

        foreach ($matches as [, $name, $path]) {
            $declared[$name] = $path;
        }

        self::assertSame(
            array_keys(self::ICONS),
            array_keys($declared),
            'ICON-02: bileşendeki ikon kümesi bu testin bildiği kümeyle aynı değil.'
        );

        foreach (self::ICONS as $name => $file) {
            $source = base_path("node_modules/@phosphor-icons/react/dist/defs/{$file}.es.js");

            self::assertFileExists(
                $source,
                "Phosphor paketi kurulu değil; ikon dili ÖLÇÜLEMEDİ ({$file}). "
                .'Ölçülemeyen bir sonuç yeşil gösterilmez — `npm ci` çalıştırın.'
            );

            self::assertStringContainsString(
                $declared[$name],
                (string) file_get_contents($source),
                "ICON-02: [{$name}] ikonunun yol verisi `@phosphor-icons/react` paketindeki "
                ."[{$file}] tanımında BULUNAMADI — elle çizilmiş ya da düzeltilmiş bir kopya, "
                .'ailenin tutarlılığını sessizce bozar.'
            );
        }
    }

    // --- ICON-03 ---------------------------------------------------------------

    public function test_an_icon_never_speaks_twice_to_a_screen_reader(): void
    {
        $header = (string) $this->get('/pricing')->assertOk()->getContent();

        preg_match_all('#<svg\b[^>]*>#', $header, $svgs);

        self::assertNotSame([], $svgs[0], 'Kabukta hiç ikon yok — ölçüm dayanaksız.');

        foreach ($svgs[0] as $svg) {
            /*
                Kabuktaki her ikonun YANINDA gerçek bir sözcük durur; ikonu
                ayrıca okutmak, ekran okuyucuya aynı şeyi iki kez söyletmektir.
                Tek başına duran bir ikon `role="img"` + `aria-label` alır —
                ama kabukta öyle bir ikon YOKTUR.
            */
            self::assertStringContainsString(
                'aria-hidden="true"',
                $svg,
                "ICON-03: yanında sözcük olan bir ikon ekran okuyucuya ayrıca okunuyor: {$svg}"
            );
            self::assertStringContainsString('focusable="false"', $svg, 'ICON-03: ikon klavye sırasına giriyor.');
        }
    }

    // --- ICON-04 ---------------------------------------------------------------

    public function test_no_corporate_template_hand_draws_an_icon(): void
    {
        /*
            Tek kaynak kuralı: SVG yalnız ikon bileşeninde yaşar. Bir şablonun
            kendi `<svg>`ini çizmesi, ailenin dışına çıkan ilk adımdır ve
            ikinci adımı kimse fark etmez.

            Misafir menüsü (`public-menu*.blade.php`) bilerek DIŞARIDA: orası
            restoranın yüzeyi, burası ürünün yüzeyi (`docs/100` Kapsam).
        */
        $offenders = [];

        foreach ($this->corporateViews() as $relative => $contents) {
            if ($relative === 'components/phosphor.blade.php') {
                continue;
            }

            if (str_contains($contents, '<svg')) {
                $offenders[] = $relative;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'ICON-04: kurumsal şablon kendi SVG\'sini çiziyor: '.implode(', ', $offenders)
            .' — ikon yalnız `<x-phosphor>` üzerinden gelir.'
        );
    }

    /** @return array<string, string> */
    private function corporateViews(): array
    {
        $root = resource_path('views');
        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname())
            );

            $isCorporate = str_starts_with($relative, 'public/')
                || str_starts_with($relative, 'content/')
                || str_starts_with($relative, 'components/');

            if ($isCorporate) {
                $files[$relative] = (string) file_get_contents($file->getPathname());
            }
        }

        self::assertNotSame([], $files, 'Kurumsal görünüm bulunamadı — tarama yanlış yerde.');

        return $files;
    }
}
