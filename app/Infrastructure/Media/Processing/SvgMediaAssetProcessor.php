<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Processing;

use App\Application\Media\Dto\GeneratedRendition;
use App\Application\Media\Dto\MediaProcessingResult;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Domain\Media\SlotCatalogue;
use App\Domain\Media\SvgSanitizer;

/**
 * SVG işleyicisi — GD'nin ÖNÜNDE duran ince bir katman.
 *
 * NEDEN AYRI BİR İŞLEYİCİ. GD bir raster kütüphanesidir; SVG'yi hiç
 * çözemez ve çözmesi de gerekmez. Bir vektörden 320w/640w/960w üretmek
 * ANLAMSIZDIR: SVG zaten her ölçekte keskindir, üstelik onu rasterleştirmek
 * `printLogo` slotunun bütün gerekçesini (baskı çözünürlüğü ekrandan
 * bağımsızdır) yok ederdi. Bu yüzden SVG tek bir türev üretir: kendisi,
 * temizlenmiş hâliyle.
 *
 * NEDEN SARMALAYICI. Boru hattında tek bir `MediaAssetProcessorPort` var
 * ve `ProcessAcceptedMediaAsset`/`ReprocessMediaAsset` onu bilmeden çağırır.
 * GD'nin içine bir `if svg` koymak, raster işleyiciye ilgisiz bir sorumluluk
 * yüklerdi; SVG olmayan her dosya buradan DEĞİŞMEDEN geçer.
 *
 * ── TEMİZLENMİŞ GÖVDE NEREDE SAKLANIR ────────────────────────────────────
 *
 * Karar: **asıl karantinada olduğu gibi kalır, temizlenmiş gövde bir
 * RENDITION olarak saklanır** (`media_versions` v1 → `media_renditions`
 * `vector` → `media_blobs`).
 *
 * Gerekçe:
 *   1. "Asıl korunur" depoda bir kuraldır (`docs/49` Faz 3): parmak izi
 *      alım anında alınır ve "dosya değişmedi" iddiasının tek kanıtı odur.
 *      Aslın üzerine temizlenmiş gövdeyi yazmak o kanıtı yok ederdi.
 *   2. Halka giden tek şey türevdir — polyglot GIF savunmasının aynısı
 *      (`MaliciousIntakeGateTest`). Asıl yalnız imzalı adresten, `attachment`
 *      olarak iner; menüdeki `<img>` her zaman türevi gösterir.
 *   3. Yeniden işleme (reprocess) temizleyicinin YENİ sürümünü eski dosyalara
 *      uygulayabilir ve v2 açar. Asıl durduğu için bu mümkündür; asıl
 *      ezilseydi, temizleyici düzeldiğinde geriye uygulanacak bir şey
 *      kalmazdı.
 */
final class SvgMediaAssetProcessor implements MediaAssetProcessorPort
{
    public function __construct(
        private readonly MediaAssetProcessorPort $inner,
        private readonly SvgSanitizer $sanitizer,
        private readonly SlotCatalogue $slots,
    ) {}

