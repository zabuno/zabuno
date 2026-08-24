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

    /**
     * Transfers workspace ownership from the current owner to the given
     * editor membership, in one atomic transaction. Requires the requester
     * to be the exact current owner of the workspace, the target membership
     * to belong to the same workspace with role exactly editor, and the
     * target to differ from the current owner. Locks and validates the
     * workspace's membership set (exactly one owner, owner belongs to the
     * requester), then conditionally promotes the target to owner and
     * demotes the prior owner to editor — both updates must affect exactly
     * one row or the whole transaction rolls back. Returns true only when
     * the transfer committed; false for any rejection, with no partial
     * state left behind.
     */
    public function transferOwnership(int $workspaceId, int $requesterUserId, int $targetMembershipId): bool;
}
