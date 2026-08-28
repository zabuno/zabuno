<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

use App\Application\Reference\Port\MarketReferencePort;
use App\Domain\Tenancy\ValueObject\LocaleCode;
use DateTimeZone;
use NumberFormatter;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Languages;

final class IcuMarketReference implements MarketReferencePort
{
    /** Referans listesi sunulacak dil. Arayüz bugün yalnız İngilizce. */
    private const DISPLAY_LOCALE = 'en';

    public function markets(): array
    {
        $markets = [];

        foreach (Countries::getNames(self::DISPLAY_LOCALE) as $code => $name) {
            // Saat dilimi olmayan ülke seçilemez: ondan türetilecek bir şey
            // yoktur ve kullanıcı çıkmaza girer.
            if ($this->zonesFor($code) === []) {
                continue;
            }

            $markets[] = ['code' => $code, 'name' => $name];
        }

        return $markets;
    }

    public function locales(): array
    {
        $locales = [];

        foreach (LocaleCode::supported() as $code) {
            $locales[] = [
                'code' => $code,
                // ICU adı bulunamazsa kodun kendisi gösterilir. Uydurulmuş
                // bir ad, yanlış bir dili seçtirmekten iyidir değil —
                // ama boş bir seçenek de seçilemez.
                'name' => Languages::exists($code)
                    ? Languages::getName($code, self::DISPLAY_LOCALE)
                    : $code,
            ];
        }

        usort($locales, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $locales;
    }

    public function timezonesFor(string $countryCode): array
    {
        $zones = [];

        foreach ($this->zonesFor($countryCode) as $id) {
            $zones[] = ['id' => $id, 'label' => $this->zoneLabel($id)];
        }

        return $zones;
    }

    public function currencies(): array
    {
        $currencies = [];

        foreach (Currencies::getNames(self::DISPLAY_LOCALE) as $code => $name) {
            $currencies[] = [
                'code' => $code,
                'name' => $name,
                'symbol' => Currencies::getSymbol($code, self::DISPLAY_LOCALE),
                // Sabit iki ondalık varsaymak yanlıştır: JPY sıfır, bazı
                // para birimleri üç ondalık kullanır.
                'fractionDigits' => Currencies::getFractionDigits($code),
            ];
        }

        return $currencies;
    }

    public function countryForTimezone(string $timezone): ?string
    {
        try {
            $location = (new DateTimeZone($timezone))->getLocation();
        } catch (\Exception) {
            // Tanınmayan saat dilimi bir hata değil, yalnız bir öneri
            // fırsatının kaçmasıdır. Kullanıcı ülkeyi kendisi seçer.
            return null;
        }

        $code = $location === false ? null : ($location['country_code'] ?? null);

        return is_string($code) && $code !== '' && $code !== '??' ? $code : null;
    }

    public function defaultsFor(string $countryCode): array
    {
        $zones = $this->zonesFor($countryCode);

        return [
            'timezone' => $zones[0] ?? 'UTC',
            'currency' => $this->currencyFor($countryCode),
        ];
    }

    /** @return list<string> */
    private function zonesFor(string $countryCode): array
    {
        $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, strtoupper($countryCode));

        return $zones === false ? [] : array_values($zones);
    }

    /**
     * Saat dilimi kimliğini insan okunur hâle getirir: `Europe/Istanbul`
     * yerine `Istanbul — UTC+03:00`. Kimlik saklanır, etiket gösterilir.
     */
    private function zoneLabel(string $id): string
    {
        $city = str_replace('_', ' ', substr((string) strrchr($id, '/'), 1));

        if ($city === '') {
            $city = $id;
        }

        $offset = (new DateTimeZone($id))->getOffset(new \DateTimeImmutable('now', new DateTimeZone('UTC')));
        $sign = $offset < 0 ? '-' : '+';
        $offset = abs($offset);

        return sprintf('%s — UTC%s%02d:%02d', $city, $sign, intdiv($offset, 3600), intdiv($offset % 3600, 60));
    }

    private function currencyFor(string $countryCode): string
    {
        $formatter = new NumberFormatter(
            self::DISPLAY_LOCALE.'_'.strtoupper($countryCode),
            NumberFormatter::CURRENCY
        );

        $code = $formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE);

        // ICU tanımadığı ülke için boş ya da `XXX` döndürebilir. Uydurmak
        // yerine görünür bir varsayılana düşüyoruz.
        return is_string($code) && $code !== '' && $code !== 'XXX' ? $code : 'EUR';
    }
}
