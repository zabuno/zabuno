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
    'reserved_slugs' => [
        'admin', 'api', 'app', 'assets', 'build', 'email', 'forgot-password',
        'health', 'invitations', 'kvkk', 'login', 'logout', 'menu', 'platform',
        'privacy', 'q', 'register', 'reset-password', 'robots.txt', 'sanctum',
        'sitemap.xml', 'storage', 'terms', 'up', 'user', 'verify-email', 'www',
    ],

    /*
     * Kanonik biçime yönlendirme kodu. 301 kalıcıdır ve önbelleklenir; bu
     * yüzden yalnız GERÇEKTEN kalıcı olan normalizasyonda kullanılır.
     * QR çözümleyicisi bunu KULLANMAZ (bkz. `docs/38` §4).
     */
    'normalization_redirect_status' => 301,
];
