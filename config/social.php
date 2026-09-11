<?php

declare(strict_types=1);

return [

    /*
     * SOSYAL PROFİLLER — YALNIZ DOĞRULANMIŞ ADRESLER (C3).
     *
     * Bir ikon şeridi tanıtım sitelerinin en kolay uydurulan parçasıdır:
     * altı platform ikonu çizilir, dördü hiçbir yere gitmez. Ölçülen sonuç
     * ziyaretçi için tek yönlüdür — tıklar, 404 görür ve ürünün geri
     * kalanına da o gözle bakar.
     *
     * Bu yüzden burada TEK doğrulanmış adres var. Ötekiler `null` ve
     * `null` bir ikon ÇİZDİRMEZ: profil yoksa şeritte yeri de yoktur.
     *
     * ── ADRES DEĞİŞTİ, İKON DEĞİŞMEDİ (sahibin kararı, 2026-09-11) ──
     *
     * Şerit artık ürünün GitHub organizasyonuna değil, ürünü YAPANA
     * gidiyor: `https://atonota.com`. Glif GitHub logosu olarak kalıyor —
     * bu bilinçli bir kısayol, "yapımcı" işaretinin yaygın olarak tanınan
     * biçimi. Kabul edilen bedel şu: logo artık gideceği yerin ADI değil,
     * bağlantının TÜRÜ için duruyor. Bu yüzden bağlantının erişilebilir
     * adı "GitHub" demiyor, kişinin adını söylüyor (`SocialProfiles`) —
     * ikonun ima ettiğiyle bağlantının götürdüğü yer ayrışmasın.
     *
     * Kendi kurulumunu yapan biri kendi adresini `.env`'e yazar
     * (`SAAS-DOMAIN` ile aynı gerekçe) ve o gün ikon kendiliğinden belirir.
     * Adres HTTPS olmak zorunda: `SocialProfiles` başka bir şemayı hiç
     * çizmez — bir `javascript:` ya da `http:` adresi, yapılandırmadan
     * gelen bir bağlantıyı bir güvenlik yüzeyine çevirirdi.
     *
     * İKON ŞARTI: her platformun `x-phosphor` haritasında bir glifi olmak
     * zorunda. Glifi olmayan bir platform buraya EKLENMEZ — ikonsuz bir
     * sosyal bağlantı, şeridin okunmasını sağlayan tek işareti kaybeder.
     */
    'profiles' => [
        'github' => env('SOCIAL_GITHUB_URL', 'https://atonota.com'),
    ],

];
