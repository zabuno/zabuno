<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Bayt sınırı bakımından bir dosyanın NE OLDUĞU (FF-158).
 *
 * NEDEN SLOT DEĞİL DE TÜR. Slot politikası bir görselin NEREDE
 * kullanılacağını söyler (`SlotPolicy`: en küçük ölçü, oran, kabul edilen
 * biçimler). Bayt sınırı ise başka bir soruya cevap verir: bu dosyanın
 * MEŞRU büyüklüğü ne kadar? Cevap slota değil türe bağlıdır — taranmış bir
 * A3 menü hangi slota giderse gitsin taranmış bir A3 menüdür.
 *
 * NEDEN ÜÇ TÜR VAR VE DÖRDÜNCÜSÜ YOK.
 *
 *   - `Image` — yükleme yolunun büyük çoğunluğu. Telefon fotoğrafı,
 *     DSLR JPEG'i ve taranmış kâğıt menü aynı aileden.
 *   - `Vector` — SVG bir görsel değil BELGEDİR ve temizleyici
 *     (`SvgSanitizer`) gövdenin TAMAMINI ayrıştırmak zorundadır. Buradaki
 *     sınır bir kolaylık değil, saldırı yüzeyi kısıtıdır.
 *   - `Document` — bugün yalnız PDF (`document` slotu). Denetçi
 *     (`PdfInspector`) yine tüm gövdeyi okur, ama meşru bir basılı menü
 *     gerçekten büyüktür; iki gerçek ayrı sayı ister.
 *
 * VİDEO YOKTUR. `docs/109` §8.2: "Depo video kabul etmiyor; eksik olan
 * ffmpeg değil, video hattı hiç yok." Buraya bir `Video` durumu yazmak,
 * olmayan bir yeteneği ilan etmek olurdu — ve bir sayı bir SÖZDÜR. Hat
 * açıldığı gün eklenecek şey tek bir durum ve tek bir yapılandırma
 * satırıdır; sınırın nereye ekleneceği bugünden bellidir, sayısı ise
 * bugün yoktur. `UploadSizeCeilingTest` bu boşluğu KORUR.
 */
enum MediaSizeKind: string
{
    case Image = 'image';
    case Vector = 'vector';
    case Document = 'document';

    /**
     * Biçim adından tür — kabul edilmeyen bir biçimde `null`.
     *
     * Liste `config/media-slots.php` içindeki slot `formats` değerlerinin
     * TAMAMINI karşılar; karşılamadığı gün kapı kırılır (bkz.
     * `UploadSizeCeilingTest`). `jpg`/`heif`/`tif` gibi eşanlamlılar da
     * tanınır: yapılandırma `jpeg` yazar ama bir dosya adı `jpg` gelir.
     */
    public static function tryFromFormat(string $format): ?self
    {
        return match (ltrim(strtolower(trim($format)), '.')) {
            'jpeg', 'jpg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'tiff', 'tif' => self::Image,
            'svg' => self::Vector,
            'pdf' => self::Document,
            default => null,
        };
    }
}
