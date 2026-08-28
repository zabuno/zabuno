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
    public function get(string $key, ?string $contentLocale = null, array $params = []): string
    {
        return $this->translations->translate(
            self::DOMAIN,
            $key,
            $contentLocale !== null && trim($contentLocale) !== '' ? $contentLocale : 'tr',
            $params,
        );
    }
}
