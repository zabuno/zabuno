<?php

declare(strict_types=1);

namespace App\Infrastructure\Team\Persistence;

use App\Application\Team\Dto\IssuedTeamInvitation;
use App\Application\Team\Dto\TeamInvitationDetails;
use App\Application\Team\Dto\TeamInvitationSummary;
use App\Application\Team\Exception\TeamInvitationConflictException;
use App\Application\Team\Port\TeamInvitationRepositoryPort;
use App\Domain\Team\InvitationStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentTeamInvitationRepository implements TeamInvitationRepositoryPort
{
    /**
     * @return list<TeamInvitationSummary>
     */
    public function listPendingByWorkspaceId(int $workspaceId): array
    {
        return DB::table('team_invitations')
            ->where('workspace_id', $workspaceId)
            ->where('status', InvitationStatus::Pending->value)
            ->orderBy('id')
            ->select(['id', 'email', 'role', 'status'])
            ->get()
            ->map(fn (object $row): TeamInvitationSummary => new TeamInvitationSummary(
                (int) $row->id,
                (string) $row->email,
                (string) $row->role,
                (string) $row->status,
            ))
            ->all();
    }

    public function createPendingWithToken(int $workspaceId, string $normalizedEmail, string $role, int $invitedBy): IssuedTeamInvitation
    {
        $alreadyMember = DB::table('workspace_memberships')
            ->join('users', 'users.id', '=', 'workspace_memberships.user_id')
            ->where('workspace_memberships.workspace_id', $workspaceId)
            ->where('users.email', $normalizedEmail)
            ->exists();

        if ($alreadyMember) {
            throw new TeamInvitationConflictException('E-posta zaten bu workspace\'in üyesi.');
        }

        $workspaceName = DB::table('workspaces')->where('id', $workspaceId)->value('name');

        $rawToken = $this->generateRawToken();
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = now()->addDays(7);

        try {
            $id = DB::transaction(function () use ($workspaceId, $normalizedEmail, $role, $invitedBy, $tokenHash, $expiresAt): int {
                $existing = DB::table('team_invitations')
                    ->where('workspace_id', $workspaceId)
                    ->where('email', $normalizedEmail)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($existing->status === InvitationStatus::Pending->value) {
                        throw new TeamInvitationConflictException('Bu e-posta için zaten bekleyen bir davet var.');
                    }

                    DB::table('team_invitations')
                        ->where('id', $existing->id)
                        ->update([
                            'role' => $role,
                            'status' => InvitationStatus::Pending->value,
                            'invited_by' => $invitedBy,
                            'token_hash' => $tokenHash,
                            'expires_at' => $expiresAt,
                            'accepted_at' => null,
                            'accepted_by' => null,
                            'updated_at' => now(),
                        ]);

                    return (int) $existing->id;
                }

                return (int) DB::table('team_invitations')->insertGetId([
                    'workspace_id' => $workspaceId,
                    'email' => $normalizedEmail,
                    'role' => $role,
                    'status' => InvitationStatus::Pending->value,
                    'invited_by' => $invitedBy,
                    'token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000' && $this->isWorkspaceEmailUniqueViolation($exception)) {
                throw new TeamInvitationConflictException('Bu e-posta için zaten bekleyen bir davet var.', previous: $exception);
            }

            throw $exception;
        }

        return new IssuedTeamInvitation(
            $id,
            $normalizedEmail,
            $role,
            InvitationStatus::Pending->value,
            $rawToken,
            $expiresAt,
            (string) $workspaceName,
        );
    }

    public function findDetailsForRawToken(string $rawToken, ?int $authenticatedUserId, ?string $authenticatedUserEmail): TeamInvitationDetails
    {
        $tokenHash = hash('sha256', $rawToken);

        $row = DB::table('team_invitations')
            ->join('workspaces', 'workspaces.id', '=', 'team_invitations.workspace_id')
            ->where('team_invitations.token_hash', $tokenHash)
            ->select(['team_invitations.*', 'workspaces.name as workspace_name'])
            ->first();

        if ($row === null || $row->status === InvitationStatus::Cancelled->value) {
            return TeamInvitationDetails::nonAvailable('invalid', $authenticatedUserId !== null);
        }

        if ($row->status === InvitationStatus::Accepted->value) {
            return TeamInvitationDetails::nonAvailable('consumed', $authenticatedUserId !== null);
        }

        if (Carbon::parse($row->expires_at)->isPast()) {
            return TeamInvitationDetails::nonAvailable('expired', $authenticatedUserId !== null);
        }

        if ($authenticatedUserId === null) {
            return TeamInvitationDetails::guest();
        }

        if ($authenticatedUserEmail === null || $authenticatedUserEmail !== $row->email) {
            if ($authenticatedUserId === (int) $row->invited_by) {
                return TeamInvitationDetails::guest();
            }

            return TeamInvitationDetails::nonAvailable('invalid', true);
        }

        return TeamInvitationDetails::available(
            (string) $row->workspace_name,
            (string) $row->email,
            (string) $row->role,
            "/api/invitations/accept/{$rawToken}",
        );
    }

    private function generateRawToken(): string
    {
        return Str::replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(48)));
    }

    private function isWorkspaceEmailUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'team_invitations_workspace_id_email_unique')
            || str_contains($message, 'UNIQUE constraint failed: team_invitations.workspace_id, team_invitations.email');
    }

    public function cancelPending(int $workspaceId, int $invitationId): bool
    {
        return DB::table('team_invitations')
            ->where('id', $invitationId)
            ->where('workspace_id', $workspaceId)
            ->where('status', InvitationStatus::Pending->value)
            ->update([
                'status' => InvitationStatus::Cancelled->value,
                'updated_at' => now(),
            ]) > 0;
    }

    public function acceptPendingByTokenHash(string $tokenHash, string $normalizedEmail, int $userId): bool
    {
        return DB::transaction(function () use ($tokenHash, $normalizedEmail, $userId): bool {
            $invitation = DB::table('team_invitations')
                ->where('token_hash', $tokenHash)
                ->where('email', $normalizedEmail)
                ->where('status', InvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                return false;
            }

            $accepted = DB::table('team_invitations')
                ->where('id', $invitation->id)
                ->where('status', InvitationStatus::Pending->value)
                ->update([
                    'status' => InvitationStatus::Accepted->value,
                    'accepted_at' => now(),
                    'accepted_by' => $userId,
                    'updated_at' => now(),
                ]);

            if ($accepted === 0) {
                return false;
            }

            DB::table('workspace_memberships')->insertOrIgnore([
                'workspace_id' => $invitation->workspace_id,
                'user_id' => $userId,
                'role' => 'editor',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }
}
