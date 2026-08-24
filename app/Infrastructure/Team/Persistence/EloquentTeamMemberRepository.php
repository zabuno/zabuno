<?php

declare(strict_types=1);

namespace App\Infrastructure\Team\Persistence;

use App\Application\Team\Dto\TeamMemberSummary;
use App\Application\Team\Port\TeamMemberRepositoryPort;
use Illuminate\Support\Facades\DB;

final class EloquentTeamMemberRepository implements TeamMemberRepositoryPort
{
    /**
     * @return list<TeamMemberSummary>
     */
    public function listByWorkspaceId(int $workspaceId): array
    {
        return DB::table('workspace_memberships')
            ->join('users', 'users.id', '=', 'workspace_memberships.user_id')
            ->where('workspace_memberships.workspace_id', $workspaceId)
            ->orderBy('workspace_memberships.id')
            ->select([
                'workspace_memberships.id as id',
                'users.name as name',
                'users.email as email',
                'workspace_memberships.role as role',
            ])
            ->get()
            ->map(fn (object $row): TeamMemberSummary => new TeamMemberSummary(
                (int) $row->id,
                (string) $row->name,
                (string) $row->email,
                (string) $row->role,
            ))
            ->all();
    }

    public function removeEditor(int $workspaceId, int $membershipId): bool
    {
        return DB::table('workspace_memberships')
            ->where('id', $membershipId)
            ->where('workspace_id', $workspaceId)
            ->where('role', 'editor')
            ->delete() > 0;
    }

    public function transferOwnership(int $workspaceId, int $requesterUserId, int $targetMembershipId): bool
    {
        try {
            return $this->transferOwnershipInTransaction($workspaceId, $requesterUserId, $targetMembershipId);
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function transferOwnershipInTransaction(int $workspaceId, int $requesterUserId, int $targetMembershipId): bool
    {
        return DB::transaction(function () use ($workspaceId, $requesterUserId, $targetMembershipId): bool {
            $memberships = DB::table('workspace_memberships')
                ->where('workspace_id', $workspaceId)
                ->lockForUpdate()
                ->get(['id', 'user_id', 'role']);

            $owners = $memberships->where('role', 'owner');

            if ($owners->count() !== 1) {
                return false;
            }

            $ownerMembership = $owners->first();

            if ((int) $ownerMembership->user_id !== $requesterUserId) {
                return false;
            }

            $targetMembership = $memberships->firstWhere('id', $targetMembershipId);

            if ($targetMembership === null || $targetMembership->role !== 'editor') {
                return false;
            }

            if ((int) $targetMembership->id === (int) $ownerMembership->id) {
                return false;
            }

            $promoted = DB::table('workspace_memberships')
                ->where('id', $targetMembership->id)
                ->where('workspace_id', $workspaceId)
                ->where('role', 'editor')
                ->update(['role' => 'owner', 'updated_at' => now()]);

            if ($promoted !== 1) {
                throw new \RuntimeException('Failed to promote target membership to owner.');
            }

            $demoted = DB::table('workspace_memberships')
                ->where('id', $ownerMembership->id)
                ->where('workspace_id', $workspaceId)
                ->where('role', 'owner')
                ->update(['role' => 'editor', 'updated_at' => now()]);

            if ($demoted !== 1) {
                throw new \RuntimeException('Failed to demote prior owner to editor.');
            }

            return true;
        });
    }
}
