<?php

declare(strict_types=1);

use App\Domain\Media\MediaSurface;

/**
 * Slot politikaları — bir görselin NEREDE kullanılacağı ve o yerin ne
 * gerektirdiği.
 *
 * Slot LİSTESİ sahibinin kararıdır ve panelde zaten vardı (2026-08-27).
 * Eksik olan, her slotun ne gerektirdiğiydi: kullanıcı 17 opak ad arasından
 * seçim yapıyor, hangi ölçüde görsel yükleyeceğini hiçbir yerden
 * öğrenemiyordu. Sonuç, menüde bulanık ya da kırpılmış görseldi.
 *
 * Buradaki SAYILAR sahibinin verdiği değerler değil, türetilmiş önerilerdir
 * (`docs/49` §12). Değiştirilebilirler; ama tahminle DEĞİL, bu dosyada.
 *
 * Türetme gerekçesi:
 *   - `min_width`/`min_height`: o slotun en büyük rendition'ından küçük
 *     olamaz. Upscale yasaktır (INV-01), dolayısıyla giriş çıkıştan küçükse
 *     görsel menüde bulanık görünür.
 *   - `aspect`: slotun yerleşimi sabitse kırpma kaçınılmazdır; kullanıcı
 *     hangi oranın korunacağını ÖNCEDEN bilmelidir.
 *   - `renditions`: `docs/49` §5.4 — 320w ilk sırada, çünkü 320px-first.
 */
