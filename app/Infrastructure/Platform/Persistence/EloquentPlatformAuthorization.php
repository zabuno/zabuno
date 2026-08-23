<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Persistence;

use App\Application\Platform\Port\PlatformAuthorizationPort;
use App\Domain\Platform\PlatformRole;
use Illuminate\Support\Facades\DB;

final class EloquentPlatformAuthorization implements PlatformAuthorizationPort
{
    public function isSuperAdmin(int $userId): bool
    {
        return DB::table('platform_role_assignments')
            ->where('user_id', $userId)
            ->where('role', PlatformRole::SuperAdmin->value)
            ->exists();
    }
}
