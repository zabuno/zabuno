<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Capability;

use App\Application\Platform\Port\HostCapabilityProbePort;

/**
 * Yetenekleri çalışan PHP sürecine SORARAK ölçer — tahmin etmez.
 *
 * Paylaşımlı barındırmada bir sağlayıcının "PHP 8.3 destekliyoruz" demesi,
 * `exec`'in açık olduğu ya da Imagick'in kurulu olduğu anlamına gelmez.
 * Deploy sonrası "neden çalışmıyor?" sorusunun cevabı çoğu zaman bu
 * listededir; bu yüzden liste tahminle değil, ölçümle doldurulur.
 */
final class RuntimeHostCapabilityProbe implements HostCapabilityProbePort
{
    /** @return array<string, bool|string> */
    public function probe(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'imagick' => extension_loaded('imagick'),
            'gd' => extension_loaded('gd'),
            'sqlite' => extension_loaded('pdo_sqlite'),
            'redis' => extension_loaded('redis'),
            'ffmpeg' => $this->binaryExists('ffmpeg'),
            'exec_enabled' => $this->execEnabled(),
            'symlink_supported' => $this->symlinkSupported(),
            'php_memory_limit' => (string) ini_get('memory_limit'),
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'execution_timeout' => (string) ini_get('max_execution_time'),
        ];
    }

    private function execEnabled(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        foreach (['exec', 'proc_open'] as $needed) {
            if (! function_exists($needed) || in_array($needed, $disabled, true)) {
                return false;
            }
        }

        return true;
    }

    private function binaryExists(string $binary): bool
    {
        // `exec` kapalıysa ikilinin varlığını ölçemeyiz. "Bilinmiyor"u
        // "yok" saymak, olmayan bir yeteneği varsaymaktan daha güvenlidir.
        if (! $this->execEnabled()) {
            return false;
        }

        $output = [];
        $exitCode = 1;
        @exec('command -v '.escapeshellarg($binary).' 2>/dev/null', $output, $exitCode);

        return $exitCode === 0 && $output !== [];
    }

    private function symlinkSupported(): bool
    {
        if (! function_exists('symlink')) {
            return false;
        }

        $directory = sys_get_temp_dir();
        $target = $directory.'/zabuno-probe-target-'.bin2hex(random_bytes(6));
        $link = $directory.'/zabuno-probe-link-'.bin2hex(random_bytes(6));

        if (@file_put_contents($target, 'probe') === false) {
            return false;
        }

        $supported = @symlink($target, $link);

        // Prob hiçbir iz bırakmaz — bıraksaydı, host'u kirletirdi ve bir
        // sonraki çalıştırma yanlış sonuç verebilirdi.
        @unlink($link);
        @unlink($target);

        return $supported;
    }
}