return [

    /*
     * Yüzey ayrımı — `docs/50`'nin "3 Neden" kapısı.
     *
     * Bir restoran sahibinin görsel yüklerken "Pricing" ya da "Testimonial"
     * seçeneği görmesinin sebebi yoktu: bunlar ZABUNO'NUN KENDİ tanıtım
     * sitesinin slotları. Panelde yalnız kendi yüzeyinin slotları görünür.
     */
    'slots' => [

        // ── Restoran paneli ──────────────────────────────────────────────
        'logo' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 512, 'min_height' => 512,
            'aspect' => null,               // logo kırpılmaz
            'formats' => ['png', 'svg', 'webp'],
            'transparency' => 'preserve',   // alfa düz beyaza çevrilmez (INV-07)
            'renditions' => [64, 128, 256, 512],
            'alt_required' => true,
        ],
        'cover' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 1920, 'min_height' => 640,
            'aspect' => '3:1',
            'formats' => ['jpeg', 'png', 'webp', 'avif'],
            'transparency' => 'flatten',
            'renditions' => [320, 640, 960, 1280, 1600, 1920],
            'alt_required' => true,
        ],
        'categoryHero' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 1280, 'min_height' => 640,
            'aspect' => '2:1',
            'formats' => ['jpeg', 'png', 'webp', 'avif'],
            'transparency' => 'flatten',
            'renditions' => [320, 640, 960, 1280],
            'alt_required' => true,
        ],
        // Menünün en çok kullanılan slotu. 1:1, çünkü liste ve kart
        // yerleşimlerinin ikisinde de aynı görsel kullanılır.
        'itemImage' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 1000, 'min_height' => 1000,
            'aspect' => '1:1',
            'formats' => ['jpeg', 'png', 'webp', 'avif', 'heic'],
            'transparency' => 'flatten',
            'renditions' => [320, 480, 640, 960],
            'alt_required' => true,
        ],
        'gallery' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 1000, 'min_height' => 1000,
            'aspect' => '1:1',
            'formats' => ['jpeg', 'png', 'webp', 'avif', 'heic'],
            'transparency' => 'flatten',
            'renditions' => [320, 480, 640, 960],
            'alt_required' => true,
        ],
        'profileAvatar' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 200, 'min_height' => 200,
            'aspect' => '1:1',
            'formats' => ['jpeg', 'png', 'webp'],
            'transparency' => 'flatten',
            'renditions' => [64, 128, 200],
            'alt_required' => false,        // avatar dekoratiftir
        ],
        // Basılan QR sayfasında kullanılır; vektör tercih edilir çünkü
        // baskı çözünürlüğü ekrandan bağımsızdır.
        'printLogo' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 1024, 'min_height' => 1024,
            'aspect' => null,
            'formats' => ['svg', 'png'],
            'transparency' => 'preserve',
            'renditions' => [512, 1024, 2048],
            'alt_required' => false,
        ],
        'ogImage' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 1200, 'min_height' => 630,
            'aspect' => '1.91:1',           // Open Graph'ın beklediği oran
            'formats' => ['jpeg', 'png', 'webp'],
            'transparency' => 'flatten',
            'renditions' => [600, 1200],
            'alt_required' => true,
        ],
        'favicon' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 512, 'min_height' => 512,
            'aspect' => '1:1',
            'formats' => ['png', 'svg'],
            'transparency' => 'preserve',
            'renditions' => [16, 32, 180, 512],
            'alt_required' => false,
        ],
        'appIcon' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 1024, 'min_height' => 1024,
            'aspect' => '1:1',
            'formats' => ['png'],
            'transparency' => 'flatten',    // iOS şeffaf ikon kabul etmez
            'renditions' => [192, 512, 1024],
            'alt_required' => false,
        ],
        'emailHeader' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 1200, 'min_height' => 400,
            'aspect' => '3:1',
            'formats' => ['jpeg', 'png'],   // e-posta istemcileri webp/avif desteklemez
            'transparency' => 'flatten',
            'renditions' => [600, 1200],
            'alt_required' => true,
        ],

        // ── Zabuno'nun kendi tanıtım sitesi ──────────────────────────────
        // Restoran panelinde GÖRÜNMEZ.
        'hero' => [
            'surface' => MediaSurface::Marketing,
            'min_width' => 1920, 'min_height' => 1080,
            'aspect' => '16:9',
            'formats' => ['jpeg', 'png', 'webp', 'avif'],
            'transparency' => 'flatten',
            'renditions' => [320, 640, 960, 1280, 1600, 1920],
            'alt_required' => true,
        ],
        'cards' => [
            'surface' => MediaSurface::Marketing,
            'min_width' => 800, 'min_height' => 600,
            'aspect' => '4:3',
            'formats' => ['jpeg', 'png', 'webp', 'avif'],
            'transparency' => 'flatten',
            'renditions' => [320, 480, 800],
            'alt_required' => true,
        ],
        'pricing' => [
            'surface' => MediaSurface::Marketing,
            'min_width' => 800, 'min_height' => 600,
            'aspect' => '4:3',
            'formats' => ['jpeg', 'png', 'webp', 'avif'],
            'transparency' => 'flatten',
            'renditions' => [320, 480, 800],
            'alt_required' => true,
        ],
        'features' => [
            'surface' => MediaSurface::Marketing,
            'min_width' => 800, 'min_height' => 600,
            'aspect' => '4:3',
            'formats' => ['jpeg', 'png', 'webp', 'avif'],
            'transparency' => 'flatten',
            'renditions' => [320, 480, 800],
            'alt_required' => true,
        ],
        'testimonial' => [
            'surface' => MediaSurface::Marketing,
            'min_width' => 400, 'min_height' => 400,
            'aspect' => '1:1',
            'formats' => ['jpeg', 'png', 'webp'],
            'transparency' => 'flatten',
            'renditions' => [128, 256, 400],
            'alt_required' => true,
        ],
        'avatar' => [
            'surface' => MediaSurface::Marketing,
            'min_width' => 200, 'min_height' => 200,
            'aspect' => '1:1',
            'formats' => ['jpeg', 'png', 'webp'],
            'transparency' => 'flatten',
            'renditions' => [64, 128, 200],
            'alt_required' => false,
        ],
    ],

    /*
     * Bütün slotlar için ortak üst sınırlar.
     *
     * `max_megapixels` bir güvenlik sınırıdır, bir kalite tercihi değil:
     * decompression bomb tam olarak burada durdurulur ve dosya DECODE
     * EDİLMEDEN reddedilir (`docs/49` Faz 2).
     */
    'limits' => [
        'max_bytes' => 30 * 1024 * 1024,
        'max_megapixels' => 40,
        'max_frames' => 1,              // animasyon Faz 2'de açılır
    ],
];
