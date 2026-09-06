<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Dto\BillingProfile;
use App\Application\Billing\Port\BillingProfileRepositoryPort;
use Illuminate\Support\Facades\DB;

final class EloquentBillingProfileRepository implements BillingProfileRepositoryPort
{
    public function find(int $workspaceId): ?BillingProfile
    {
        $row = DB::table('billing_profiles')->where('workspace_id', $workspaceId)->first();

        return $row === null ? null : BillingProfile::fromArray((array) $row);
    }

    public function save(int $workspaceId, BillingProfile $profile): BillingProfile
    {
        DB::table('billing_profiles')->updateOrInsert(
            ['workspace_id' => $workspaceId],
            [...$profile->toArray(), 'updated_at' => now(), 'created_at' => now()],
        );

        return $this->find($workspaceId) ?? $profile;
    }
}
