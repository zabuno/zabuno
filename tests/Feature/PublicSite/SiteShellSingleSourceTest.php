<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * SHELL-SINGLE-SOURCE-01…04 — kurumsal sitenin TEK kabuğu.
 *
 * Sahibin talebi (2026-09-05): *"hepsi aynı masterpage shell'e bağlı olsun.
 * masterpage shell (header footer) tüm frontpages'da aynı olsun,
 * güncellendiğinde her yer güncellensin."*
 *
 * Ölçülen şey bir dosya düzeni değil, o cümlenin KARŞILIĞI: bir yerde
 * yapılan bir değişiklik her yerde görünüyor mu? Bunun tek kanıtı, ikinci
 * bir header/footer tanımının HİÇ olmamasıdır. `docs/100` §2 masterpage
 * sözleşmesini kurmuştu ama yalnız beş sayfayı kapsıyordu; kütükten çizilen
 * kurumsal sayfalar (`content/*`) kendi belgelerini kuruyordu — yani iki
 * kabuk vardı ve birini güncellemek diğerini güncellemiyordu.
 *
 * Kullanıcı yolculuğu: sahip üst çubuğa bir bağlantı ekler. Bugün o bağlantı
 * `/pricing`'de görünür ama `/tr/urun/qr-menu/` sayfasında görünmez; ziyaretçi
 * aynı sitenin iki farklı hâlini gezer. Bu testten sonra tek bir dosya
 * değişir ve iki adres de aynı çubuğu gösterir.
 */
