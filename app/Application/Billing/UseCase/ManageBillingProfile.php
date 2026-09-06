<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Dto\BillingProfile;
use App\Application\Billing\Port\BillingProfileRepositoryPort;

final class ManageBillingProfile
{
    public function __construct(
        private readonly BillingProfileRepositoryPort $profiles,
    ) {}

    /** @return array<string, string> */
    public function show(int $workspaceId): array
    {
        $profile = $this->profiles->find($workspaceId);

        return $profile === null
            ? ['state' => 'missing']
            : ['state' => 'complete', ...$profile->toArray()];
    }

    /** @return array<string, string> */
    public function store(int $workspaceId, BillingProfile $profile): array
    {
        return ['state' => 'complete', ...$this->profiles->save($workspaceId, $profile)->toArray()];
    }
}
