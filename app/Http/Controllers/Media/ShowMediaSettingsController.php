<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MEDYA AYARLARI (kanonik kaynak: `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html`, ekran etiketi "Ayarlar"; somut listeler
 * `docs/108` §6.5 ve §6.6).
 *
 * SALT OKUNUR — ve bu bir eksiklik değil, ekranın SÖZÜDÜR.
 *
 * Sahibin kararı (2026-09-05): "Uygulanmayan bir anahtarı çalışıyormuş gibi
 * göstermek yasak." Bir ayar ekranındaki her kontrol bir SÖZDÜR: kullanıcı
 * onu çevirdiğinde bir şeyin değişeceğini söyler. Bu depoda desenler
 * değiştirilemez, güvenlik önlemleri kapatılamaz; o yüzden bu uç her satır
 * için DURUMU ve "değiştirilebilir mi" sorusunun cevabını açıkça gönderir,
 * ekran da kaydetme kutusu çizmez.
 *
 * VİRÜS TARAMASI ayrıca sahibin AÇIK kararıdır: gösterilir ama kapatılamaz.
 * Kapatılabilir bir güvenlik anahtarı, kapatıldığı gün bir güvenlik
 * açığıdır.
 *
 * METİN BURADA DEĞİLDİR. Uç yalnız DURUM gönderir; etiket ve açıklama
 * çeviri kataloğunda durur (`docs/37`).
 */
final class ShowMediaSettingsController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'patterns' => $this->patterns(),
            'security' => $this->security(),
        ]);
    }

    /**
     * Desen alanları (`docs/108` §6.5) — kaynağın SEÇENEKLERİ değil, bu
     * deponun GERÇEK davranışı.
     *
     * Kaynak üç seçim sunuyor. İlk ikisi bu depoda DEPOLAMA ANAHTARIDIR ve
     * anahtar asla değişmez — `2026_08_27_000400` göçünün kendi cümlesi:
     * "Depolama anahtarı ASLA değişmez; kullanıcının gördüğü ad değişir."
     * Bir dizin deseni seçtirip hiçbir dosyayı taşımamak, sahibe olmayan
     * bir yetenek satmak olurdu; taşımak ise yayınlanmış her menünün
     * görsel adresini kırardı.
     *
     * Üçüncüsü (tarih biçimi) bugün OKUYANIN CİHAZINDAN gelir
     * (`mediaFormat.ts` → `toLocaleDateString`). Sabit bir `Europe/Istanbul`
     * biçimi seçtirmek ayrı bir karardır ve henüz verilmedi.
     *
     * @return array<int, array<string, mixed>>
     */
    private function patterns(): array
    {
        return [
            ['key' => 'directory', 'value' => 'workspaceFolder', 'changeable' => false],
            ['key' => 'fileName', 'value' => 'opaqueKey', 'changeable' => false],
            ['key' => 'date', 'value' => 'deviceLocale', 'changeable' => false],
        ];
    }

    /**
     * Güvenlik önlemleri (`docs/108` §6.6) — kaynağın beş satırı, kaynağın
     * sırasıyla, BU deponun gerçek durumuyla.
     *
     * Durum sözlüğü:
     *   - `on`        : uygulanıyor.
     *   - `partial`   : bir kısmında uygulanıyor; farkı ekran yazar.
     *   - `unavailable`: kod var, bu ORTAMDA çalışmıyor.
     *   - `missing`   : bu depoda böyle bir kod yok.
     *
     * Hiçbiri `switchable` değildir. Üçü fiilen uygulanıyor ve bir ayara
     * bağlı değil; biri hiç yok. Çalışmayan bir anahtar çizmek, sahibin
     * kapattığını sanmasına ya da açık sanmasına yol açar — ikisi de yalan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function security(): array
    {
        return [
            ['key' => 'virusScan', 'state' => $this->scannerState(), 'switchable' => false],
            /*
                `StoreMediaRequest` dosyanın KENDİ ilk baytına bakar; uzantıya
                ve istemcinin bildirdiği MIME'a asla güvenmez. Uygulanıyor.
            */
            ['key' => 'contentSignature', 'state' => 'on', 'switchable' => false],
            /*
                YARIM ve öyle söyleniyor. Türevler GD ile YENİDEN kodlanır,
                dolayısıyla misafirin gördüğü dosyada EXIF (konum, cihaz,
                seri numarası) yoktur. ASIL dosya ise geldiği gibi saklanır.
                "Tamamen temizleniyor" demek yalan olurdu.
            */
            ['key' => 'metadataStrip', 'state' => 'partial', 'switchable' => false],
            /*
                Asıl dosyanın herkese açık adresi yoktur; indirme yalnız
                10 dakikalık imzalı adresle olur
                (`CreateOriginalDownloadLinkController`). Uygulanıyor.
            */
            ['key' => 'signedLink', 'state' => 'on', 'switchable' => false],
            // Bu depoda filigran diye bir kod YOK. Anahtar çizilmez.
            ['key' => 'watermark', 'state' => 'missing', 'switchable' => false],
        ];
    }

    /**
     * Tarayıcı bu ORTAMDA gerçekten çalışabiliyor mu?
     *
     * Ölçüt yalnız sürücü adı DEĞİLDİR: `MEDIA_SCANNER_DRIVER=clamav` yazıp
     * ikili yolunu boş bırakmak mümkündür ve o durumda
     * `ClamavMalwareScanner` her dosya için `Indeterminate` döner — yani
     * tarama fiilen yapılmaz. Ekranda "açık" yazmak, sahibe olmayan bir
     * korumayı vaat etmek olurdu.
     *
     * Bu koşul `AppServiceProvider`'daki bağlamanın ve
     * `ClamavMalwareScanner::scan()`'in ön koşulunun AYNISIDIR. Port'a
     * `isAvailable()` eklemek daha temiz olurdu; bugün eklenmiyor çünkü
     * `MalwareScannerPort`'u on ayrı test dosyasındaki anonim ikizler de
     * uyguluyor ve arayüzü büyütmek onların hepsini kırardı.
     */
    private function scannerState(): string
    {
        if (config('media.scanner.driver') !== 'clamav') {
            return 'unavailable';
        }

        $binary = (string) config('media.scanner.clamav.binary_path', '');
        $timeout = (float) config('media.scanner.clamav.timeout_seconds', 0);

        $usable = $binary !== ''
            && is_file($binary)
            && is_executable($binary)
            && is_finite($timeout)
            && $timeout > 0.0;

        return $usable ? 'on' : 'unavailable';
    }
}
