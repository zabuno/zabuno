<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Persistence;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Domain\Authorization\RolePermissions;
use App\Models\WorkspaceMembership;

final class EloquentAuthorizationDecisionPoint implements AuthorizationPort
{
    public function can(int $userId, Permission $permission, int $workspaceId): bool
    {
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->first();

        if ($membership === null) {
            return false;
        }

        return in_array($permission, RolePermissions::for($membership->role), true);
    }
}
