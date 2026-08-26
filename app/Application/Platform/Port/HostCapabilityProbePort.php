<?php

declare(strict_types=1);

namespace App\Application\Platform\Port;

/**
 * Host yetenek probu — `docs/16` MED-01, `skills/shared-host-capability.md`.
 *
 * Salt-okunur: host'a kalıcı hiçbir değişiklik bırakmaz. Symlink testi gibi
 * yazma gerektiren bir kontrol yaparsa, ürettiği dosyayı aynı çağrı içinde
 * siler.
 *
 * @return array{
 *     imagick: bool, gd: bool, ffmpeg: bool, exec_enabled: bool,
 *     symlink_supported: bool, php_memory_limit: string,
 *     upload_max_filesize: string, post_max_size: string,
 *     execution_timeout: string, redis_available: bool,
 *     php_version: string, sqlite: bool
 * }
 */
interface HostCapabilityProbePort
{
    /** @return array<string, bool|string> */
    public function probe(): array;
}
