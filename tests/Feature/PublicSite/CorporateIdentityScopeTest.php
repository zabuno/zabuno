<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use Tests\Support\CorporateIdentityTokens;
use Tests\TestCase;

/**
 * KIMLIK-04…07 — kurumsal kimlik KURUMSAL SİTEDE KALIR, panele sızmaz.
 *
 * ── Sahibin sınırı ───────────────────────────────────────────────────────
 *
 * *"Panelin tek pikseline dokunmayacaksın."* Panel `flowbite-react` + AEP
 * jetonları üzerine kurulu ve kendi estetik olgunluk belgesi var
 * (`docs/102`). Kurumsal siteye kendi paletini vermek, panelin yeniden
 * yazılması DEĞİLDİR.
 *
 * ── Neden bu kapı bir yorum satırından daha güçlü ─────────────────────────
 *
 * "Dokunmadım" bir iddiadır. Bu dosya onu ÖLÇÜYE çevirir ve ölçünün üç
 * ayağı var:
 *
 *   1. Kurumsal palet yalnız `.site-shell` kapsamında yaşar ve o sınıf
 *      panelin gövdesinde yoktur (KIMLIK-05).
 *   2. Panelin kaynakları kurumsal jetonların ve `dz-` sınıflarının adını
 *      bile anmaz (KIMLIK-06).
 *   3. Panelin kendi zinciri (`--fg` → `--aep-*`) `:root` seviyesinde
 *      olduğu gibi durur (KIMLIK-07).
 *
 * Üçü birden geçmedikçe "panel etkilenmedi" cümlesi kurulamaz.
 *
 * ── Kullanıcı yolculuğu ──────────────────────────────────────────────────
 *
 * Restoran sahibi sabah paneli açıyor; menüsünü düzenliyor. Öğlen kendi
 * sitesinin tanıtım sayfasını arkadaşına gösteriyor. İki ekran AYNI ürüne
 * ait ama aynı işi yapmıyor: biri sekiz saatlik bir çalışma tezgâhı, öteki
 * bir vitrin. Bu kapı, vitrini boyarken tezgâhın rengini değiştirmediğimizi
 * kanıtlar.
 */
final class CorporateIdentityScopeTest extends TestCase
{
    private const IDENTITY_FILE = 'resources/css/site-identity.css';

    private const APP_CSS = 'resources/css/app.css';

    // --- KIMLIK-04 -------------------------------------------------------------

    public function test_every_rebound_alias_also_rebinds_its_tailwind_twin(): void
    {
        /*
            İKİ AD, TEK ROL — ve ikisi de yazılmak ZORUNDA.

            Depoda her renk rolünün iki adı var: düz CSS'in kullandığı
            `--surface` ve Tailwind utility'lerinin kullandığı
            `--color-surface`. İkincisi `@theme` içinde `var(--surface)` diye
            tanımlı ve bir `var()` içeren özel özellik TANIMLANDIĞI YERDE
            ikame edilir (bu deponun kendi ölçümü, `app.css` yoğunluk bloğu).

            Sonuç: yalnız `--surface`i yeniden bağlamak, `bg-surface` sınıfını
            DEĞİŞTİRMEZ. Sayfanın bir yarısı yeni palete, öteki yarısı
            eskisine bakar — ve hiçbir test kırmızıya dönmez, çünkü ikisi de
            kendi içinde tutarlıdır.

            Tam olarak bu yüzden bu kapı var. Eşleşme listesi ELLE
            yazılmıyor: `app.css`ten okunuyor, yani yarın yeni bir
            `--color-*` çifti eklenirse kapı onu da ister.
        */
        $appCss = CorporateIdentityTokens::withoutComments(
            (string) file_get_contents(base_path(self::APP_CSS))
        );

        preg_match_all(
            '#(--color-[a-z0-9-]+)\s*:\s*var\((--[a-z0-9-]+)\)\s*;#',
            $appCss,
            $matches,
            PREG_SET_ORDER,
        );

        self::assertNotSame([], $matches, 'Tailwind köprü değişkenleri okunamadı — ölçüm dayanaksız.');

        $shell = $this->shellBlock();
        $missing = [];

        foreach ($matches as [, $twin, $base]) {
            /*
                Zincirin İÇ halkaları atlanır: `--color-action` değeri
                `var(--color-action-primary-bg)`dir ve o da bir `--color-*`
                adıdır. Aranan şey, DÜZ CSS adının kurumsal kapsamda
                yeniden bağlanıp bağlanmadığı.
            */
            if (str_starts_with($base, '--color-') || str_starts_with($base, '--aep-')) {
                continue;
            }

            if (! $this->declares($shell, $base)) {
                continue;
            }

            if (! $this->declares($shell, $twin)) {
                $missing[] = "{$base} yeniden bağlandı ama {$twin} bağlanmadı";
            }
        }

        self::assertSame(
            [],
            $missing,
            "KIMLIK-04: kurumsal kapsamda bir rolün yalnız BİR adı yeniden bağlanmış:\n  · "
            .implode("\n  · ", $missing)
            ."\nDüz CSS yeni paleti okur, Tailwind sınıfı eskisini — sayfa ikiye bölünür."
        );
    }

