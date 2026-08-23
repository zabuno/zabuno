<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

enum WorkspaceState: string
{
    case Onboarding = 'onboarding';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
    case DeletionPending = 'deletion_pending';
    case Deleted = 'deleted';

    public function isEligibleAsCurrentContext(): bool
    {
        return $this === self::Onboarding || $this === self::Active;
    }

    /**
     * @return list<string>
     */
    public static function eligibleValues(): array
    {
        return array_values(array_map(
            static fn (self $state): string => $state->value,
            array_filter(self::cases(), static fn (self $state): bool => $state->isEligibleAsCurrentContext()),
        ));
    }
}
