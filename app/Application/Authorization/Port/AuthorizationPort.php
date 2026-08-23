<?php

declare(strict_types=1);

namespace App\Application\Authorization\Port;

use App\Domain\Authorization\Permission;

interface AuthorizationPort
{
    public function can(int $userId, Permission $permission, int $workspaceId): bool;
}
