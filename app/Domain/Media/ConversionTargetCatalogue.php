<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Dönüştürme hedeflerinin tamamı (kanonik kaynak: `docs/reference/
 * media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi "Dönüştür";
 * somut liste `docs/108` §6.3).
 *
 * SIRA KORUNUR: kaynak hedefleri AVIF → WebP → WebM → JPEG diye diziyor ve
 * bu bir okuma sırasıdır — en küçükten en uyumluya. Alfabetik sıralamak
 * listeyi anlamsız kılardı.
 *
 * NEDEN YAPILANDIRMADA DEĞİL. `DerivativeCatalogue` kurallarını
 * `config/media-slots.php`den okur, çünkü orada ayarlanabilir bir SAYI
 * vardır: 320'yi 360 yapmak boru hattını değiştirmeden çalışır. Burada
 * öyle bir şey yok — yapılandırmaya "heic" yazmak sunucuyu HEIC üretebilir
 * hâle getirmez. Ayarlanabilir görünen ama ayarlandığında hiçbir şey
 * yapmayan bir liste, sahibi olmayan bir yeteneğe güvendirir.
 *
 * Bu katalog NE ÜRETİLEBİLDİĞİNİ de bilmez ve bilmemeli: "bu sunucu AVIF
 * kodlayabiliyor mu" bir çalışma ortamı sorusudur ve cevabı
 * `MediaFormatSupportPort`tadır. İkisini birleştirmek, kaynağın listesini
 * makineye göre kısaltmak olurdu — oysa liste TAMDIR, eksik olan ürünün
 * yeteneğidir ve fark ekranda dürüstçe yazılır.
 */
final readonly class ConversionTargetCatalogue
{
    /** @param list<ConversionTarget> $targets */
    private function __construct(private array $targets) {}

    public static function canonical(): self
    {
        return new self([
            new ConversionTarget('avif', 'image', 74),
            new ConversionTarget('webp', 'image', 58),
            // VP9/AV1 — kaynağın tek video hedefi.
            new ConversionTarget('webm', 'video', 62),
            // "Her yerde açılan yedek": en büyüğü, ama hiçbir yerde
            // açılmayan bir dosyadan iyidir.
            new ConversionTarget('jpeg', 'image', 40),
        ]);
    }

    /** @return list<ConversionTarget> */
    public function all(): array
    {
        return $this->targets;
    }

    public function find(string $format): ?ConversionTarget
    {
        foreach ($this->targets as $target) {
            if ($target->format === $format) {
                return $target;
            }
        }

        return null;
    }

    public function has(string $format): bool
    {
        return $this->find($format) !== null;
    }
}
