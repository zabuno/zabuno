<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Processing;

use App\Application\Media\Dto\MediaProcessingResult;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Domain\Media\PdfInspector;

/**
 * PDF işleyicisi — zincirin EN ÖNÜNDE duran ince bir katman.
 *
 * NEDEN VAR. Boru hattında tek bir `MediaAssetProcessorPort` var ve
 * `ProcessAcceptedMediaAsset` onu bilmeden çağırır. Bu katman olmasaydı bir
 * PDF, GD'ye giderdi; GD onu çözemez ve "Bu dosya bir görsel olarak
 * okunamadı" derdi. Sonuç: kabul edilmiş, taraması temiz çıkmış bir belge
 * kütüphanede `failed` damgasıyla otururdu ve sahip yüklediği alerjen
 * tablosunun BOZUK olduğunu sanardı. Oysa bozuk olan bir şey yok — sadece
 * yapılacak bir iş yok.
 *
 * ── PDF'İN TÜREVİ YOKTUR ─────────────────────────────────────────────────
 *
 * Bir vektörün türevi kendisidir (`SvgMediaAssetProcessor`); bir PDF'in
 * türevi ise HİÇBİR ŞEYDİR. Raster önizleme üretmek için sayfayı çizmek
 * gerekir: imagick (Ghostscript) bu sunucuda yok, GD ise bir PDF'i hiç
 * okumaz. Ürün burada iki dürüst seçenekten birini seçti:
 *
 *   - UYDURMA KAPAK ÇİZMEK: kütüphanede bir kâğıt ikonu ya da ilk sayfanın
 *     "tahmini" — sahibe gerçekte üretmediğimiz bir şeyi göstermek olurdu.
 *   - "ÖNİZLEME YOK" DEMEK: türev üretilmez, kütüphane satırı küçük
 *     resimsiz kalır ve ekranın var olan "henüz önizleme yok" davranışı
 *     doğruyu söyler. Belgeyi okumanın yolu zaten "Görüntüle"dir; orada
 *     ASIL dosya `inline` servis edilir (`MediaPreviewPolicy`).
 *
 * Bu yüzden sonuç BAŞARILIDIR ama türev listesi BOŞTUR: varlık `ready`
 * olur, iş kaydı başarıyla kapanır ve `previewUrl` doğal olarak `null`
 * kalır. "Türev yok" ile "işlenemedi" aynı şey değildir ve ürün ikisini
 * karıştırmaz.
 *
 * ── İKİNCİ SAVUNMA HATTI ─────────────────────────────────────────────────
 *
 * Alım kapısı (`StoreMediaRequest`) aynı denetçiyi zaten çalıştırır;
 * buraya normalde temiz bir dosya gelir. Yine de burada da denetlenir: tek
 * savunma hattına yaslanmak, o hattı atlayan her yeni yol (yeniden işleme,
 * ileride bir içe aktarma, bir toplu göç) açıldığında kapıyı sessizce açık
 * bırakır — `SvgMediaAssetProcessor` ile aynı gerekçe.
 */
final class PdfMediaAssetProcessor implements MediaAssetProcessorPort
{
    public function __construct(
        private readonly MediaAssetProcessorPort $inner,
        private readonly PdfInspector $inspector,
    ) {}

    public function process(string $absolutePath, string $slot = '', ?string $targetFormat = null): MediaProcessingResult
    {
        if (! is_readable($absolutePath)) {
            return $this->inner->process($absolutePath, $slot, $targetFormat);
        }

        // Yalnız ilk baytlar okunur: PDF olmayan bir dosyayı bu katman
        // için belleğe almanın anlamı yok.
        $head = (string) @file_get_contents($absolutePath, false, null, 0, 5);

        if (! str_starts_with($head, '%PDF-')) {
            // Görsel dosya: karar GD'nin (ya da SVG katmanının), bu katman
            // görünmez kalır.
            return $this->inner->process($absolutePath, $slot, $targetFormat);
        }

        $bytes = @file_get_contents($absolutePath);

        if ($bytes === false) {
            return MediaProcessingResult::failed('Yüklenen belge okunamadı. Lütfen yeniden yükleyin.');
        }

        $result = $this->inspector->inspect($bytes);

        if (! $result->isSafe()) {
            return MediaProcessingResult::failed(
                $result->failureReason ?? 'Bu PDF dosyası güvenle denetlenemedi ve kabul edilmedi.',
            );
        }

        /*
            DÖNÜŞTÜRME (`docs/108` §6.3) BİR PDF'TE ANLAMSIZDIR: AVIF/WebP
            birer GÖRSEL biçimidir ve bir belgeyi onlara "çevirmek" belgeyi
            yok etmek olurdu. Uç zaten PDF'i kaynak listesine almaz; buraya
            başka bir yoldan gelirse de sessizce yanlış bir şey üretmek
            yerine sebebiyle reddedilir.
        */
        if ($targetFormat !== null) {
            return MediaProcessingResult::failed(
                'Bir PDF belgesi görsel biçimine çevrilemez. Belge olduğu gibi saklanır.',
            );
        }

        // Ölçü SIFIR: bir belgenin piksel genişliği yoktur ve uydurulmaz.
        return MediaProcessingResult::succeeded([], 0, 0, null);
    }
}