final class SiteShellSingleSourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kurumsal kabuğun İZİNLİ tanım yerleri.
     *
     * Misafir menüsü (`public-menu.blade.php`) bilerek DIŞARIDA: orası
     * restoranın yüzeyi, burası ürünün yüzeyi (`docs/100` Kapsam). İki
     * yüzeyi tek kabuğa bağlamak, bir restoranın menüsüne Zabuno'nun
     * fiyat gezintisini koymak olurdu.
     */
    private const SHELL_FILES = [
        'public/partials/header.blade.php',
        'public/partials/footer.blade.php',
    ];

    /** Kurumsal yüzeyin dışındaki, kendi kabuğu olan görünümler. */
    private const OTHER_SURFACES = [
        'public-menu.blade.php',
    ];

    /** @return list<array{0:string}> */
    public static function livePaths(): array
    {
        return [['/'], ['/pricing'], ['/help'], ['/contact'], ['/terms'], ['/privacy'], ['/kvkk']];
    }

    // --- SHELL-SINGLE-SOURCE-01 ------------------------------------------------

    public function test_only_one_file_defines_the_corporate_header_and_footer(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $relative => $contents) {
            if (in_array($relative, self::SHELL_FILES, true)) {
                continue;
            }

            if (in_array($relative, self::OTHER_SURFACES, true)) {
                continue;
            }

            if (preg_match('#<(header|footer)[\s>]#', $contents) === 1) {
                $offenders[] = $relative;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'SHELL-SINGLE-SOURCE-01: kurumsal header/footer ikinci bir dosyada tanımlanmış: '
            .implode(', ', $offenders)
            .' — ikinci bir tanım, güncellemenin bir yerde unutulacağı yerdir.'
        );
    }

    // --- SHELL-SINGLE-SOURCE-02 ------------------------------------------------

    public function test_every_corporate_template_extends_the_single_shell(): void
    {
        $missing = [];

        foreach ($this->corporateTemplates() as $relative => $contents) {
            if (! str_contains($contents, "@extends('public.layout')")) {
                $missing[] = $relative;
            }
        }

        self::assertSame(
            [],
            $missing,
            'SHELL-SINGLE-SOURCE-02: şu kurumsal şablonlar kabuğa bağlı değil: '.implode(', ', $missing)
        );
    }

    // --- SHELL-SINGLE-SOURCE-03 ------------------------------------------------

    public function test_a_planned_registry_page_wears_the_same_shell_as_a_live_page(): void
    {
        $this->registryPage('/tr/urun/qr-menu/', PagePublicationStatus::Planned);

        /*
            Karşılaştırma AYNI DİLDE yapılır. `/tr/…` sayfasının dili
            adresindedir (`docs/118` E4) ve Türkçedir; `/pricing` tarayıcıyla
            pazarlık eder. İkisini farklı dillerde karşılaştırmak, doğru
            davranışı "farklı kabuk" diye bildirmek olurdu.

            Hazırlanıyor ekranı bir 404 GÖVDESİDİR (`docs/105` §8); gövdenin
            404 olması, ziyaretçinin çıkış yolu olmaması demek değildir.
        */
        self::assertSame(
            $this->chrome($this->body('/pricing', 200, ['Accept-Language' => 'tr'])),
            $this->chrome($this->body('/tr/urun/qr-menu/', 404)),
            'SHELL-SINGLE-SOURCE-03: kütükten çizilen sayfa başka bir kabuk giyiyor.'
        );
    }

    public function test_a_published_registry_page_wears_the_same_shell_as_a_live_page(): void
    {
        $this->registryPage('/tr/urun/menu-yonetimi/', PagePublicationStatus::Published);

        self::assertSame(
            $this->chrome($this->body('/pricing', 200, ['Accept-Language' => 'tr'])),
            $this->chrome($this->body('/tr/urun/menu-yonetimi/')),
            'SHELL-SINGLE-SOURCE-03: yayınlanmış kurumsal sayfa başka bir kabuk giyiyor.'
        );
    }

    // --- SHELL-SINGLE-SOURCE-04 ------------------------------------------------

    public function test_the_shell_still_works_when_no_javascript_runs(): void
    {
        /*
            Kabuk ve gezinti JavaScript'e BAĞLI DEĞİLDİR. Ölçüm: betik
            gövdelerini attıktan sonra bile her gezinti bağlantısı HTML'de
            duruyor mu? Bir menüyü yalnız betik açıyorsa, betik
            çalışmadığında site gezilemez hâle gelir — ve kurumsal sitenin
            en çok ziyaret edildiği an, betiklerin en çok engellendiği andır.
        */
        $html = $this->body('/pricing');
        $withoutScripts = preg_replace('#<script\b.*?</script>#s', '', $html) ?? '';

        foreach (['/pricing', '/help', '/contact', '/terms', '/privacy', '/kvkk'] as $target) {
            self::assertStringContainsString(
                'href="'.$target.'"',
                $this->chrome($withoutScripts),
                "SHELL-SINGLE-SOURCE-04: [{$target}] bağlantısı betiksiz gövdede yok."
            );
        }
    }

    // --- Yardımcılar -----------------------------------------------------------

    private function registryPage(string $path, PagePublicationStatus $status): ContentPage
    {
        return ContentPage::query()->create([
            'page_key' => trim(str_replace('/', '.', $path), '.'),
            'locale' => 'tr',
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'QR Menü',
            'priority' => 'P0',
            'publication_status' => $status->value,
            'was_ever_published' => $status === PagePublicationStatus::Published,
        ]);
    }

    /** @param  array<string, string>  $headers */
    private function body(string $path, int $expected = 200, array $headers = []): string
    {
        return (string) $this->withHeaders($headers)->get($path)->assertStatus($expected)->getContent();
    }

    /**
     * Sayfanın KABUĞU — üst ve alt çubuk, çıpa öneki normalize edilmiş.
     *
     * Çıpa öneki sayfaya göre değişmek ZORUNDA (`/#features` ile `#features`
     * aynı hedefe gider ama farklı sayfalardan); onu farklılık saymak, doğru
     * davranışı kusur diye bildirmek olurdu.
     */
    private function chrome(string $html): string
    {
        $chrome = '';

        foreach (['header', 'footer'] as $tag) {
            preg_match("#<{$tag}\b.*?</{$tag}>#s", $html, $match);
            $chrome .= $match[0] ?? "[{$tag} YOK]";
        }

        return (string) preg_replace('#href="/?\#[a-z-]+"#', 'href="ANCHOR"', $chrome);
    }

    /** @return array<string, string> göreli yol → içerik */
    private function bladeFiles(): array
    {
        $root = resource_path('views');
        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $files[str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = (string) file_get_contents($file->getPathname());
        }

        return $files;
    }

    /**
     * Kurumsal SAYFA şablonları — kabuğun kendisi ve parçaları hariç.
     *
     * @return array<string, string>
     */
    private function corporateTemplates(): array
    {
        $templates = [];

        foreach ($this->bladeFiles() as $relative => $contents) {
            $isCorporate = (str_starts_with($relative, 'public/') || str_starts_with($relative, 'content/'))
                && ! str_contains($relative, '/partials/')
                && $relative !== 'public/layout.blade.php';

            if ($isCorporate) {
                $templates[$relative] = $contents;
            }
        }

        self::assertNotSame([], $templates, 'Kurumsal şablon bulunamadı — tarama yanlış yerde.');

        return $templates;
    }
}
