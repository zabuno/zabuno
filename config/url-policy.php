<?php

declare(strict_types=1);

/**
 * URL politikasının TEK kanonik kaynağı — URL motoru bunu okur.
 *
 * Bu dosya bir "ayar" dosyası değil, bir **sözleşmedir**. Bir URL bir kez
 * yayımlandığında (basılı QR, paylaşılan bağlantı, dış site linki) onu
 * değiştirmek bizim elimizde değildir. Bu yüzden kural kodun içine dağılmaz,
 * burada tek yerde durur ve `tests/Feature/Url` altındaki kapılar onu zorlar.
 *
 * Kaynak: `docs/38-URL-POLICY.md`. O belge NEDENİ, bu dosya DEĞERİ taşır;
 * ikisi birbirini tekrar etmez.
 */
return [
    /*
     * Kanonik şema ve host.
     *
     * `enforce_host` varsayılan olarak KAPALIDIR ve bu bilinçlidir: aynı kod
     * netcup, Hetzner ve üç ayrı paylaşımlı barındırmada çalışacak. Host'u
     * koda gömmek, taşınabilirliği kaybetmektir. Üretimde `.env` ile açılır.
     */
    'canonical_scheme' => env('URL_CANONICAL_SCHEME', 'https'),

    /*
     * Uygulamanın hangi Host başlıklarına cevap vereceği.
     *
     * Laravel varsayılan olarak isteğin kendi Host başlığına güvenir. Bu
     * başlık İSTEMCİ tarafından gönderilir: sahte bir Host ile gelen bir
     * istek, ürettiğimiz kanonik adresi ve imzalı bağlantıları saldırganın
     * alan adına kaydırabilir. Doğrulama e-postasındaki bağlantı o alan
     * adına giderse, kullanıcı kimlik bilgisini oraya yazar.
     *
     * Boş bırakılırsa yalnız `APP_URL`'in host'u kabul edilir; bu, beş ayrı
     * barındırıcıda çalışan tek bir yapının güvenli varsayılanıdır
     * (`docs/38` §8). Ek alan adları virgülle eklenir.
     */
    'trusted_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('URL_TRUSTED_HOSTS', '')),
    ))),
    'canonical_host' => env('URL_CANONICAL_HOST'),
    'enforce_scheme' => (bool) env('URL_ENFORCE_SCHEME', false),
    'enforce_host' => (bool) env('URL_ENFORCE_HOST', false),

    /*
     * Yol normalizasyonu.
     *
     * `//a//b` ve `/a/b/` aynı içeriği farklı adresten sunar; arama motoru
     * için iki ayrı sayfa, önbellek için iki ayrı anahtardır.
     */
    'collapse_duplicate_slashes' => true,
    'trailing_slash' => 'never_except_root',

    /*
     * BÜYÜK/KÜÇÜK HARF — bu ürünün en tehlikeli kuralı.
     *
     * Yaygın tavsiye "tüm path'i küçük harfe indir ve 301 at" der. Burada bu
     * kural UYGULANAMAZ: QR token'ı `[A-Za-z0-9_-]{43}` biçimindedir ve
     * büyük/küçük harfe DUYARLIDIR. Path'i toptan küçük harfe indiren bir
     * kural, basılmış her QR kodunu sessizce çöpe atar — masadaki kod
     * değişmez, ama artık hiçbir menüye gitmez.
     *
     * Bu yüzden harf katlama yalnız burada sayılan STATİK öneklerde yapılır.
     * Yeni bir statik yol eklenirken buraya da eklenir; token taşıyan hiçbir
     * yol buraya EKLENMEZ.
     */
    'lowercase_prefixes' => [
        'terms',
        'privacy',
        'kvkk',
        'app',
        'platform',
        'engineering',
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'verify-email',
    ],

    /*
     * Opak kimlik taşıyan yollar. Harf katlama, kesme veya "temizleme"
     * bunlara asla uygulanmaz.
     */
    'opaque_prefixes' => [
        'q',
        'menu',
        // Görsel türev adresleri sağlama toplamı taşır; harf katlama ya da
        // "temizleme" onları bozar (`docs/76`).
        'media',
        'invitations',
        'email',
        'api',
    ],

    /*
     * Yinelenen sorgu anahtarı (`?sort=a&sort=b`).
     *
     * PHP sonuncuyu alır, bazı ara katmanlar ilkini alır. Bu fark tek başına
     * bir güvenlik açığıdır: iki katman aynı isteği farklı okur. Sessizce
     * bir tanesini seçmek yerine isteği reddederiz.
     */
    'duplicate_query_keys' => 'reject',

    /*
     * İzleme parametreleri.
     *
     * Bunlar bir yönlendirme TETİKLEMEZ — yönlendirmek, ölçümü daha okunmadan
     * silmek olurdu. Yalnız canonical adresin DIŞINDA bırakılırlar.
     */
    'tracking_parameters' => [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'msclkid',
        'ref',
    ],

    /*
     * Slug olarak kullanılamayacak kelimeler. Bir işletme kendine `api` veya
     * `admin` slug'ı alırsa, o yol artık iki şey birden ifade eder.
     */
    /*
        Tür segmentleri REZERVEDİR (FF-116, `docs/105` §4.4). Bir işletme
        `restoran` slug'ını alırsa `/restoran/restoran/menu/...` gibi
        çözülemeyen adresler doğar. Liste `BusinessType::allSegments()` ile
        eşleşmek zorunda ve bunun testi var.
    */
    'reserved_slugs' => [
        'admin', 'api', 'app', 'assets', 'build', 'dish', 'email', 'forgot-password',
        'contact', 'health', 'help', 'invitations', 'kvkk', 'login', 'logout', 'media',
        'en', 'menu', 'platform', 'engineering', 'pricing', 'restaurant', 'restoran',
        /*
            TASLAK ÖNİZLEMESİ (`/menu-preview/{workspace}/{menu}`, sahibin
            2026-09-05 "Telefonda önizle" kararı). Bir işletme bu slug'ı
            alabilseydi, kendi sayfası ile sahibin imzalı önizleme adresi
            aynı kökü paylaşırdı — ve o kök, misafire asla gösterilmemesi
            gereken yayınlanmamış fiyatları taşıyor.
        */
        'menu-preview',
        'privacy', 'q', 'register', 'reset-password', 'robots.txt', 'sanctum',
        // Dil dizinleri de rezerve: kurumsal site `/tr/` ve `/en/` altında
        // yaşıyor ve bir kiracı slug'ı o kökü gölgeleyemez (FF-121).
        'sitemap.xml', 'storage', 'terms', 'tr', 'up', 'urun', 'user', 'verify-email', 'www',
    ],

    /*
     * Arama motoruna kapalı yüzeyler.
     *
     * İki ayrı mekanizmayla korunur ve ikisi AYNI ŞEY DEĞİLDİR:
     * `robots.txt` TARAMAYI engeller, `X-Robots-Tag: noindex` SONUÇLARDA
     * GÖRÜNMEYİ engeller. Yalnız robots.txt yetmez — başka bir yerden link
     * verilmiş bir adres taranmadan da indekslenebilir. Yalnız noindex de
     * yetmez — bot onu okuyabilmek için sayfayı çekebilmelidir.
     *
     * Gerçek koruma kimlik doğrulamadır; bu liste onun yerine geçmez.
     */
    'noindex_prefixes' => [
        'app',
        'platform',
        'engineering',
        'api',
        'q',
        'invitations',
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'verify-email',
    ],

    /*
     * `robots.txt` içinde TARAMASI engellenen yollar.
     *
     * Bu liste yukarıdakinin aynısı DEĞİLDİR ve fark bilinçlidir.
     *
     * Kimlik korumalı yüzeyler (`app`, `platform`, `api`, `login` …) burada
     * yer alır: bot zaten içeriği göremez, taramak yalnız bütçe harcar.
     *
     * `q` ise BİLEREK burada YOKTUR. Herkese açık ve gerçekten yanıt veren
     * bir yönlendiricidir. `Disallow` edilseydi bot sayfayı hiç çekemez,
     * dolayısıyla `X-Robots-Tag: noindex` başlığını da OKUYAMAZDI — ve
     * başka bir yerden link verilmiş bir `/q/...` adresi içeriksiz biçimde
     * yine de indekslenebilirdi. Taranmasına izin verip "gösterme" demek,
     * hiç taratmamaktan daha güvenilirdir.
     */
    'disallow_prefixes' => [
        'app',
        'platform',
        'engineering',
        'api',
        'invitations',
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'verify-email',
    ],

    /*
     * Kanonik biçime yönlendirme kodu. 301 kalıcıdır ve önbelleklenir; bu
     * yüzden yalnız GERÇEKTEN kalıcı olan normalizasyonda kullanılır.
     * QR çözümleyicisi bunu KULLANMAZ (bkz. `docs/38` §4).
     */
    'normalization_redirect_status' => 301,
];
