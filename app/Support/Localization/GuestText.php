<?php

declare(strict_types=1);

namespace App\Support\Localization;

use App\Application\Localization\Port\TranslationPort;

/**
 * Misafir sayfasının metinleri — `docs/82`.
 *
 * Bu sayfanın dili UYGULAMANIN değil RESTORANIN dilidir: menüyü okuyan kişi
 * masadadır ve ürün adları zaten restoranın dilindedir. Bu yüzden metin,
 * yayının taşıdığı içerik diliyle çözülür.
 *
 * `guest` alanının KAYNAK DİLİ Türkçedir (`resources/js/i18n/guest.ts`
 * içindeki gerekçe): kaynağı İngilizce yapmak, çeviri dosyası doldurulana
 * kadar Türk bir restoranın menüsünde İngilizce bir cümle gösterirdi.
 */
final class GuestText
{
    private const DOMAIN = 'guest';

    public function __construct(private readonly TranslationPort $translations) {}

    /** @param  array<string, string>  $params */
    public function get(string $key, ?string $locale = null, array $params = []): string
    {
        return $this->translations->translate(
            self::DOMAIN,
            $key,
            $locale !== null && trim($locale) !== '' ? $locale : 'tr',
            $params,
        );
    }

    /**
     * Sayfanın ihtiyaç duyduğu bütün metinler, TEK seferde.
     *
     * Şablonda tek tek çağırmak yerine bir harita verilmesinin sebebi, bir
     * testin "şablonda sabit kullanıcı metni yok" kuralını dondurabilmesi:
     * metin şablonda değil, burada yaşar (`docs/85`).
     *
     * @return array<string, string>
     */
    public function all(string $locale, int $categoryCount = 0, int $itemCount = 0): array
    {
        $keys = [
            'soldOut' => 'guest.menu.item.soldOut',
            'subtitle' => 'guest.menu.subtitle',
            'categoriesLabel' => 'guest.menu.categories.label',
            'menuEmpty' => 'guest.menu.empty',
            'categoryEmpty' => 'guest.menu.category.empty',
            'searchLabel' => 'guest.search.label',
            'searchPlaceholder' => 'guest.search.placeholder',
            'searchNoMatch' => 'guest.search.noMatch',
            'installButton' => 'guest.pwa.install',
            'installAccepted' => 'guest.pwa.installAccepted',
            'installDismissed' => 'guest.pwa.installDismissed',
            'installed' => 'guest.pwa.installed',
            'offline' => 'guest.pwa.offline',
            'languageLabel' => 'guest.language.label',
            'contentNotice' => 'guest.language.contentNotice',
        ];

        $out = [];

        foreach ($keys as $name => $key) {
            $out[$name] = $this->get($key, $locale);
        }

        $out['summary'] = $this->get('guest.menu.summary', $locale, [
            'categories' => (string) $categoryCount,
            'items' => (string) $itemCount,
        ]);

        // `{count}` YERİNDE bırakılır: sayı istemcide, arama sonucuna göre
        // değişiyor ve sunucu onu bilemez.
        $out['searchMatched'] = $this->get('guest.search.matched', $locale);

        return $out;
    }
}
