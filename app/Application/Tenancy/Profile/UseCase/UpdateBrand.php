<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\UseCase;

use App\Application\Tenancy\Profile\Dto\BrandProfile;
use App\Application\Tenancy\Profile\Port\BrandRepositoryPort;
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

        return $this->brands->update($workspaceId, [
            'name' => $data['name'],
            'locale' => $locale,
            'timezone' => $timezone,
            'currency' => $currency,
            'description' => $data['description'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'primary_color' => self::normaliseColor($data['primary_color'] ?? null),
            'secondary_color' => self::normaliseColor($data['secondary_color'] ?? null),
        ]);
    }

    /** Boş dize NULL demektir: "renk seçmedim" ile "boş renk" aynı şey değildir. */
    private static function normaliseColor(mixed $value): ?string
    {
        $color = is_string($value) ? strtolower(trim($value)) : '';

        return $color === '' ? null : $color;
    }
}
