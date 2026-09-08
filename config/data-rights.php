<?php

declare(strict_types=1);

return [

    /*
     * KVKK HAKLARI ÜRÜN İÇİNDE — FF-226 (`docs/107` Faz 3.3, `docs/138`).
     *
     * Buradaki iki süre de ÜRÜN kararıdır, hukuki bir saklama süresi
     * DEĞİLDİR ve öyle sunulmaz. Yasal saklama süreleri sahibin hukuki
     * incelemesinden gelir ve bu depo onları uydurmaz (`docs/124` §6.4,
     * `docs/131` §12.6).
     */
    'erasure' => [

        /*
         * SİLME GECİKMELİ VE GERİ ALINABİLİR.
         *
         * Silme geri alınamaz; o yüzden istekle yürütme arasında bir pencere
         * vardır ve pencere boyunca istek tek düğmeyle iptal edilir. Sıfır
         * gün, "emin misiniz?" kutusuna basmış birinin bütün menüsünü aynı
         * saniyede kaybetmesi demekti.
         *
         * Değer ortamdan gelir ve OKUNAMAYAN bir değer sonsuz değil
         * varsayılandır: bir yazım hatası pencereyi kapatıp silmeyi anında
         * yürütecek olsaydı, yapılandırma hatası veri kaybına dönüşürdü.
         * Alt sınır 1 gündür — "gecikmeli" sözü bir yapılandırmayla
         * kaldırılamaz.
         */
        'grace_days' => (int) env('DATA_RIGHTS_ERASURE_GRACE_DAYS', 30),

    ],

    'export' => [

        /*
         * ÇIKTININ ÖMRÜ.
         *
         * Dışa aktarma arşivi çalışma alanının BÜTÜN verisini tek dosyada
         * taşır; sunucuda sonsuza kadar durması, silme hakkının yanında
         * duran sessiz bir kopya olurdu. Süre dolduğunda bağlantı çalışmaz
         * ve dosya bir sonraki bakım koşusunda diskten silinir.
         */
        'available_days' => (int) env('DATA_RIGHTS_EXPORT_AVAILABLE_DAYS', 7),

        /*
         * İmzalı indirme adresinin ömrü — dakika. Arşivin kendi ömrüyle
         * karıştırılmaz: bu, kopyalanan bir adresin başkasının elinde ne
         * kadar yaşadığıdır (`CreateOriginalDownloadLinkController` ile
         * aynı desen).
         */
        'link_minutes' => (int) env('DATA_RIGHTS_EXPORT_LINK_MINUTES', 10),

        /*
         * Arşivin yazıldığı disk. `local` özel disktir: dosyanın herkese
         * açık bir adresi YOKTUR ve yalnız imzalı uçtan iner.
         */
        'disk' => env('DATA_RIGHTS_EXPORT_DISK', 'local'),

    ],

    /*
     * BARINDIRMA OLGUSU — ölçüldü (2026-09-08): sunucu Almanya'da.
     *
     * Yasal metinler bunu söylemek ZORUNDA (`docs/138` §7): KVKK
     * aydınlatması bugün yalnız sağlayıcıların yurt dışı olabileceğini
     * söylüyordu, verinin kendisinin nerede durduğunu değil. Değer
     * yapılandırmadan gelir çünkü sunucu taşınırsa metin de taşınmalı ve
     * bunu koda gömmek, taşındığı gün yalan söyleyen bir cümle bırakırdı.
     */
    'hosting' => [
        'country' => env('DATA_HOSTING_COUNTRY', 'Germany'),
        'provider' => env('DATA_HOSTING_PROVIDER', 'netcup GmbH'),
    ],

];
