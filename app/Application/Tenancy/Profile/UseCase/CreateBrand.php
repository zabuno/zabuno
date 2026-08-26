<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\UseCase;

use App\Application\Tenancy\Profile\Dto\BrandProfile;
use App\Application\Tenancy\Profile\Port\BrandRepositoryPort;
use App\Domain\Tenancy\ValueObject\BrandSlug;
use App\Domain\Tenancy\ValueObject\CurrencyCode;
use App\Domain\Tenancy\ValueObject\LocaleCode;
use App\Domain\Tenancy\ValueObject\TimezoneIdentifier;
use App\Domain\Url\UrlPolicy;
use Illuminate\Support\Str;

final class CreateBrand
{
    public function __construct(
        private readonly BrandRepositoryPort $brands,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(int $workspaceId, array $data): BrandProfile
    {
        $slug = isset($data['slug']) && $data['slug'] !== ''
            ? BrandSlug::fromString((string) $data['slug'])->value()
            : $this->generateUniqueSlug((string) $data['name']);

        $locale = isset($data['locale']) && $data['locale'] !== ''
            ? LocaleCode::fromString((string) $data['locale'])->value()
            : LocaleCode::default()->value();

        $timezone = TimezoneIdentifier::fromString((string) $data['timezone'])->value();
        $currency = CurrencyCode::fromString((string) $data['currency'])->value();

        return $this->brands->create($workspaceId, [
            'name' => $data['name'],
            'slug' => $slug,
            'locale' => $locale,
            'timezone' => $timezone,
            'currency' => $currency,
            'description' => $data['description'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
        ]);
    }

    private function generateUniqueSlug(string $name): string
    {
        // `Str::slug` Türkçe harfleri doğru çevirir: "Çiğköfteci Ömer'in
        // Şöleni" -> "cigkofteci-omerin-soleni". Ayrı bir çeviri katmanı
        // eklemek, çalışan bir davranışı ikinci kez yazmak olurdu.
        $base = Str::slug($name);
        $policy = app(UrlPolicy::class);

        do {
            $candidate = $base === '' ? Str::lower(Str::random(8)) : $base.'-'.Str::lower(Str::random(6));
        } while ($this->brands->slugExists($candidate) || $policy->isReservedSlug($candidate));

        return $candidate;
    }
}
