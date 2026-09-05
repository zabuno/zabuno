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

            // Aşağıdakiler 2026-08-27'de eklendi. Sebep: owner PO
            // dosyalarını FTP ile yükleyip sonucu görmek istiyor
            // (`docs/13` §7) ve MVP ilanı gerçek sunucu kanıtı bekliyor.
            // O iş akışını sessizce bozabilecek şeyler ölçülmeden
            // "çalışacak" denemez.

            // FTP iş akışının en sinsi düşmanı. `validate_timestamps=0`
            // ise yüklenen dosya devreye GİRMEZ ve hata da vermez:
            // kullanıcı yükler, hiçbir şey değişmez, sebebi görünmez.
            'opcache_enabled' => $this->opcacheEnabled(),
            'opcache_revalidates_files' => $this->opcacheRevalidatesFiles(),

            // PO önbelleğinin yazılabileceği bir yer var mı
            // (`docs/40` Faz 1.3 buna göre düşer).
            'storage_writable' => $this->storageWritable(),

            // Locale/para/zaman biçimleme ve UTF-8. Yokluğunda çok dilli
            // katalog sessizce bozuk çıktı verir.
            'intl' => extension_loaded('intl'),
            'mbstring' => extension_loaded('mbstring'),

            // Temiz URL'ler tüm URL motorunun ön şartı (`docs/38`).
            'url_rewrite' => $this->urlRewriteAvailable(),

            'zip' => extension_loaded('zip'),

            /*
                TARAYICI, KURULDUĞU HÂLDE SESSİZCE ÖLEBİLİR.

                `ClamavMalwareScanner` taramaya başlamadan önce üç şeye bakar
                ve biri tutmazsa taramayı hiç denemez: yol boş mu, dosya var
                mı, çalıştırılabilir mi. Sonuç "belirsiz" olur — dosya
                bekler, hata basılmaz, ekranda tarayıcının hiç kurulmamış
                hâlinden ayırt edilemez.

                Bu iki alan tam olarak o üç kontrolün ölçümüdür. Sürücünün
                ADI da yazılır çünkü "ikili çalışmıyor" ile "sürücü hiç
                açılmamış" aynı sonucu doğuran FARKLI iki sebeptir ve
                düzeltmeleri farklı yerdedir.
            */
            'malware_scanner_driver' => (string) config('media.scanner.driver'),
            'malware_scanner_binary_usable' => $this->malwareScannerBinaryUsable(),
        ];
    }

    /**
     * Yapılandırılmış tarayıcı ikilisi GERÇEKTEN çağrılabilir mi?
     *
     * Kontroller `ClamavMalwareScanner`'ın kendi kontrollerinin AYNISIDIR ve
     * bilerek öyledir: prob başka bir soru sorsaydı, "kanıt yeşil ama tarama
     * çalışmıyor" gibi ikisi de doğru görünen bir çelişki üretirdi.
     */
    private function malwareScannerBinaryUsable(): bool
    {
        $path = (string) config('media.scanner.clamav.binary_path');

        return $path !== '' && is_file($path) && is_executable($path);
    }

    private function opcacheEnabled(): bool
    {
        return function_exists('opcache_get_status') && (bool) ini_get('opcache.enable');
    }

    /**
     * Opcache açıkken dosya değişikliklerinin görülüp görülmediği.
     *
     * Kapalıysa (`validate_timestamps=0`) yüklenen her PHP dosyası,
     * önbellek elle temizlenene kadar yok sayılır. Paylaşımlı
     * barındırmada bunu temizlemenin yolu çoğu zaman kontrol panelidir,
     * ve bu bilinmeden FTP tabanlı bir iş akışı vaat edilemez.
     */
    private function opcacheRevalidatesFiles(): bool
    {
        if (! $this->opcacheEnabled()) {
            // Opcache yoksa dosya her istekte okunur: davranış zaten doğru.
            return true;
        }

        return (bool) ini_get('opcache.validate_timestamps');
    }

    private function storageWritable(): bool
    {
        $path = storage_path('framework');

        return is_dir($path) && is_writable($path);
    }

    private function urlRewriteAvailable(): bool
    {
        if (function_exists('apache_get_modules')) {
            return in_array('mod_rewrite', (array) apache_get_modules(), true);
        }

        // nginx, LiteSpeed ve PHP-FPM altında modül listesi okunamaz.
        // Ölçemediğimiz şeyi "var" saymıyoruz; sunucuda gerçek bir
        // istekle doğrulanması gerektiği raporda yazılı.
        return false;
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
