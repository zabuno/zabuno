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
        ];
    }
}
