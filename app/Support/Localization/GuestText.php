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
            'allergensLabel' => 'guest.menu.allergens.label',
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

    /**
     * Çıkmaz sokak sayfasının metinleri (FF-98).
     *
     * Menü metinlerinden AYRI bir harita: o sayfada menü yoktur, dolayısıyla
     * "kategori boş" ya da "arama" gibi anahtarları yüklemek, olmayan bir
     * ekranın sözlüğünü taşımak olurdu.
     *
     * @return array<string, string>
     */
    public function deadEnd(?string $locale = null): array
    {
        return [
            'title' => $this->get('guest.deadEnd.title', $locale),
            'heading' => $this->get('guest.deadEnd.heading', $locale),
            'body' => $this->get('guest.deadEnd.body', $locale),
        ];
    }

    /**
     * SERVİS DIŞI SAAT (FF-139) — çıkmaz sokaktan AYRI bir sayfa.
     *
     * İkisini aynı metinle geçiştirmek ürünü yalancı yapardı: çıkmaz sokak
     * "burada menü yok" der, bu sayfa ise "menü var, bu saatte servis
     * edilmiyor" der. Masadaki misafir için aradaki fark, kalkıp gitmekle
     * personele sormak arasındaki farktır.
     *
     * SAAT VARSA YAZILIR, YOKSA HİÇ YAZILMAZ. `nextService` anahtarı gerçek
     * bir geçiş bulunamadığında haritada HİÇ BULUNMAZ; boş bir dize
     * döndürseydik şablon "Sonraki servis: " diye yarım bir cümle çizerdi.
     *
     * @return array<string, string>
     */
    public function outOfService(?string $locale = null, ?string $nextServiceClock = null): array
    {
        $text = [
            'title' => $this->get('guest.outOfService.title', $locale),
            'heading' => $this->get('guest.outOfService.heading', $locale),
            'body' => $this->get('guest.outOfService.body', $locale),
        ];

        if ($nextServiceClock !== null && trim($nextServiceClock) !== '') {
            $text['nextService'] = $this->get('guest.outOfService.nextService', $locale, [
                'clock' => $nextServiceClock,
            ]);
        }

        return $text;
    }

    /**
     * ŞUBE KAPALI ŞERİDİ (FF-141) — menünün ÜSTÜNDE, onun YERİNE değil.
     *
     * Servis dışı metinlerinden ayrı bir haritadır ve öyle kalmalıdır: orada
     * gösterilecek menü yoktur, burada menü vardır ve çizilir. Aynı sözlüğe
     * koymak, bir gün birinin cümlesini diğerinin ekranında görmek demekti.
     *
     * DURUM CÜMLEYLE SÖYLENİR. `notice` her zaman doludur ve "kapalıyız"
     * der; şeridin rengi ya da konumu tek başına hiçbir şey anlatmaz
     * (WCAG 1.4.1).
     *
     * AÇILIŞ SATIRI VARSA YAZILIR, YOKSA HİÇ YAZILMAZ. `nextOpening` anahtarı
     * saat bilinmediğinde haritada HİÇ BULUNMAZ; boş bir dize döndürseydik
     * şablon yarım bir cümle çizerdi — aynı kural `outOfService` içinde de
     * yazılı.
     *
     * @param  int|null  $nextOpeningIsoWeekday  1 = Pazartesi … 7 = Pazar.
     * @param  bool  $nextOpeningIsToday  Gün numarasından TÜRETİLEMEZ: bir hafta
     *                                    sonraki aynı gün de aynı numarayı taşır.
     * @return array<string, string>
     */
    public function closedNotice(
        ?string $locale = null,
        ?string $nextOpeningClock = null,
        ?int $nextOpeningIsoWeekday = null,
        bool $nextOpeningIsToday = false,
    ): array {
        $text = ['notice' => $this->get('guest.closed.notice', $locale)];

        if ($nextOpeningClock === null || trim($nextOpeningClock) === '') {
            return $text;
        }

        if ($nextOpeningIsToday) {
            $text['nextOpening'] = $this->get('guest.closed.opensToday', $locale, [
                'clock' => $nextOpeningClock,
            ]);

            return $text;
        }

        // Gün adı olmadan "Pazartesi 09:00" cümlesi kurulamaz; günü
        // bilmiyorsak saati de yazmayız. Yarım bir cümle, hiç cümle
        // olmamasından kötüdür.
        if ($nextOpeningIsoWeekday === null || $nextOpeningIsoWeekday < 1 || $nextOpeningIsoWeekday > 7) {
            return $text;
        }

        $text['nextOpening'] = $this->get('guest.closed.opensOn', $locale, [
            'day' => $this->get('guest.day.'.$nextOpeningIsoWeekday, $locale),
            'clock' => $nextOpeningClock,
        ]);

        return $text;
    }
}
