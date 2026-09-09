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
     * Bu yüzden burada TEK doğrulanmış adres var: GitHub organizasyonu
     * (`https://github.com/zabuno`, MASTER `gh api orgs/zabuno` ile
     * doğruladı — `html_url` ve `login` alanları). Ötekiler `null` ve
     * `null` bir ikon ÇİZDİRMEZ: profil yoksa şeritte yeri de yoktur.
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
        'github' => env('SOCIAL_GITHUB_URL', 'https://github.com/zabuno'),
    ],

];
