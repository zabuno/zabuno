<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Application\Platform\Port\HostCapabilityProbePort;
use App\Application\Platform\UseCase\RecordHostCapabilityEvidence;
use App\Infrastructure\Platform\Capability\RuntimeHostCapabilityProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `docs/16` MED-01 — paylaşımlı barındırma yetenek probu.
 *
 * `skills/shared-host-capability.md` üç şey şart koşar: prob salt-okunurdur
 * ve iz bırakmaz, eksik yetenek hard-fail ÜRETMEZ (planlı düşüş devreye
 * girer), ve her yeteneğin çıktısı kanıt olarak saklanır.
 *
 * Requirement ID'leri: MED-01-PROBE-01, MED-01-NO-TRACE-02,
 * MED-01-NO-HARD-FAIL-03, MED-01-DEGRADATION-04, MED-01-EVIDENCE-05.
 */
final class HostCapabilityProbeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, bool|string> */
    private function capabilities(array $overrides = []): array
    {
        return array_merge([
            'php_version' => '8.3.0',
            'imagick' => true,
            'gd' => true,
            'sqlite' => true,
            'redis' => true,
            'ffmpeg' => true,
            'exec_enabled' => true,
            'symlink_supported' => true,
            'php_memory_limit' => '512M',
            'upload_max_filesize' => '32M',
            'post_max_size' => '32M',
            'execution_timeout' => '60',
            'malware_scanner_driver' => 'clamav',
            'malware_scanner_binary_usable' => true,
        ], $overrides);
    }

    // --- MED-01-PROBE-01 --------------------------------------------------

    public function test_the_probe_reports_every_capability_the_product_depends_on(): void
    {
        $capabilities = (new RuntimeHostCapabilityProbe)->probe();

        foreach ([
            'php_version', 'imagick', 'gd', 'sqlite', 'redis', 'ffmpeg',
            'exec_enabled', 'symlink_supported', 'php_memory_limit',
            'upload_max_filesize', 'post_max_size', 'execution_timeout',
            'malware_scanner_driver', 'malware_scanner_binary_usable',
        ] as $key) {
            self::assertArrayHasKey($key, $capabilities, "MED-01-PROBE-01: `{$key}` ölçülmüyor.");
        }
    }

    public function test_the_probe_measures_rather_than_assumes(): void
    {
        $capabilities = (new RuntimeHostCapabilityProbe)->probe();

        // Ölçüm gerçekten çalışan sürece sorulmalı; sabit bir cevap değil.
        self::assertSame(PHP_VERSION, $capabilities['php_version']);
        self::assertSame(extension_loaded('gd'), $capabilities['gd']);
        self::assertSame((string) ini_get('memory_limit'), $capabilities['php_memory_limit']);
    }

    // --- MED-01-NO-TRACE-02 -----------------------------------------------

    public function test_the_probe_leaves_nothing_behind_on_the_host(): void
    {
        $before = glob(sys_get_temp_dir().'/zabuno-probe-*') ?: [];

        (new RuntimeHostCapabilityProbe)->probe();

        $after = glob(sys_get_temp_dir().'/zabuno-probe-*') ?: [];

        self::assertSame(
            $before,
            $after,
            'MED-01-NO-TRACE-02: prob test dosyası bırakırsa host kirlenir ve bir sonraki ölçüm yanlış olur.'
        );
    }

    // --- MED-01-NO-HARD-FAIL-03 -------------------------------------------

    // --- MED-01-SCANNER-EVIDENCE-04 ---------------------------------------

    /**
     * TARAYICI SESSİZCE ÖLÜR; KANIT BUNU GÖRMELİ.
     *
     * `ClamavMalwareScanner` taramaya başlamadan önce üç şeye bakar: yol boş
     * mu, dosya var mı, çalıştırılabilir mi. Üçünden biri tutmazsa taramayı
     * hiç denemez ve "belirsiz" döner — dosya bekler, hata basılmaz, log
     * yazılmaz. Ekranda görünen sonuç, tarayıcının HİÇ KURULMAMIŞ hâlinden
     * ayırt edilemez.
     *
     * Bu kanıt komutunun bütün varlık sebebi tam olarak bu tür sessiz
     * arızalardır: *"bizim sunucuda çalışıyordu"* bir kanıt değildir. Sürücü
     * `clamav`a çevrildiği hâlde ikili yerinde değilse, operatör bunu
     * dosyaların takıldığını fark ederek DEĞİL, bu raporu okuyarak
     * öğrenmelidir.
     *
     * `exec` kapalıyken de ayrı bir satır yazılır ve ikisi karıştırılmaz:
     * biri "çağıramıyoruz", diğeri "çağıracak bir şey yok". Aynı sonucu
     * doğuran iki farklı sebebi tek cümleye indirmek, yanlış yerde düzeltme
     * aratırdı.
     */
    public function test_a_configured_scanner_with_an_unusable_binary_is_named_in_the_evidence(): void
    {
        $degradations = RecordHostCapabilityEvidence::degradationsFor($this->capabilities([
            'malware_scanner_driver' => 'clamav',
            'malware_scanner_binary_usable' => false,
        ]));

        self::assertNotEmpty(
            array_filter($degradations, static fn (string $line): bool => str_contains($line, 'malware-scan:binary-unusable')),
            'MED-01-SCANNER-EVIDENCE-04: sürücü clamav iken çalışmayan ikili raporda adıyla geçmeli.'
        );
    }

    public function test_a_scanner_that_was_never_switched_on_is_named_as_such(): void
    {
        $degradations = RecordHostCapabilityEvidence::degradationsFor($this->capabilities([
            'malware_scanner_driver' => 'unavailable',
            'malware_scanner_binary_usable' => false,
        ]));

        self::assertNotEmpty(
            array_filter($degradations, static fn (string $line): bool => str_contains($line, 'malware-scan:not-configured')),
            'MED-01-SCANNER-EVIDENCE-04: sürücü hiç açılmamışsa bu ayrı bir satır olmalı.'
        );

        // Açılmamış bir sürücü için "ikili çalışmıyor" demek YANLIŞ YERE
        // baktırırdı: ortada aranacak bir ikili yok.
        self::assertEmpty(
            array_filter($degradations, static fn (string $line): bool => str_contains($line, 'malware-scan:binary-unusable')),
            'MED-01-SCANNER-EVIDENCE-04: iki farklı sebep tek satıra indirilmemeli.'
        );
    }

    public function test_a_working_scanner_produces_no_scanner_degradation(): void
    {
        $degradations = RecordHostCapabilityEvidence::degradationsFor($this->capabilities());

        self::assertEmpty(
            array_filter($degradations, static fn (string $line): bool => str_contains($line, 'malware-scan:')),
            'MED-01-SCANNER-EVIDENCE-04: çalışan tarayıcı için düşüş satırı yazılmamalı.'
        );
    }

    public function test_a_host_missing_everything_still_records_evidence_and_succeeds(): void
    {
        $this->app->instance(HostCapabilityProbePort::class, new class implements HostCapabilityProbePort
        {
            /** @return array<string, bool|string> */
            public function probe(): array
            {
                return [
                    'php_version' => '8.3.0', 'imagick' => false, 'gd' => false, 'sqlite' => true,
                    'redis' => false, 'ffmpeg' => false, 'exec_enabled' => false,
                    'symlink_supported' => false, 'php_memory_limit' => '64M',
                    'upload_max_filesize' => '2M', 'post_max_size' => '8M', 'execution_timeout' => '30',
                ];
            }
        });

        $this->artisan('platform:evidence:host-capability')->assertExitCode(0);

        self::assertSame(
            1,
            DB::table('host_capability_evidence')->count(),
            'MED-01-NO-HARD-FAIL-03: yeteneksiz bir host bile kanıt üretmeli; hard-fail yasaktır.'
        );
    }

    // --- MED-01-DEGRADATION-04 --------------------------------------------

    public function test_a_host_with_everything_needs_no_degradation(): void
    {
        self::assertSame([], RecordHostCapabilityEvidence::degradationsFor($this->capabilities()));
    }

    public function test_a_missing_imagick_degrades_to_gd_instead_of_failing(): void
    {
        $degradations = RecordHostCapabilityEvidence::degradationsFor(
            $this->capabilities(['imagick' => false])
        );

        self::assertStringStartsWith('image-derivatives:gd', $degradations[0]);
    }

    public function test_losing_both_image_libraries_is_stated_as_a_product_loss(): void
    {
        $degradations = RecordHostCapabilityEvidence::degradationsFor(
            $this->capabilities(['imagick' => false, 'gd' => false])
        );

        self::assertStringStartsWith('image-derivatives:none', $degradations[0]);
        self::assertStringContainsString(
            'Menüye görsel eklenemez',
            $degradations[0],
            'MED-01-DEGRADATION-04: kayıp yetenek, restoran sahibinin yaşayacağı sonuçla yazılmalı.'
        );
    }

    public function test_a_host_without_exec_keeps_uploads_quarantined_rather_than_publishing_them(): void
    {
        $degradations = RecordHostCapabilityEvidence::degradationsFor(
            $this->capabilities(['exec_enabled' => false])
        );

        $scan = array_values(array_filter($degradations, static fn (string $d): bool => str_starts_with($d, 'malware-scan')));

        self::assertNotEmpty($scan);
        self::assertStringContainsString(
            'fail-closed',
            $scan[0],
            'MED-01-DEGRADATION-04: tarayıcı yoksa dosya public OLMAMALI — taranmamış asset yayınlanamaz.'
        );
    }

    public function test_a_small_host_upload_cap_is_surfaced_because_the_application_sets_none(): void
    {
        $degradations = RecordHostCapabilityEvidence::degradationsFor(
            $this->capabilities(['upload_max_filesize' => '2M'])
        );

        self::assertNotEmpty(array_filter($degradations, static fn (string $d): bool => str_starts_with($d, 'upload-cap:host')));
    }

    public function test_ini_sizes_are_read_as_bytes_not_as_numbers(): void
    {
        self::assertSame(2 * 1024 * 1024, RecordHostCapabilityEvidence::toBytes('2M'));
        self::assertSame(512 * 1024, RecordHostCapabilityEvidence::toBytes('512K'));
        self::assertSame(1024 * 1024 * 1024, RecordHostCapabilityEvidence::toBytes('1G'));
        self::assertSame(0, RecordHostCapabilityEvidence::toBytes('-1'), 'Sınırsız, "sıfır bayt" değildir; sınır yok demektir.');
    }

    // --- MED-01-EVIDENCE-05 -----------------------------------------------

    public function test_each_run_appends_a_record_so_two_hosts_can_be_compared(): void
    {
        $this->artisan('platform:evidence:host-capability')->assertExitCode(0);
        $this->artisan('platform:evidence:host-capability')->assertExitCode(0);

        self::assertSame(2, DB::table('host_capability_evidence')->count());
    }

    public function test_the_record_states_the_limit_of_what_it_proves(): void
    {
        $this->artisan('platform:evidence:host-capability')->assertExitCode(0);

        $claim = (string) DB::table('host_capability_evidence')->value('claim');

        self::assertStringContainsString(
            'not a claim about any other environment',
            $claim,
            'MED-01-EVIDENCE-05: bir host\'un ölçümü, başka bir host hakkında hiçbir şey söylemez.'
        );
    }
}
