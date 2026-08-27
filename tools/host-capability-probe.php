<?php

declare(strict_types=1);

/**
 * Zabuno — bağımsız barındırma yetenek probu.
 *
 * NEDEN AYRI BİR DOSYA
 *
 * Depoda zaten bir prob var (`platform:evidence:host-capability`), ama o
 * komut satırından çalışıyor. Hedeflenen beş sağlayıcının üçü paylaşımlı
 * plan ve orada çoğu zaman SSH yok — yani prob, tam olarak tasarlandığı
 * sunucularda çalıştırılamıyordu. Bu dosya o boşluğu kapatır: framework
 * yüklemez, composer istemez, Node istemez. FTP ile yüklenir, tarayıcıdan
 * açılır, çıktısı kopyalanır.
 *
 * NASIL KULLANILIR
 *
 *   1. Aşağıdaki PROBE_KEY değerini kendi seçtiğin uzun bir parolayla
 *      değiştir. Değiştirmezsen dosya çalışmayı reddeder.
 *   2. Dosyayı sitenin kök dizinine yükle.
 *   3. Tarayıcıda aç:  https://alanadin.com/host-capability-probe.php?key=SECTIGIN_PAROLA
 *   4. Çıktıdaki JSON bloğunu kopyala.
 *   5. DOSYAYI SİL. Sunucu bilgisi sızdırır; işi bitince orada durmamalı.
 *
 * Prob hiçbir şey yazmaz, kurmaz ve değiştirmez — yalnız okur. Tek
 * istisnası yazılabilirlik testidir: geçici bir dosya oluşturup hemen
 * siler.
 */
const PROBE_KEY = 'DEGISTIR-BENI';

// --- erişim ---------------------------------------------------------------
// Bu çıktı sunucunun yapılandırmasını açık eder. Kimliği doğrulanmamış
// bir bilgi ifşası bırakmamak için iki kapı var: parola değiştirilmiş
// olmalı, ve istek doğru parolayı taşımalı.

if (PROBE_KEY === 'DEGISTIR-BENI') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Prob kapalı: PROBE_KEY değiştirilmedi.\nDosyayı düzenleyip kendi parolanı yaz, sonra tekrar dene.\n");
}

$supplied = isset($_GET['key']) && is_string($_GET['key']) ? $_GET['key'] : '';

if (! hash_equals(PROBE_KEY, $supplied)) {
    // Varlığını da doğrulamıyoruz: yanlış parola ile "burada bir şey var"
    // bilgisi bile verilmez.
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not Found\n");
}

// --- ölçüm ----------------------------------------------------------------
// Anahtar adları `RuntimeHostCapabilityProbe` ile birebir aynıdır; aksi
// hâlde buradan gelen kanıt depodaki kanıtla karşılaştırılamaz. Parite
// `StandaloneHostCapabilityProbeTest` tarafından zorlanır.

function probe_exec_enabled(): bool
{
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    foreach (['exec', 'proc_open'] as $needed) {
        if (! function_exists($needed) || in_array($needed, $disabled, true)) {
            return false;
        }
    }

    return true;
}

function probe_binary_exists(string $binary): bool
{
    // `exec` kapalıysa ölçemeyiz. "Bilinmiyor"u "yok" saymak, olmayan bir
    // yeteneği varsaymaktan güvenlidir.
    if (! probe_exec_enabled()) {
        return false;
    }

    $output = [];
    $exitCode = 1;
    @exec('command -v '.escapeshellarg($binary).' 2>/dev/null', $output, $exitCode);

    return $exitCode === 0 && $output !== [];
}

function probe_symlink_supported(): bool
{
    if (! function_exists('symlink')) {
        return false;
    }

    $dir = sys_get_temp_dir();
    $target = $dir.'/zabuno-probe-target-'.bin2hex(random_bytes(6));
    $link = $dir.'/zabuno-probe-link-'.bin2hex(random_bytes(6));

    if (@file_put_contents($target, 'probe') === false) {
        return false;
    }

    $ok = @symlink($target, $link);

    // Prob arkasında iz bırakmaz.
    @unlink($link);
    @unlink($target);

    return (bool) $ok;
}

function probe_opcache_enabled(): bool
{
    return function_exists('opcache_get_status') && (bool) ini_get('opcache.enable');
}

function probe_opcache_revalidates_files(): bool
{
    // Opcache yoksa dosya her istekte okunur: davranış zaten doğru.
    if (! probe_opcache_enabled()) {
        return true;
    }

    return (bool) ini_get('opcache.validate_timestamps');
}

