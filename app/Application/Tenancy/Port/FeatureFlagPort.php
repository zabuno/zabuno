<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Port;

/**
 * Özellik bayrakları (`docs/98` FF-74, Pennant). Kapsam çalışma alanıdır:
 * bir bayrak bir kiracı için kapatılabilir, ötekiler etkilenmez.
 */
interface FeatureFlagPort
{
    /** @return array<string, bool> bayrak adı → açık mı */
    public function flagsFor(int $workspaceId): array;
}
