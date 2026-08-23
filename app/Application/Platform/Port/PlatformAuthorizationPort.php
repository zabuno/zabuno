<?php

declare(strict_types=1);

namespace App\Application\Platform\Port;

interface PlatformAuthorizationPort
{
    public function isSuperAdmin(int $userId): bool;
}
