<?php

declare(strict_types=1);

namespace App\Application\Team\Port;

use App\Application\Team\Dto\TeamMemberSummary;

interface TeamMemberRepositoryPort
{
    /**
     * Returns every membership of the given workspace, ordered
     * deterministically by membership id (ascending — oldest membership
     * first).
     *
     * @return list<TeamMemberSummary>
     */
    public function listByWorkspaceId(int $workspaceId): array;

    /**
     * Removes the membership identified by the given workspace and
     * membership id via one atomic conditional delete (workspace id +
     * membership id + role = editor). Returns true when a row was
     * deleted, false when no matching editor row was found for that exact
     * workspace.
     */
    public function removeEditor(int $workspaceId, int $membershipId): bool;
}
