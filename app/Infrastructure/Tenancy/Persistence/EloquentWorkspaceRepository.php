<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Persistence;

use App\Application\Tenancy\Dto\WorkspaceSummary;
use App\Application\Tenancy\Port\WorkspaceRepositoryPort;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\WorkspaceState;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentWorkspaceRepository implements WorkspaceRepositoryPort
{
    public function createOwnedWorkspace(string $name, int $ownerUserId): WorkspaceSummary
    {
        return DB::transaction(function () use ($name, $ownerUserId): WorkspaceSummary {
            $workspace = Workspace::query()->create([
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'state' => WorkspaceState::Onboarding,
                'created_by' => $ownerUserId,
            ]);

            WorkspaceMembership::query()->create([
                'workspace_id' => $workspace->getKey(),
                'user_id' => $ownerUserId,
                'role' => MembershipRole::Owner,
            ]);

            return $this->toSummary($workspace);
        });
    }

    /**
     * @return list<WorkspaceSummary>
     */
    public function listForUser(int $userId): array
    {
        $workspaces = Workspace::query()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $userId))
            ->get();

        return $workspaces->map(fn (Workspace $workspace) => $this->toSummary($workspace))->all();
    }

    public function findEligibleForUser(int $workspaceId, int $userId): ?WorkspaceSummary
    {
        $workspace = Workspace::query()
            ->whereKey($workspaceId)
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $userId))
            ->whereIn('state', WorkspaceState::eligibleValues())
            ->first();

        return $workspace === null ? null : $this->toSummary($workspace);
    }

    private function toSummary(Workspace $workspace): WorkspaceSummary
    {
        return new WorkspaceSummary(
            (int) $workspace->getKey(),
            (string) $workspace->name,
            (string) $workspace->slug,
            $workspace->state,
        );
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        do {
            $candidate = $base.'-'.Str::lower(Str::random(6));
        } while (Workspace::query()->where('slug', $candidate)->exists());

        return $candidate;
    }
}
