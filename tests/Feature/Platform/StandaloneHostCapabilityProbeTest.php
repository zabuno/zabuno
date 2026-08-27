<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Infrastructure\Platform\Capability\RuntimeHostCapabilityProbe;
use Tests\TestCase;

/**
 * MED-01-PROBE-STANDALONE — FTP ile çalıştırılabilen prob.
 *
 * Depodaki prob komut satırından çalışır. Hedeflenen beş sağlayıcının üçü
 * paylaşımlı plandır ve orada SSH çoğu zaman yoktur; yani prob, tam olarak
 * tasarlandığı sunucularda çalıştırılamıyordu. `tools/host-capability-probe.php`
 * o boşluğu kapatır.
 *
 * İki risk var ve ikisi de burada tutuluyor:
 *
 * 1. **Sapma.** İki prob farklı anahtarlar raporlarsa sunucudan gelen kanıt
 *    depodaki kanıtla karşılaştırılamaz ve kanıt olmaktan çıkar.
 * 2. **Bilgi ifşası.** Dosya sunucu yapılandırmasını açık eder. Parolasız
 *    erişilebilir kalırsa, kapatmaya çalıştığımız yüzeyin kendisi olur.
 */
final class StandaloneHostCapabilityProbeTest extends TestCase
{
    private function probePath(): string
    {
        $path = base_path('tools/host-capability-probe.php');

        self::assertFileExists($path, 'MED-01-PROBE-STANDALONE: bağımsız prob yok.');

        return $path;
    }

    private function source(): string
    {
        return (string) file_get_contents($this->probePath());
    }

    public function test_the_standalone_probe_reports_exactly_the_same_capabilities_as_the_runtime_probe(): void
    {
        $runtimeKeys = array_keys((new RuntimeHostCapabilityProbe)->probe());

        preg_match('/\$capabilities = \[(.*?)\n\];/s', $this->source(), $match);
        self::assertNotEmpty($match, 'MED-01-PROBE-STANDALONE: yetenek dizisi okunamadı.');

        preg_match_all("/'([a-z0-9_]+)'\s*=>/", $match[1], $found);
        $standaloneKeys = $found[1];

        sort($runtimeKeys);
        sort($standaloneKeys);

        self::assertSame(
            $runtimeKeys,
            $standaloneKeys,
            'MED-01-PROBE-STANDALONE: iki prob farklı yetenek ölçüyor. '
            .'Sunucudan gelen çıktı depodaki kanıtla karşılaştırılamaz hâle gelir.'
        );
    }

    public function test_it_refuses_to_run_until_the_owner_sets_a_passphrase(): void
    {
        $output = $this->runProbe($this->probePath(), 'anything');

        self::assertStringContainsString(
            'PROBE_KEY',
            $output,
            'MED-01-PROBE-STANDALONE: yer tutucu parola ile çalışmayı reddetmeli.'
        );
    }

    public function test_a_wrong_passphrase_does_not_even_confirm_that_the_probe_is_there(): void
    {
        $configured = $this->withPassphrase('bir-uzun-parola');
        $output = $this->runProbe($configured, 'yanlis-parola');

        self::assertStringContainsString('Not Found', $output);
        self::assertStringNotContainsString('php_version', $output);
        self::assertStringNotContainsString('memory_limit', $output);

        @unlink($configured);
    }

    public function test_the_right_passphrase_produces_a_report_and_a_copyable_block(): void
    {
        $configured = $this->withPassphrase('bir-uzun-parola');
        $output = $this->runProbe($configured, 'bir-uzun-parola');

        self::assertStringContainsString('php_version', $output);
        self::assertStringContainsString('opcache_revalidates_files', $output);

        // Kopyalanabilir blok geçerli JSON olmalı; owner bunu geri gönderecek.
        preg_match('/<pre>(.*?)<\/pre>/s', $output, $match);
        self::assertNotEmpty($match, 'MED-01-PROBE-STANDALONE: kopyalanacak blok yok.');

        $decoded = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('capabilities', $decoded);

        @unlink($configured);
    }

    public function test_the_probe_leaves_nothing_behind_on_the_host(): void
    {
        $configured = $this->withPassphrase('bir-uzun-parola');
        $before = glob(dirname($configured).'/*') ?: [];

        $this->runProbe($configured, 'bir-uzun-parola');

        $after = glob(dirname($configured).'/*') ?: [];

        self::assertSame(
            $before,
            $after,
            'MED-01-PROBE-STANDALONE: prob yazılabilirlik testinden artık dosya bırakmış.'
        );

        @unlink($configured);
    }

    public function test_the_report_asks_search_engines_to_stay_away(): void
    {
        $source = $this->source();

        // Sayfa yanlışlıkla açıkta kalırsa en azından indekslenmesin.
        self::assertStringContainsString('X-Robots-Tag', $source);
        self::assertStringContainsString('noindex', $source);
    }

    /** Parolası ayarlanmış geçici bir kopya üretir. */
    private function withPassphrase(string $passphrase): string
    {
        $source = str_replace(
            "const PROBE_KEY = 'DEGISTIR-BENI';",
            "const PROBE_KEY = '{$passphrase}';",
            $this->source()
        );

        $dir = sys_get_temp_dir().'/zabuno-probe-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $path = $dir.'/probe.php';
        file_put_contents($path, $source);

        return $path;
    }

    private function runProbe(string $path, string $key): string
    {
        $script = sprintf(
            '$_GET["key"] = %s; include %s;',
            var_export($key, true),
            var_export($path, true)
        );

        return (string) shell_exec(PHP_BINARY.' -r '.escapeshellarg($script).' 2>&1');
    }
}