    /**
     * `$targetFormat` (dönüştürme, `docs/108` §6.3) buradan DEĞİŞMEDEN
     * geçer ve SVG'de HİÇ uygulanmaz. Vektörü AVIF'e çevirmek bir kazanç
     * değil kayıptır — SVG her ölçekte keskindir. Bu yüzden dönüştürme
     * bölümü SVG'yi kaynak listesine hiç almaz; buraya bir SVG başka bir
     * yoldan gelirse de vektör vektör kalır.
     */
    public function process(string $absolutePath, string $slot = '', ?string $targetFormat = null): MediaProcessingResult
    {
        if (! is_readable($absolutePath)) {
            return $this->inner->process($absolutePath, $slot, $targetFormat);
        }

        $bytes = @file_get_contents($absolutePath);

        if ($bytes === false || ! $this->looksLikeSvg($bytes)) {
            // Raster dosya: karar GD'nindir, bu katman görünmez kalır.
            return $this->inner->process($absolutePath, $slot, $targetFormat);
        }

        $result = $this->sanitizer->sanitize($bytes);

        if (! $result->isSafe()) {
            /*
                FAIL-CLOSED. Alım kapısı (`StoreMediaRequest`) aynı
                temizleyiciyi zaten çalıştırır, dolayısıyla buraya normalde
                temiz bir dosya gelir. Yine de burada da reddedilir: tek
                savunma hattına yaslanmak, o hattı atlayan her yeni yol
                (yeniden işleme, ileride bir içe aktarma) açıldığında
                sessizce açık bırakır.
            */
            return MediaProcessingResult::failed(
                $result->failureReason ?? 'Bu SVG dosyası güvenle temizlenemedi ve kabul edilmedi.',
            );
        }

        $sanitized = (string) $result->sanitized;
        [$width, $height] = $this->intrinsicSize($sanitized, $slot);

        return MediaProcessingResult::succeeded(
            [new GeneratedRendition(
                // `vector` bir GENİŞLİK değil, bir cinstir: "320w" demek
                // burada yanlış olurdu, çünkü dosya her genişlikte aynıdır.
                profile: 'vector',
                width: $width,
                height: $height,
                format: 'svg',
                mimeType: 'image/svg+xml',
                bytes: $sanitized,
            )],
            $width,
            $height,
            // LQIP yok: SVG zaten birkaç kilobayttır ve yer tutucu için
            // beklenecek bir indirme süresi oluşmaz.
            null,
        );
    }

    /**
     * Bir SVG'nin ilk baytları her zaman `<`tır (BOM ve XML bildirimi
     * sayılmazsa). Uzantıya ya da istemcinin bildirdiği MIME'a BAKILMAZ:
     * ikisi de yükleyenin denetimindedir.
     */
    private function looksLikeSvg(string $bytes): bool
    {
        $head = ltrim(str_starts_with($bytes, "\xEF\xBB\xBF") ? substr($bytes, 3) : $bytes);

        if (! str_starts_with($head, '<')) {
            return false;
        }

        return stripos(substr($head, 0, 2048), '<svg') !== false;
    }

    /**
     * Vektörün "doğal" ölçüsü — `media_renditions.width/height` boş
     * geçilemez ve bu sütunlar kütüphanede sıralama/gösterim için okunur.
     *
     * Sıra: `viewBox` (en güvenilir, birimsizdir) → `width`/`height`
     * öznitelikleri → slotun en büyük rendition'ı. Son basamak bir tahmin
     * DEĞİL, bir varsayılandır: slot zaten "bu yerde şu ölçü gerekir"
     * diyen kaydın kendisidir.
     *
     * @return array{0:int,1:int}
     */
    private function intrinsicSize(string $svg, string $slot): array
    {
        if (preg_match('/viewBox\s*=\s*"[\s,]*[-\d.eE]+[\s,]+[-\d.eE]+[\s,]+([\d.eE]+)[\s,]+([\d.eE]+)/', $svg, $box) === 1) {
            $width = (int) round((float) $box[1]);
            $height = (int) round((float) $box[2]);

            if ($width > 0 && $height > 0) {
                return [$width, $height];
            }
        }

        $width = $this->lengthAttribute($svg, 'width');
        $height = $this->lengthAttribute($svg, 'height');

        if ($width > 0 && $height > 0) {
            return [$width, $height];
        }

        $fallback = $this->slots->has($slot) ? $this->slots->get($slot)->largestRendition() : 0;
        $fallback = $fallback > 0 ? $fallback : 512;

        return [$fallback, $fallback];
    }

    private function lengthAttribute(string $svg, string $name): int
    {
        // `width="512px"` ve `width="512"` aynı şeydir; yüzde ("100%")
        // doğal bir ölçü BİLDİRMEZ ve sıfır sayılır.
        if (preg_match('/\b'.$name.'\s*=\s*"\s*([\d.]+)\s*(px)?\s*"/i', $svg, $match) !== 1) {
            return 0;
        }

        return (int) round((float) $match[1]);
    }
}
