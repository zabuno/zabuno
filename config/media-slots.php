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
            /*
                SVG AÇIK — sahibin kararı, 2026-09-05 (`docs/108` §6.2).

                2026-09-04'e kadar burada "SVG yok" yazıyordu ve gerekçesi
                doğruydu: SVG bir görsel değil BELGEDİR, içinden betik
                çalışır ve menü sayfaları herkese açıktır. O gerekçe
                kalkmadı — KARŞILANDI. Sahip "şimdi aç" dedi ve kabul,
                temizleyiciyle AYNI pakette açıldı: `App\Domain\Media\
                SvgSanitizer` (allowlist; betik, olay özniteliği, dış
                bağlantı, gömülü HTML ve XML varlığı taşıyan gövde
                REDDEDİLİR — temizlenip kabul edilmez).

                Vektör YALNIZ vektör slotlarındadır: `logo`, `printLogo`,
                `favicon`. Bir yemek fotoğrafı slotunda (`itemImage`) SVG
                yoktur, çünkü orada vektör diye bir şey yoktur.
            */
            'formats' => ['svg', 'png', 'webp'],
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
        /*
            AI FOTOĞRAF İÇE AKTARMA KAYNAĞI — `docs/92`/`docs/97` Yolculuk A.

            Diğer Menu slotları GÖSTERİM içindir (misafirin göreceği kart/
            liste görseli) — bu yüzden sıkı en-boy oranı ve minimum
            piksel taşırlar. Bu slot bir ÇALIŞMA BELGESİDİR: elle tutulan
            bir kâğıt menünün fotoğrafı, ekran görüntüsü, taranmış PDF
            sayfası. Sabit oran dayatmak geçerli bir kaynak fotoğrafı
            reddedebilirdi (`docs/51` §4b.1: "resim, fotoğraf, grafik").
            `alt_required: false` — bu görsel misafire hiç gösterilmez.
        */
        'menuImportSource' => [
            'surface' => MediaSurface::Menu,
            'min_width' => 600, 'min_height' => 600,
            'aspect' => null,
            'formats' => ['jpeg', 'png', 'webp', 'heic'],
            'transparency' => 'flatten',
            'renditions' => [960],
            'alt_required' => false,
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
            // Baskıda vektör tek doğru cevaptır: kâğıt, ekranın piksel
            // ızgarasını tanımaz (SVG kararı: bkz. `logo` slotu).
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
            'formats' => ['svg', 'png'],    // SVG kararı: bkz. `logo` slotu
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
     * ADLANDIRILMIŞ TÜREV KURALLARI — "Boyut motoru" (kanonik kaynak:
     * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran
     * etiketi "Boyut motoru"; somut tablo `docs/108` §6.1).
     *
     * NEDEN AYRI BİR BÖLÜM, slot `renditions` listesinin İÇİ DEĞİL:
     *
     *   - Slot `renditions` listesi bir SLOTUN ihtiyacıdır: "menü ürünü
     *     kartı 320'den 960'a kadar dört ölçü ister". O liste bir yükleme
     *     kapısıdır (`min_width` onun en büyüğünden türer) ve çalışan bir
     *     boru hattını besliyor. Oraya ad yazmak, var olan slot davranışını
     *     kırardı.
     *   - Bu bölüm ise ÜRÜNÜN ölçü dağarcığıdır: `320` bir sayıdır,
     *     `small · menü kartı · telefon` bir karardır. Kural değiştiğinde
     *     hangi ekranın etkileneceğini yalnız ikincisi söyler.
     *
     * Kural DOMAIN'de okunur (`App\Domain\Media\DerivativeCatalogue`) —
     * yapılandırma çerçeveye, karar domain'e aittir; `SlotPolicy` ile aynı
     * ayrım.
     *
     * DÜRÜSTLÜK NOTU. Bu ad dağarcığı bugün boru hattına BAĞLI DEĞİLDİR:
     * `GdMediaAssetProcessor` hâlâ slot `renditions` listesinden üretiyor.
     * Bu yüzden uç, her kuralın hangi slotlarda GERÇEKTEN üretildiğini de
     * söyler ve ekran bunu gizlemez. Adlandırılmış kuralı boru hattına
     * bağlamak ayrı bir pakettir; onu sessizce yapmak, on binlerce var olan
     * türevi habersiz geçersizleştirirdi.
     *
     * DEĞİŞMEZ (`docs/108` §4): buradaki bir değişiklik YALNIZ YENİ
     * yüklemelere uygulanır. Eskiler ancak açık bir yeniden üretim işiyle
     * değişir ve o iş asılları korur, yeni SÜRÜM açar.
     */
    'derivatives' => [
        'thumb' => [
            'width' => 160,
            // Liste satırı sabit kare bir kutudur; sığdırmak boşluk bırakır.
            'fit' => 'crop',
            'height' => null,
            'formats' => ['avif', 'webp'],
        ],
        'small' => [
            'width' => 320,
            'fit' => 'contain',
            'height' => null,
            'formats' => ['avif', 'webp'],
        ],
        'medium' => [
            'width' => 768,
            'fit' => 'contain',
            'height' => null,
            // JPEG buradan itibaren yedektir: ürün ayrıntısı sayfası eski
            // bir tarayıcıda da açılmalı.
            'formats' => ['avif', 'webp', 'jpeg'],
        ],
        'large' => [
            'width' => 1440,
            'fit' => 'contain',
            'height' => null,
            'formats' => ['avif', 'webp', 'jpeg'],
        ],
        'social' => [
            // Paylaşım önizlemesinin çerçevesi SABİTTİR ve bizim değil:
            // 1200×630'u paylaşılan yer dayatır, sığdırma bir seçenek değil.
            'width' => 1200,
            'fit' => 'crop',
            'height' => 630,
            // AVIF/WebP yok: paylaşım kazıyıcılarının çoğu okumaz.
            'formats' => ['jpeg'],
        ],
        'print' => [
            'width' => 2480,
            'fit' => 'contain',
            'height' => null,
            'formats' => ['jpeg'],
        ],
    ],

    /*
     * TOPLU YENİDEN ÜRETİM sınırı.
     *
     * Tek çağrı, kaç dosyayı işleyebilir? Bu bir performans ayarı değil bir
     * DÜRÜSTLÜK sınırıdır: yeniden üretim SENKRONDUR (`ReprocessMediaAsset`
     * çağrıldığı istekte görsel işler). Sınırsız bir toplu iş, iki yüz
     * fotoğraflu bir kiracıda isteği zaman aşımına uğratır ve sahip işin
     * yarıda kaldığını hiçbir yerden öğrenemez.
     *
     * Sınıra takılan kalan dosyalar cevapta SAYILIR; sahip düğmeye yeniden
     * basar ve kaldığı yerden devam eder.
     */
    'regeneration' => [
        'batch_limit' => 25,
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
        // Çöpte bekleme süresi (`docs/49` Faz 5, kota kararı `docs/98` §7):
        // silmek boş alan açmalı ama yanlış silmenin de bir geri yolu olmalı.
        'trash_retention_days' => 30,
    ],
];
