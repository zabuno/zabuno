<?php

declare(strict_types=1);

namespace App\Application\Team\Port;

use App\Application\Team\Dto\IssuedTeamInvitation;
use App\Application\Team\Dto\TeamInvitationDetails;
use App\Application\Team\Dto\TeamInvitationSummary;
use App\Application\Team\Exception\TeamInvitationConflictException;

interface TeamInvitationRepositoryPort
{
    /**
     * Returns every pending invitation of the given workspace, ordered
     * deterministically by invitation id (ascending — oldest invitation
     * first).
     *
     * @return list<TeamInvitationSummary>
     */
    public function listPendingByWorkspaceId(int $workspaceId): array;

    /**
     * Creates a pending invitation for the given normalized email within the
     * workspace, generating a fresh opaque raw token (only the sha256 hash
     * of which is persisted) and an approximately seven-day expiry. When a
     * cancelled or already-accepted row exists for the same
     * workspace+email, that row id is reused and its token/expiry/status
     * are rotated back to pending; the previous raw token is thereby
     * invalidated.
     *
     * @throws TeamInvitationConflictException when the email already
     *                                         belongs to a workspace member, or a pending invitation for the
     *                                         same email already exists.
     */
    public function createPendingWithToken(int $workspaceId, string $normalizedEmail, string $role, int $invitedBy): IssuedTeamInvitation;

    /**
     * Resolves the safe-to-expose state projection for the given raw
     * invitation token on behalf of the given authenticated user id (null
     * when the request is unauthenticated). Never throws for an unknown,
     * expired, cancelled or already-consumed token — those resolve to the
     * corresponding non-available state with no sensitive fields exposed.
     */
    public function findDetailsForRawToken(string $rawToken, ?int $authenticatedUserId, ?string $authenticatedUserEmail): TeamInvitationDetails;

    /**
     * Cancels the pending invitation identified by the given workspace and
     * invitation id via one atomic conditional update (workspace id +
     * invitation id + pending status). Returns true when the row was
     * transitioned to cancelled, false when no matching pending row was
     * found for that exact workspace.
     */
    public function cancelPending(int $workspaceId, int $invitationId): bool;

    /**
     * Accepts the pending invitation identified by the given token hash on
     * behalf of the given user, provided the normalized email matches and
     * the invitation has not expired. On success, atomically inserts (or
     * ignores, if already present) an editor workspace membership for the
     * user and flips the invitation to accepted with accepted_at/accepted_by
     * set. Returns true when the invitation was accepted by this call,
     * false when no matching pending, non-expired, email-matching
     * invitation was found for that token hash (unknown/expired/cancelled/
     * already-consumed/email-mismatched token).
     */
    public function acceptPendingByTokenHash(string $tokenHash, string $normalizedEmail, int $userId): bool;
}
