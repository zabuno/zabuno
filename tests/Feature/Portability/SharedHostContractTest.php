<?php

declare(strict_types=1);

namespace Tests\Feature\Portability;

use Tests\TestCase;

/**
 * Paylaşımlı barındırma taşınabilirlik sözleşmesi — owner kararı 2026-08-27.
 *
 * Hedef beş barındırıcı birden: netcup (AMD EPYC), Hetzner ve üç paylaşımlı
 * sağlayıcı. Karar "hepsinde KALICI olarak çalışsın" biçimindedir, yani **en
 * dar ortam tabandır**: bir özellik yalnız kök erişimi olan bir sunucuda
 * çalışıyorsa, bu ürün için yoktur.
 *
 * Bu kapı bir belge değil, bir eşiktir. Bugün her şey uyumlu; amaç yarın
 * eklenen bir satırın bunu sessizce bozmasını engellemektir — çünkü kırılma
 * yerel makinede değil, müşterinin sunucusunda görünür.
 *
 * Requirement ID'leri: HOST-EXT-01, HOST-DRIVER-02, HOST-PROCESS-03,
 * HOST-QUEUE-04, HOST-SYMLINK-05, HOST-WEBSERVER-06.
 */
final class SharedHostContractTest extends TestCase
{
    /** @return array<string, mixed> */
    private function composer(): array
    {
        return (array) json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private function appFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    // --- HOST-EXT-01 -------------------------------------------------------

    public function test_no_php_extension_a_shared_host_may_lack_is_required(): void
    {
        // Paylaşımlı barındırmada eklenti listesi sizin elinizde değildir.
        // Zorunlu kılınan bir eklenti, `composer install`'ın o sunucuda hiç
        // çalışmaması demektir.
        $required = array_keys((array) ($this->composer()['require'] ?? []));

        foreach (['ext-redis', 'ext-imagick', 'ext-gettext', 'ext-pcntl', 'ext-posix', 'ext-sockets', 'ext-ffi', 'ext-inotify'] as $risky) {
            self::assertNotContains(
                $risky,
                $required,
                "HOST-EXT-01: `{$risky}` zorunlu kılınmış; paylaşımlı barındırmada bulunmayabilir ve kurulum orada hiç çalışmaz."
            );
        }
    }

    // --- HOST-DRIVER-02 ----------------------------------------------------

    public function test_deployed_environments_choose_drivers_a_shared_host_actually_has(): void
    {
        foreach (['.env.production.example', '.env.staging.example'] as $file) {
            $contents = (string) file_get_contents(base_path($file));

            foreach (['CACHE_STORE', 'QUEUE_CONNECTION', 'SESSION_DRIVER'] as $key) {
                if (preg_match('/^'.$key.'=(.+)$/m', $contents, $matches) !== 1) {
                    continue;
                }

                $value = trim($matches[1]);

                self::assertNotContains(
                    $value,
                    ['redis', 'memcached', 'dynamodb'],
                    "HOST-DRIVER-02: {$file} içindeki {$key}={$value} paylaşımlı barındırmada yoktur."
                );
            }
        }
    }

    // --- HOST-PROCESS-03 ---------------------------------------------------

    public function test_process_spawning_stays_inside_the_files_that_degrade_when_it_is_forbidden(): void
    {
        // `exec`/`proc_open` birçok paylaşımlı sağlayıcıda kapalıdır. Kapalı
        // olduğunda çağıran kodun ÇÖKMEMESİ, planlı biçimde düşmesi gerekir.
        // Bu yüzden çağrı yerleri sayılıdır ve her biri düşüş yolunu bilir.
        $allowed = [
            'Infrastructure/Platform/Capability/RuntimeHostCapabilityProbe.php',
            'Infrastructure/Security/Execution/SqliteBackupRestoreDrillRunner.php',
        ];

        $offenders = [];

        foreach ($this->appFiles() as $file) {
            $body = (string) file_get_contents($file);

            if (preg_match('/(?<![a-zA-Z_])(exec|shell_exec|proc_open|passthru|popen)\s*\(/', $body) !== 1) {
                continue;
            }

            $relative = str_replace(app_path().'/', '', $file);

            if (! in_array($relative, $allowed, true)) {
                $offenders[] = $relative;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'HOST-PROCESS-03: bu dosyalar süreç açıyor ama izinli listede değil. '
            .'Paylaşımlı barındırmada `exec` kapalıysa planlı düşüş yolu olmalı (docs/15 §4b).'
        );
    }

    // --- HOST-QUEUE-04 -----------------------------------------------------

    public function test_a_queued_job_may_not_exist_without_a_cron_path_to_run_it(): void
    {
        // Paylaşımlı barındırmada sürekli çalışan bir worker yoktur. Kuyruğa
        // atılan bir iş, onu işleyecek zamanlanmış bir komut olmadan
        // veritabanında SONSUZA KADAR bekler — hata vermez, sadece hiç olmaz.
        $queued = [];

        foreach ($this->appFiles() as $file) {
            if (str_contains((string) file_get_contents($file), 'ShouldQueue')) {
                $queued[] = str_replace(app_path().'/', '', $file);
            }
        }

        if ($queued === []) {
            self::assertTrue(true, 'Kuyruk bağımlılığı yok.');

            return;
        }

        $schedule = (string) file_get_contents(base_path('routes/console.php'));

        self::assertStringContainsString(
            'queue:work',
            $schedule,
            'HOST-QUEUE-04: kuyruğa iş atılıyor ('.implode(', ', $queued).') ama onu cron ile işleyecek '
            .'zamanlanmış bir komut yok. Paylaşımlı barındırmada o işler asla çalışmaz.'
        );
        self::assertStringContainsString(
            '--stop-when-empty',
            $schedule,
            'HOST-QUEUE-04: cron ile çalışan bir worker kendini durdurmalı; aksi hâlde süreç birikir.'
        );
    }

    // --- HOST-SYMLINK-05 ---------------------------------------------------

    public function test_no_runtime_path_depends_on_creating_a_symlink(): void
    {
        // `storage:link` bazı paylaşımlı sağlayıcılarda çalışmaz. Çalışma
        // zamanında symlink'e bağlı bir yol, o sunucularda ölü bir yoldur.
        $offenders = [];

        foreach ($this->appFiles() as $file) {
            $relative = str_replace(app_path().'/', '', $file);

            if ($relative === 'Infrastructure/Platform/Capability/RuntimeHostCapabilityProbe.php') {
                continue; // probun işi zaten bunu ÖLÇMEK
            }

            if (preg_match('/(?<![a-zA-Z_])symlink\s*\(/', (string) file_get_contents($file)) === 1) {
                $offenders[] = $relative;
            }
        }

        self::assertSame([], $offenders, 'HOST-SYMLINK-05: çalışma zamanı symlink oluşturmaya bağımlı.');
    }

    // --- HOST-WEBSERVER-06 -------------------------------------------------

    public function test_url_normalization_lives_in_the_application_not_in_web_server_config(): void
    {
        // Paylaşımlı barındırmada `nginx.conf`'a erişemezsiniz ve `.htaccess`
        // sağlayıcıya göre farklı davranır. Kural uygulamada olursa beş
        // barındırıcıda da aynı çalışır (docs/38 §8).
        self::assertFileExists(app_path('Http/Middleware/CanonicalUrl.php'));

        $policy = (array) config('url-policy', []);

        self::assertFalse(
            (bool) ($policy['enforce_host'] ?? false),
            'HOST-WEBSERVER-06: kanonik host varsayılan olarak zorlanamaz — aynı yapı beş barındırıcıda çalışmalı.'
        );
    }
}
