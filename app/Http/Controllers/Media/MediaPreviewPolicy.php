<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

/**
 * "Bu dosya panelin İÇİNDE açılabilir mi, açılırsa hangi başlıklarla?"
 *
 * Tek bir yerde durur çünkü İKİ uç aynı cevabı vermek zorundadır: ekranın
 * ne çizeceğini söyleyen `ShowMediaViewerController` ile baytları veren
 * `ServeMediaPreviewController`. İki listeyi iki dosyada tutmak, bir gün
 * yalnız birine tür eklenmesi demekti — ve o gün ekran "açılır" derken uç
 * 415 dönerdi, ya da daha kötüsü, uç ekranın hiç göstermediği bir türü
 * servis ederdi.
 *
 * Denetleyicilerin yanında duruyor: bu paketin yazma yetkisi
 * `app/Http/Controllers/Media/` ile sınırlı ve güvenlik listesini iki kez
 * yazmaktansa tek kopyayı çağıranların yanında tutmak yeğlendi. Alan
 * katmanına (`app/Domain/Media/`) taşınması ayrı ve güvenli bir adımdır.
 */
final class MediaPreviewPolicy
{
    /**
     * PANELDE AÇILABİLEN TÜRLER — beyaz liste, kara liste değil.
     *
     * Kara liste "unuttuğumuz tür açılır" demektir; beyaz liste
     * "tanımadığımız tür açılmaz" der. Kullanıcıya ödettiği bedel bir
     * "indir" düğmesidir; kara listenin ödettiği bedel tarayıcıda çalışan
     * bir belgedir.
     *
     * @var array<string, string>
     */
    private const EMBEDDABLE = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        'image/avif' => 'image',
        // SVG bir GÖRSEL değil BELGEDİR ve bilerek listede: alım kapısı onu
        // `SvgSanitizer` ile fail-closed doğruluyor (`docs/108` §6.2),
        // panel onu `<img>` içinde çiziyor (bir `<img>` içindeki SVG betik
        // çalıştıramaz — tarayıcının kendi garantisi) ve yanıt yine
        // betik çalıştıramaz başlıklarla geliyor. Üç hat üst üste.
        'image/svg+xml' => 'image',
    ];

    /**
     * TARAMASI TEMİZ DÖNMÜŞ durumlar.
     *
     * `quarantined`/`scanning` "henüz bilmiyoruz", `rejected` "temiz
     * değil" demektir; üçü de panelde açılmaz. `failed` ise taramayı
     * geçmiş ama TÜREV üretimi düşmüş dosyadır — aslı temizdir ve sahibin
     * "ne yükledim ben?" sorusunu tam da o dosyada sorması beklenir.
     *
     * @var list<string>
     */
    private const SCAN_CLEARED = ['accepted', 'processing', 'ready', 'failed'];

    /**
     * Sayfa sayısı için okunacak azami gövde.
     *
     * Kaynağın belge tavanı 25 MB (`docs/108` §6.2); daha büyüğünü sayfa
     * saymak için belleğe almak, bir okuma isteğini bellek maliyetine
     * çevirirdi.
     */
    private const PAGE_COUNT_MAX_BYTES = 25 * 1024 * 1024;

    /** `pdf` | `image` | `other` */
    public static function kind(string $mimeType): string
    {
        return self::EMBEDDABLE[self::normalize($mimeType)] ?? 'other';
    }

    public static function isEmbeddableType(string $mimeType): bool
    {
        return self::kind($mimeType) !== 'other';
    }

    public static function isScanCleared(string $status): bool
    {
        return in_array($status, self::SCAN_CLEARED, true);
    }

    /**
     * Gömülen yanıtın güvenlik başlıkları — FF-134 deseninin devamı.
     *
     * `sandbox`: belge kendi köken yetkilerinden yoksun açılır; içindeki
     * bağlantı üst pencereyi götüremez. `default-src 'none'`: betik yok,
     * ağ yok. `frame-ancestors 'self'`: yalnız kendi panelimiz
     * çerçeveleyebilir. `X-Frame-Options` AÇIKÇA yazılır çünkü
     * `SecurityHeaders` ara katmanı yazılmamış başlığa `DENY` koyar ve
     * `DENY` bu dosyayı kendi panelimizde bile açılmaz yapardı.
     *
     * @return array<string, string>
     */
    public static function headers(string $mimeType, string $originalName, int $assetId): array
    {
        $mime = self::normalize($mimeType);
        $filename = str_replace(['"', "\r", "\n"], '', $originalName) ?: "media-{$assetId}";

        $csp = "default-src 'none'; frame-ancestors 'self'; sandbox";

        if (str_contains($mime, 'svg')) {
            // SVG'nin kendi satır içi stili çalışsın diye tek taviz; dış
            // stil zaten temizleyicide düşer (`ServeRenditionController`
            // ile aynı satır).
            $csp = "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'self'; sandbox";
        }

        return [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            // Asıl ÖZELDİR: ne tarayıcı ne ara önbellek saklar
            // (`ServeOriginalController` ile aynı karar).
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => $csp,
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];
    }

    /**
     * PDF'in sayfa sayısı — ancak GERÇEKTEN okunabiliyorsa.
     *
     * Kütüphane kurmadan bir PDF'in sayfa ağacı ancak sıkıştırılmamış
     * gövdede okunur. PDF 1.5'ten beri sayfa ağacı çoğu üreticide nesne
     * akışına (`/ObjStm`) sıkıştırılır ve baytların içinde hiç görünmez.
     *
     * Bu yüzden İKİ BAĞIMSIZ İŞARET aranır ve yalnız İKİSİ AYNI SAYIYI
     * söylerse cevap verilir:
     *
     *   1. Sayfa ağacının kökündeki `/Count N` (kendi iddiası),
     *   2. Gövdedeki `/Type /Page` nesnelerinin sayısı (fiilî sayım).
     *
     * Uyuşmazlarsa ya da biri hiç yoksa cevap `null`'dır. Ekran o zaman
     * sayfa gezintisi çizmez — "3 / 12" yazıp 12'yi bilmemek, kullanıcıya
     * olmayan bir kesinlik satmaktır (`docs/76` dürüstlük kuralı).
     */
    public static function pdfPageCount(string $body): ?int
    {
        if ($body === '' || strlen($body) > self::PAGE_COUNT_MAX_BYTES) {
            return null;
        }

        if (preg_match_all('~/Count\s+(\d+)~', $body, $matches) < 1) {
            return null;
        }

        $declared = max(array_map('intval', $matches[1]));
        // `(?![s])`: `/Type /Pages` sayfa AĞACIDIR, sayfa değil.
        $counted = preg_match_all('~/Type\s*/Page(?![s])~', $body);

        if ($declared < 1 || $counted === false || $declared !== $counted) {
            return null;
        }

        return $declared;
    }

    private static function normalize(string $mimeType): string
    {
        $mime = strtolower(trim($mimeType));
        $separator = strpos($mime, ';');

        return $separator === false ? $mime : trim(substr($mime, 0, $separator));
    }
}
