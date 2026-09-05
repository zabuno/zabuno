<?php

declare(strict_types=1);

/**
 * Kurumsal sayfaların KAYNAK DİLDEKİ adresleri — `docs/118` E4, `docs/120` §1.
 *
 * ── Çözülen tıkanıklık ───────────────────────────────────────────────────
 *
 * Site haritası girdisi (`docs/106`) Türkçe yollarla yazılmış ve `docs/118`
 * gereği DÜZENLENMEZ: bir girdiyi sonradan düzeltmek, kararın hangi girdiden
 * çıktığını gizlemek olurdu. Kaynak dil ise artık İngilizce. Yani
 * `site:import-map` belgeden İngilizce bir adres OKUYAMAZ ve okuyamamalıdır.
 *
 * ── Neden makineyle türetilmiyor ─────────────────────────────────────────
 *
 * Anahtardan mekanik bir adres üretmek mümkündü: `urun.qr-menu` →
 * `/en/urun/qr-menu/`. Üretilmedi, çünkü o adres İngilizce bir sayfada Türkçe
 * bir segment taşırdı — yani yarım çevrilmiş bir adres. `docs/119` §10.4
 * "URL slug"ı, bir locale sayfası indekslenmeden ÖNCE çevrilmiş, gözden
 * geçirilmiş ve ONAYLANMIŞ olması gereken alanların BAŞINDA sayıyor. Yarım
 * çeviri çevirisizlikten kötüdür ve bu, adreslerde daha da doğrudur: bir
 * adres yayımlandıktan sonra değiştirmek bizim elimizde değildir.
 *
 * Bu yüzden kural şu: **adresi yazılmamış bir sayfanın kaynak dil satırı
 * ÜRETİLMEZ.** Eksik satır, yanlış adresten iyidir.
 *
 * ── Buradaki altı adres nereden geldi ────────────────────────────────────
 *
 * Hiçbiri çeviri değil. Beşinin İNGİLİZCE İÇERİĞİ ZATEN DEPODA yazılı
 * (`App\Infrastructure\Content\ProductPageLibrary`, FF-191) ve kütükte adresi
 * olmadığı için bugün hiçbir yerden açılamıyordu — yazılmış beş sayfa
 * ulaşılamaz duruyordu.
 *
 *   - `urun`, `urun.qr-menu`, `urun.menu-yonetimi` adresleri deponun kendi
 *     testinde (`CorporateProductPageTest`) zaten yazılıydı; buraya taşındı.
 *   - Kalan üçü, o sayfaların KENDİ İngilizce `breadcrumbTitle` alanından
 *     geliyor ("Tables and QR codes", "Analytics", "Zabuno AI"). Yeni bir
 *     metin yazılmadı; var olan İngilizce başlık slug'a indirildi.
 *
 * ── Bu dosya nasıl büyür ─────────────────────────────────────────────────
 *
 * Bir sayfanın İngilizce içeriği yazıldığında adresi buraya EL İLE eklenir ve
 * kod incelemesinden geçer. Otomatik doldurulmaz; doldurulsaydı bu dosyanın
 * var olma sebebi ortadan kalkardı.
 *
 * Öteki dillerin adresleri buraya GİRMEZ. Onlar çeviri günü açılacak ve o gün
 * ayrı bir kayıt katmanı gerekecek (`docs/119` §10.1
 * `content_page_localizations`). Bugün kilit kapalı (`docs/120` §7).
 *
 * @var array<string, string> `page_key` → kaynak dildeki kanonik yol
 */
return [
    'urun' => '/en/product/',
    'urun.qr-menu' => '/en/product/qr-menu/',
    'urun.menu-yonetimi' => '/en/product/menu-management/',
    'urun.masa-ve-qr-yonetimi' => '/en/product/tables-and-qr-codes/',
    'urun.analitik' => '/en/product/analytics/',
    'urun.zabuno-ai' => '/en/product/zabuno-ai/',

    /*
        DALGA 2 (FF-192). Aynı kural, aynı yoldan: hiçbiri çeviri değil.
        Beş sayfanın İngilizce içeriği bu pakette yazıldı ve adres, sayfanın
        KENDİ İngilizce `breadcrumbTitle` alanından slug'a indirildi —
        "Images and media", "Languages and currency", "Multiple branches",
        "Solutions", "Pricing". Yeni bir metin üretilmedi.

        `/en/pricing/` ile bugün yayında olan `/pricing` ÇAKIŞMAZ: kurumsal
        kapı yalnız dil dizini altında çalışır (`routes/web.php`). O adresin
        dil dizinine taşınması ve 301'i ayrı bir paketin işi (`docs/105` §4.1).
    */
    'urun.gorsel-ve-medya' => '/en/product/images-and-media/',
    'urun.coklu-dil-ve-para-birimi' => '/en/product/languages-and-currency/',
    'urun.coklu-sube' => '/en/product/multiple-branches/',
    'cozumler' => '/en/solutions/',
    'fiyatlandirma' => '/en/pricing/',
];
