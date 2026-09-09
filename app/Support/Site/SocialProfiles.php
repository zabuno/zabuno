<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * Kabuğun sosyal şeridi — DOĞRULANMIŞ adresler, ölü ikon YOK (C3).
 *
 * ═══ NEDEN BİR SINIF, NEDEN ŞABLONDA DEĞİL ═══
 *
 * Şerit üç yerde çiziliyor (üst çubuk menüsü, dar altbilgi, geniş
 * altbilgi). Adres listesini üç şablona yazmak, bir profil eklendiğinde
 * ikisinde görünüp birinde görünmemesiyle biterdi — `SiteNavigation` ile
 * aynı gerekçe.
 *
 * ═══ İKİ SÜZGEÇ, İKİSİ DE PAZARLIĞA KAPALI ═══
 *
 * 1. **Adres HTTPS olmalı.** Yapılandırmadan gelen bir dize doğrudan
 *    `href`e yazılıyor; şema süzülmezse `javascript:` ya da `data:` ile
 *    başlayan bir değer, sahibin `.env` dosyasını bir betik yüzeyine
 *    çevirirdi. `http:` de girmez: dış bir profile şifresiz gitmek,
 *    ziyaretçinin bağlantısını dinleyene tıklamayı gösterir.
 * 2. **Platformun bir glifi olmalı.** İkon `x-phosphor` haritasından gelir
 *    ve haritada olmayan bir ad, çizim anında bir PHP hatasıdır. Süzgeç o
 *    hatayı bir üretim sayfasında değil, burada bitirir.
 *
 * Girilmemiş bir profil `null`dır ve `null` HİÇ çizilmez: eksik bir profil
 * şeritte yer kaplamaz. Şirket kimliğinde karar tersiydi — orada eksik alan
 * "girilmedi" diye yazılır — ve ayrım bilinçli: kanun kimlik alanlarını
 * saymayı emreder, bir sosyal profili ise kimse aramaz.
 */
final class SocialProfiles
{
    /**
     * Platform adı → `x-phosphor` glif adı ve erişilebilir etiketin
     * katalog anahtarı.
     *
     * @var array<string, array{icon: string, labelKey: string}>
     */
    private const PLATFORMS = [
        'github' => ['icon' => 'github-logo', 'labelKey' => 'socialGithub'],
    ];

    /**
     * Bugün gerçekten çizilecek bağlantılar.
     *
     * @param  array<string, string>  $siteText  `SiteText::all()` çıktısı.
     * @return list<array{id: string, url: string, icon: string, label: string}>
     */
    public static function forShell(array $siteText): array
    {
        /** @var array<string, mixed> $configured */
        $configured = (array) config('social.profiles', []);

        $links = [];

        foreach (self::PLATFORMS as $id => $platform) {
            $url = $configured[$id] ?? null;

            if (! is_string($url) || ! str_starts_with(trim($url), 'https://')) {
                continue;
            }

            $links[] = [
                'id' => $id,
                'url' => trim($url),
                'icon' => $platform['icon'],
                'label' => $siteText[$platform['labelKey']] ?? $platform['labelKey'],
            ];
        }

        return $links;
    }
}