function probe_storage_writable(): bool
{
    // Bağımsız dosya uygulamanın dizin yapısını bilmez; kendi bulunduğu
    // yerin yazılabilirliği en yakın gerçeğe karşılık gelir.
    $dir = __DIR__;
    $probe = $dir.'/zabuno-write-probe-'.bin2hex(random_bytes(6)).'.tmp';
    $written = @file_put_contents($probe, 'probe');
    @unlink($probe);

    return $written !== false;
}

function probe_url_rewrite(): bool
{
    if (function_exists('apache_get_modules')) {
        return in_array('mod_rewrite', (array) apache_get_modules(), true);
    }

    // nginx/LiteSpeed/PHP-FPM altında modül listesi okunamaz. Ölçemediğimizi
    // "var" saymıyoruz.
    return false;
}

$capabilities = [
    'php_version' => PHP_VERSION,
    'imagick' => extension_loaded('imagick'),
    'gd' => extension_loaded('gd'),
    'sqlite' => extension_loaded('pdo_sqlite'),
    'redis' => extension_loaded('redis'),
    'ffmpeg' => probe_binary_exists('ffmpeg'),
    'exec_enabled' => probe_exec_enabled(),
    'symlink_supported' => probe_symlink_supported(),
    'php_memory_limit' => (string) ini_get('memory_limit'),
    'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
    'post_max_size' => (string) ini_get('post_max_size'),
    'execution_timeout' => (string) ini_get('max_execution_time'),
    'opcache_enabled' => probe_opcache_enabled(),
    'opcache_revalidates_files' => probe_opcache_revalidates_files(),
    'storage_writable' => probe_storage_writable(),
    'intl' => extension_loaded('intl'),
    'mbstring' => extension_loaded('mbstring'),
    'url_rewrite' => probe_url_rewrite(),
    'zip' => extension_loaded('zip'),
];

// --- rapor ----------------------------------------------------------------

/** Ölçülemeyenin ölçülenden ayrı durması için. */
$notes = [];

if (! probe_exec_enabled()) {
    $notes[] = 'exec kapalı: ffmpeg gerçekte kurulu olabilir ama buradan görülemez.';
}

if (! function_exists('apache_get_modules')) {
    $notes[] = 'Sunucu Apache modül listesi vermiyor (nginx/LiteSpeed/PHP-FPM olabilir). url_rewrite=false burada "ölçülemedi" demektir, "yok" demek değildir.';
}

if (probe_opcache_enabled() && ! probe_opcache_revalidates_files()) {
    $notes[] = 'DİKKAT: opcache açık ve dosya değişikliklerini izlemiyor. FTP ile yüklenen PHP dosyaları, önbellek temizlenene kadar DEVREYE GİRMEZ.';
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow">
<title>Zabuno — barındırma yetenek probu</title>
<style>
    body { font: 16px/1.5 system-ui, sans-serif; margin: 2rem auto; max-width: 52rem; padding: 0 1rem; }
    table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
    th, td { text-align: left; padding: .4rem .6rem; border-bottom: 1px solid #ddd; }
    .yes { color: #146c2e; font-weight: 600; }
    .no { color: #a11; font-weight: 600; }
    pre { background: #f4f4f5; padding: 1rem; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
    .warn { background: #fff4e5; border-left: 4px solid #d97706; padding: .75rem 1rem; }
</style>
</head>
<body>
<h1>Barındırma yetenek probu</h1>
<p>Bu sayfa yalnız okur. Ölçüm bittiğinde <strong>dosyayı sunucudan silin</strong>.</p>

<?php foreach ($notes as $note) { ?>
    <p class="warn"><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></p>
<?php } ?>

<table>
<tr><th>Yetenek</th><th>Sonuç</th></tr>
<?php foreach ($capabilities as $key => $value) { ?>
    <tr>
        <td><code><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></code></td>
        <td>
        <?php if (is_bool($value)) { ?>
            <span class="<?= $value ? 'yes' : 'no' ?>"><?= $value ? 'var' : 'yok' ?></span>
        <?php } else { ?>
            <?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>
        <?php } ?>
        </td>
    </tr>
<?php } ?>
</table>

<h2>Kopyalanacak blok</h2>
<p>Aşağıdaki metnin tamamını kopyalayıp geri gönderin.</p>
<pre><?= htmlspecialchars(
    json_encode(
        ['capabilities' => $capabilities, 'notes' => $notes],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '{}',
    ENT_QUOTES,
    'UTF-8'
) ?></pre>

<p><strong>Son adım:</strong> bu dosyayı FTP ile silin.</p>
</body>
</html>