    // --- KIMLIK-05 -------------------------------------------------------------

    public function test_the_corporate_palette_is_scoped_to_the_public_shell_only(): void
    {
        /*
            KAPSAM SEÇİCİSİ SÖZLE DEĞİL, KAYNAKTA ÖLÇÜLÜR.

            Kimlik dosyasındaki her kural ya `:root`/`[data-theme]`
            seviyesinde YENİ bir ad tanımlar (`--zc-*`), ya da `.site-shell`
            kapsamında var olan bir adı yeniden bağlar. Üçüncü bir olasılık
            — `:root` seviyesinde var olan bir adı ezmek — panelin rengini
            değiştirirdi.
        */
        $css = CorporateIdentityTokens::withoutComments(
            (string) file_get_contents(base_path(self::IDENTITY_FILE))
        );

        preg_match_all('#^(:root|\[data-theme=\'dark\'\])\s*\{(.*?)^\}#ms', $css, $blocks, PREG_SET_ORDER);

        self::assertNotSame([], $blocks, 'Kimlik dosyasının kök blokları okunamadı.');

        foreach ($blocks as [, $selector, $body]) {
            preg_match_all('#(--[a-z0-9-]+)\s*:#', $body, $names);

            foreach ($names[1] as $name) {
                self::assertStringStartsWith(
                    '--zc-',
                    $name,
                    "KIMLIK-05: kimlik dosyası [{$selector}] seviyesinde [{$name}] tanımlıyor. "
                    .'Kök seviyede YALNIZ yeni `--zc-*` adları doğar; var olan bir jetonu orada '
                    .'yeniden yazmak paneli de değiştirirdi.'
                );
            }
        }

        /*
            VE `.site-shell` GERÇEKTEN YALNIZ KURUMSAL KABUKTA.

            Sınıfın kendisi bir yerde daha yazılsaydı — panelin gövdesinde,
            misafir menüsünde — kapsam iddiası çökerdi.
        */
        $carriers = [];

        foreach ($this->sourceFiles(['resources/views', 'resources/js']) as $path) {
            $contents = (string) file_get_contents($path);

            /*
                `site-shell-inner` bir KAPSAM DEĞİL, kabuğun içindeki bir
                kolon. Sınır `-` karakterinde çizilmezse üç kabuk parçası
                yanlışlıkla ihlal sayılırdı ve kapı ilk günden gürültü
                üretirdi.
            */
            if (preg_match('#class="[^"]*(?<![\w-])site-shell(?![\w-])#', $contents) === 1
                || preg_match('#[\'"]site-shell(?![\w-])#', $contents) === 1) {
                $carriers[] = str_replace(base_path().'/', '', $path);
            }
        }

        self::assertSame(
            ['resources/views/public/layout.blade.php'],
            $carriers,
            'KIMLIK-05: `site-shell` sınıfı kurumsal kabuk dışında da yazılmış: '
            .implode(', ', $carriers).' — kurumsal palet oraya da sızar.'
        );
    }

    // --- KIMLIK-06 -------------------------------------------------------------

