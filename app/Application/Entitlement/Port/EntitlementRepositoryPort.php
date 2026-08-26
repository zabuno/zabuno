<?php

declare(strict_types=1);

namespace App\Application\Entitlement\Port;

use App\Domain\Entitlement\EntitlementSet;

interface EntitlementRepositoryPort
{
    /**
     * Bir workspace'in aktif aboneliğinin verdiği yetenekler.
     *
     * Abonelik yoksa, süresi dolmuşsa veya aktif değilse BOŞ küme döner —
     * temel yolculuk yine çalışır, yalnız ek yetenekler kapalıdır.
     */
    public function forWorkspace(int $workspaceId): EntitlementSet;
}
