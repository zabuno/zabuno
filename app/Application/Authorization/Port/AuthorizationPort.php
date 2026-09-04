<?php

declare(strict_types=1);

namespace App\Application\Authorization\Port;

use App\Domain\Authorization\Permission;

interface AuthorizationPort
{
    public function can(int $userId, Permission $permission, int $workspaceId): bool;

    /**
     * Kullanıcının bu çalışma alanındaki BÜTÜN izinleri — ön uç bunu bir
     * kez okur ve yetkisiz eylemi hiç çizmez (`docs/98` FF-74: Editor 403
     * görmez). Üyelik yoksa boş liste.
     *
     * @return list<Permission>
     */
    public function permissionsFor(int $userId, int $workspaceId): array;
}