    public function test_the_panel_never_mentions_the_corporate_identity(): void
    {
        /*
            PANELİN KAYNAĞI KURUMSAL KİMLİĞİN ADINI BİLE ANMAZ.

            İki sızıntı yolu var ve ikisi de sessizdir:

              · bir React bileşeninin `var(--zc-…)` yazması — o an panel
                kurumsal palete bağlanır ve kimse fark etmez, çünkü ekranda
                bir renk vardır;
              · panelde `dz-` önekli bir daisyUI sınıfı — o an panel kurumsal
                bileşen kütüphanesini giyer.

            Not: `aep/tokens/` YUKARI DOĞRU da temiz olmalı. Kurumsal bir
            jetonun panel jeton dosyasına yazılması, iki kimliği tek dosyada
            birleştirirdi.
        */
        $offenders = [];

        foreach ($this->sourceFiles(['resources/js', 'resources/css/aep']) as $path) {
            $contents = (string) file_get_contents($path);
            $relative = str_replace(base_path().'/', '', $path);

            if (str_contains($contents, '--zc-')) {
                $offenders[] = "{$relative} (kurumsal jeton)";
            }

            if (preg_match('#[\'"\s]dz-[a-z]#', $contents) === 1) {
                $offenders[] = "{$relative} (daisyUI sınıfı)";
            }
        }

        self::assertSame(
            [],
            $offenders,
            'KIMLIK-06: panelin kaynağı kurumsal kimliğe bağlanmış: '.implode(', ', $offenders)
            .' — sahibin sınırı: panelin tek pikseline dokunulmaz (`docs/145` §5).'
        );
    }

    // --- KIMLIK-07 -------------------------------------------------------------

    public function test_the_panel_token_chain_is_untouched_at_the_root(): void
    {
        /*
            PANELİN ZİNCİRİ YERİNDE.

            Kurumsal kapsam `.site-shell` içinde `--fg`i kurumsal mürekkebe
            bağlıyor. Aynı adın `:root` seviyesindeki tanımı DEĞİŞMEMİŞ
            olmalı — panel o tanımı okur.

            Bu kapı, kimlik paketinin en kolay yapabileceği hatayı yakalar:
            "nasılsa hepsi aynı renk olacak" diyerek `:root`taki satırı
            değiştirmek. O satır değişirse panel de değişir ve sahibin
            sınırı sessizce aşılmış olur.
        */
        $appCss = CorporateIdentityTokens::withoutComments(
            (string) file_get_contents(base_path(self::APP_CSS))
        );

        $rootChain = [
            '--fg' => '--aep-text-primary',
            '--fg-secondary' => '--aep-text-secondary',
            '--canvas' => '--aep-surface-canvas',
            '--surface' => '--aep-surface-raised',
            '--surface-subtle' => '--aep-surface-sunken',
            '--border' => '--aep-border',
            '--focus' => '--aep-focus-ring',
            '--color-brand-500' => '--aep-yellow-500',
        ];

        foreach ($rootChain as $alias => $token) {
            self::assertMatchesRegularExpression(
                '#'.preg_quote($alias, '#').'\s*:\s*var\('.preg_quote($token, '#').'\)\s*;#',
                $appCss,
                "KIMLIK-07: panelin kök zinciri kopmuş — [{$alias}] artık [{$token}] jetonunu "
                .'okumuyor. Kurumsal kimlik `.site-shell` kapsamında yaşar; kökte yaşamaz.'
            );
        }
    }

    // --- Yardımcılar -----------------------------------------------------------

    /** Kimlik dosyasındaki `.site-shell { … }` bloğunun gövdesi. */
    private function shellBlock(): string
    {
        $css = CorporateIdentityTokens::withoutComments(
            (string) file_get_contents(base_path(self::IDENTITY_FILE))
        );

        self::assertSame(
            1,
            preg_match('#^\.site-shell\s*\{(.*?)^\}#ms', $css, $match),
            'Kurumsal kapsam bloğu (`.site-shell`) bulunamadı — ölçüm dayanaksız.'
        );

        return $match[1];
    }

    private function declares(string $block, string $name): bool
    {
        return preg_match('#'.preg_quote($name, '#').'\s*:#', $block) === 1;
    }

    /**
     * Taranan kaynak dosyalar.
     *
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function sourceFiles(array $roots): array
    {
        $files = [];

        foreach ($roots as $root) {
            $directory = base_path($root);

            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                if (! in_array($file->getExtension(), ['php', 'ts', 'tsx', 'js', 'jsx', 'css'], true)) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
