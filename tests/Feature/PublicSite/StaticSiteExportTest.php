<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * STATIC-EXPORT-01…05 — kurumsal sayfaların statik HTML önizlemesi.
 *
 * Sahibin talebi (2026-09-05): *"paralel olarak statik html dosyaları
 * yaratalım."* Amaç dağıtım DEĞİL: sahip kod bilmiyor, sunucu çalıştırmak
 * istemiyor ve tasarımı görmek için tek yapmak istediği şey bir dosyaya çift
 * tıklamak. Bu yüzden çıktı `file://` altında AÇILABİLİR olmak zorunda —
 * yoksa üretilen şey bir HTML yığınıdır, bir önizleme değil.
 *
 * Çıktı depoya girmez (`.gitignore`): dağıtılan bir kopya, bir gün asıl
 * siteden ayrışır ve hangisinin doğru olduğu bilinmez.
 */
final class StaticSiteExportTest extends TestCase
{
    use RefreshDatabase;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        $this->out = storage_path('framework/testing/site-export-'.bin2hex(random_bytes(4)));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->out);

        parent::tearDown();
    }

    private function export(): void
    {
        $this->artisan('site:export-static', ['--out' => $this->out])->assertSuccessful();
    }

    private function read(string $relative): string
    {
        $file = $this->out.'/'.$relative;

        self::assertFileExists($file, "STATIC-EXPORT-01: [{$relative}] üretilmedi.");

        return (string) file_get_contents($file);
    }

    // --- STATIC-EXPORT-01 ------------------------------------------------------

    public function test_every_live_corporate_page_becomes_a_file_the_owner_can_open(): void
    {
        $this->export();

        foreach (['index.html', 'pricing/index.html', 'help/index.html', 'contact/index.html',
            'terms/index.html', 'privacy/index.html', 'kvkk/index.html'] as $relative) {
            self::assertStringContainsString('<html', $this->read($relative));
        }
    }

    // --- STATIC-EXPORT-02 ------------------------------------------------------

    public function test_every_exported_file_wears_the_same_shell(): void
    {
        $this->export();

        $reference = null;

        foreach (['index.html', 'pricing/index.html', 'help/index.html', 'kvkk/index.html'] as $relative) {
            $chrome = $this->chrome($this->read($relative));

            self::assertNotSame('', $chrome, "STATIC-EXPORT-02: [{$relative}] kabuksuz üretilmiş.");

            $reference ??= $chrome;

            self::assertSame(
                $reference,
                $chrome,
                "STATIC-EXPORT-02: [{$relative}] statik çıktıda farklı bir kabuk taşıyor."
            );
        }
    }

    // --- STATIC-EXPORT-03 ------------------------------------------------------

    public function test_links_and_styles_resolve_on_disk_so_the_preview_is_actually_browsable(): void
    {
        $this->export();

        $html = $this->read('pricing/index.html');

        /*
            `href="/help"` bir `file://` belgesinde diskin köküne bakar ve
            hiçbir zaman açılmaz. Önizlemenin tek işi gezilebilmek olduğu
            için bağlantılar GÖRELİ dosya yollarına çevrilir.
        */
        preg_match_all('~(?:href|src)="([^"#:]+)"~', $html, $matches);

        $checked = 0;

        foreach ($matches[1] as $reference) {
            if ($reference === '' || str_starts_with($reference, '//') || str_starts_with($reference, 'data:')) {
                continue;
            }

            self::assertStringStartsNotWith(
                '/',
                $reference,
                "STATIC-EXPORT-03: [{$reference}] mutlak yol — çift tıklanan bir dosyadan açılmaz."
            );

            $target = realpath(dirname($this->out.'/pricing/index.html').'/'.$reference);

            self::assertNotFalse(
                $target,
                "STATIC-EXPORT-03: [{$reference}] diskte yok — önizlemede kırık bağlantı."
            );

            $checked++;
        }

        self::assertGreaterThan(0, $checked, 'Hiç bağlantı ölçülmedi — ölçüm dayanaksız.');
    }

    // --- STATIC-EXPORT-04 ------------------------------------------------------

    public function test_only_pages_that_really_render_are_exported(): void
    {
        $this->registryPage('/tr/urun/', PagePublicationStatus::Planned);
        $this->registryPage('/tr/cozumler/', PagePublicationStatus::Published);

        $this->export();

        self::assertFileExists(
            $this->out.'/tr/cozumler/index.html',
            'STATIC-EXPORT-04: yayınlanmış kurumsal sayfa önizlemede yok.'
        );
        self::assertFileDoesNotExist(
            $this->out.'/tr/urun/index.html',
            'STATIC-EXPORT-04: yayınlanmamış sayfa önizlemeye girmiş — 404 gövdesi bir sayfa değildir.'
        );
    }

    // --- STATIC-EXPORT-05 ------------------------------------------------------

    public function test_the_default_output_directory_is_never_committed(): void
    {
        $ignore = (string) file_get_contents(base_path('.gitignore'));

        self::assertStringContainsString(
            '/storage/app/site-preview/',
            $ignore,
            'STATIC-EXPORT-05: önizleme çıktısı depoya girebiliyor. Dağıtılan bir kopya bir gün asıl siteden ayrışır.'
        );
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
            'title' => 'Genel bakış',
            'priority' => 'P0',
            'publication_status' => $status->value,
            'was_ever_published' => $status === PagePublicationStatus::Published,
        ]);
    }

    private function chrome(string $html): string
    {
        $chrome = '';

        foreach (['header', 'footer'] as $tag) {
            preg_match("#<{$tag}\b.*?</{$tag}>#s", $html, $match);
            $chrome .= $match[0] ?? '';
        }

        // Çıpa öneki ve göreli derinlik sayfaya göre değişmek ZORUNDA:
        // `/help` altındaki bir dosyanın köke çıkması için `../` gerekir,
        // kökteki dosyanın gerekmez. İkisini farklılık saymak, doğru
        // davranışı kusur diye bildirmek olurdu.
        $chrome = (string) preg_replace('~href="[^"]*#[a-z-]+"~', 'href="ANCHOR"', $chrome);

        return (string) preg_replace('~(?:\.\./)+~', '', $chrome);
    }
}
