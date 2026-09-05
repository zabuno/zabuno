<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\UseCase;

use App\Application\Tenancy\Profile\Dto\BrandProfile;
use App\Application\Tenancy\Profile\Port\BrandRepositoryPort;
use App\Domain\Branding\SkinVariant;
use App\Domain\Tenancy\ValueObject\CurrencyCode;
use App\Domain\Tenancy\ValueObject\LocaleCode;
use App\Domain\Tenancy\ValueObject\TimezoneIdentifier;

final class UpdateBrand
{
    public function __construct(
        private readonly BrandRepositoryPort $brands,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(int $workspaceId, array $data): ?BrandProfile
    {
        $locale = isset($data['locale']) && $data['locale'] !== ''
            ? LocaleCode::fromString((string) $data['locale'])->value()
            : LocaleCode::default()->value();

        $timezone = TimezoneIdentifier::fromString((string) $data['timezone'])->value();
        $currency = CurrencyCode::fromString((string) $data['currency'])->value();

        $attributes = [
            'name' => $data['name'],
            'locale' => $locale,
            'timezone' => $timezone,
            'currency' => $currency,
            'description' => $data['description'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
        ];

        /*
            MARKA GÖRÜNÜMÜ ALANLARI YALNIZ GÖNDERİLDİYSE YAZILIR (FF-174).

            Önceden burada `$data['primary_color'] ?? null` vardı ve alan
            `sometimes` doğrulanıyor. İkisi birleşince, restoranın adını
            değiştiren bir istek seçilmiş rengi de SİLİYORDU — sahip yalnız
            adı düzeltmişken menüsünün rengi gidiyordu. Yokluk ile "boşalt"
            aynı şey değildir.
        */
        foreach (['primary_color', 'secondary_color'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = self::normaliseColor($data[$key]);
            }
        }

        if (array_key_exists('skin_variant', $data)) {
            // Tanınmayan bir biçim seçilmiş sayılmaz: tarayıcıda karşılığı
            // olmayan bir öznitelik yazmak sessiz bir hata olurdu.
            $attributes['skin_variant'] = SkinVariant::tryFromKey(
                is_string($data['skin_variant']) ? $data['skin_variant'] : null
            )?->value;
        }

        return $this->brands->update($workspaceId, $attributes);
    }

    /** Boş dize NULL demektir: "renk seçmedim" ile "boş renk" aynı şey değildir. */
    private static function normaliseColor(mixed $value): ?string
    {
        $color = is_string($value) ? strtolower(trim($value)) : '';

        return $color === '' ? null : $color;
    }
}
