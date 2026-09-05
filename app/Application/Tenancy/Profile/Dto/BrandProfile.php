<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\Dto;

final class BrandProfile
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $locale,
        public readonly string $timezone,
        public readonly string $currency,
        public readonly ?string $description,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        /** `#rrggbb` ya da null — marka rengi seçilmemişse ürün kendi tonuyla çalışır. */
        public readonly ?string $primaryColor = null,
        public readonly ?string $secondaryColor = null,
        /**
         * Skin'in BİÇİM ekseni (`a`..`f`) — renk değil, seçenek (FF-174).
         * Null: restoran biçim seçmemiş, ürünün varsayılanı geçerlidir.
         */
        public readonly ?string $skinVariant = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'name' => $this->name,
            'slug' => $this->slug,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'description' => $this->description,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'primary_color' => $this->primaryColor,
            'secondary_color' => $this->secondaryColor,
            'skin_variant' => $this->skinVariant,
        ];
    }
}
