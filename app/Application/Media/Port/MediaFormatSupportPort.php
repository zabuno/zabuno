<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

/**
 * "BU SUNUCU bu biçimi gerçekten üretebiliyor mu?"
 *
 * Kaynağın hedef listesi TAMDIR (`docs/108` §6.3): AVIF, WebP, WebM, JPEG.
 * Ürünün yeteneği tam DEĞİLDİR ve ortamdan ortama değişir — AVIF desteği
 * PHP sürümüne ve GD derlemesine bağlıdır, video dönüştürmek içinse depoda
 * hiçbir hat yoktur.
 *
 * Bu port o farkı SORAR. Varsaymak, sahibin düğmeye basıp yalnız
 * başarısızlık toplaması demek olurdu; hedefi gizlemek ise kaynağın
 * kararını sessizce kısaltmak olurdu. Doğrusu üçüncüsü: hedef gösterilir,
 * yanında "bu kurulumda yapılamıyor" yazar.
 */
interface MediaFormatSupportPort
{
    public function supports(string $format): bool;

    /**
     * Desteklenmiyorsa NEDEN — makinece okunan bir sebep kodu, sahibin
     * okuyacağı cümle değil.
     *
     * Cümle çeviri kataloğunda durur: sunucu sebebi bilir, o sebebi hangi
     * dilde nasıl anlatacağını ürün bilir (`docs/37`).
     *
     * Sebepler:
     *   - `encoder-missing`  — biçim biliniyor ama bu GD derlemesi onu
     *                          kodlayamıyor.
     *   - `no-video-pipeline`— ürünün video dönüştüren bir hattı yok.
     *   - `unknown-format`   — kaynağın listesinde olmayan bir ad.
     */
    public function limitation(string $format): ?string;
}
