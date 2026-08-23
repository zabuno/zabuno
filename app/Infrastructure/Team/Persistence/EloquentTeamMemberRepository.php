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
}
