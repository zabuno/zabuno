<?php

declare(strict_types=1);

/**
 * Ölçüm ve gözlem yapılandırması — TEK DİKİŞ YERİ.
 *
 * Sahibin kilit kuralı: Google Analytics, Yandex Metrica, Google Tag Manager,
 * dataLayer, Hotjar ve Metabase ile her şey TENANT BAZINDA analiz edilebilir
 * olmalı.
 *
 * Bunu altı ayrı SDK gömerek yapmayız. Uygulamaya YALNIZ Google Tag Manager
 * girer; GA4, Metrica ve Hotjar GTM konteynerinin içinden yönetilir. Sebebi
 * mühendislik kararıdır, tembellik değil:
 *
 *   - Yeni bir araç eklemek deploy gerektirmez; sahibi GTM arayüzünden ekler.
 *   - Ölçüm sözleşmesi tek yerdedir: `window.dataLayer`. Altı SDK, altı ayrı
 *     olay adlandırması ve altı ayrı tenant alanı demek olurdu; ilk tutarsızlık
 *     günü raporlar birbirini tutmazdı.
 *   - Bir aracı kapatmak konteynerden bir etiketi durdurmaktır; koddan script
 *     sökmek değildir.
 *
 * Metabase bu listede DEĞİLDİR ve olmamalıdır: o tarayıcıda çalışmaz,
 * doğrudan PostgreSQL'i okur. Onun tenant bazlı olması bir script meselesi
 * değil, veri modeli meselesidir (docs/46 §5).
 *
 * Hiçbir kimlik verilmezse ölçüm KAPALIDIR: tek bir script yüklenmez ve CSP
 * bugünkü kadar sıkı kalır. Yerel geliştirme ve testler bu durumdadır.
 */
return [

    /*
     * GTM konteyner kimliği (GTM-XXXXXXX). Boşsa ölçüm tamamen kapalıdır.
     */
    'gtm_container_id' => env('ANALYTICS_GTM_CONTAINER_ID', ''),

    /*
     * Konteynerin İÇİNDEN hangi araçların çalışacağı.
     *
     * Bu liste GTM'i yapılandırmaz — CSP'yi yapılandırır. Bir aracı burada
     * açmazsan tarayıcı onun isteğini engeller ve GTM'de etiketi kurmuş olsan
     * bile veri akmaz. Kapalı tutulan her araç, saldırı yüzeyi olarak da
     * kapalıdır; bu yüzden "hepsini açalım" varsayılan değildir.
     */
    'destinations' => [
        'ga4' => (bool) env('ANALYTICS_GA4_ENABLED', false),
        'yandex_metrica' => (bool) env('ANALYTICS_YANDEX_METRICA_ENABLED', false),
        'hotjar' => (bool) env('ANALYTICS_HOTJAR_ENABLED', false),
    ],

    /*
     * Her aracın tarayıcıdan konuşmak zorunda olduğu adresler, CSP yönergesi
     * bazında. Bunlar araçların yayınlanmış uç noktalarıdır; genişletmek
     * gerekirse buraya yazılır, CSP koduna değil.
     */
    'csp_sources' => [

        'gtm' => [
            'script-src-elem' => ['https://www.googletagmanager.com'],
            'connect-src' => ['https://www.googletagmanager.com'],
            'img-src' => ['https://www.googletagmanager.com'],
        ],

        'ga4' => [
            'script-src-elem' => ['https://www.googletagmanager.com'],
            'connect-src' => [
                'https://www.google-analytics.com',
                'https://analytics.google.com',
                'https://*.analytics.google.com',
                'https://*.google-analytics.com',
            ],
            'img-src' => [
                'https://www.google-analytics.com',
                'https://*.google-analytics.com',
            ],
        ],

        'yandex_metrica' => [
            'script-src-elem' => ['https://mc.yandex.ru', 'https://mc.yandex.com'],
            'connect-src' => ['https://mc.yandex.ru', 'https://mc.yandex.com'],
            'img-src' => ['https://mc.yandex.ru', 'https://mc.yandex.com'],
            // Metrica'nın Webvisor'ı kayıt için bir iframe açar.
            'frame-src' => ['https://mc.yandex.ru', 'https://mc.yandex.com'],
        ],

        'hotjar' => [
            'script-src-elem' => ['https://static.hotjar.com', 'https://script.hotjar.com'],
            'connect-src' => [
                'https://*.hotjar.com',
                'https://*.hotjar.io',
                'wss://*.hotjar.com',
            ],
            'img-src' => ['https://*.hotjar.com'],
            'font-src' => ['https://*.hotjar.com'],
        ],
    ],
];
