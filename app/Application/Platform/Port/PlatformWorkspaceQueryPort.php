<?php

declare(strict_types=1);

namespace App\Application\Platform\Port;

interface PlatformWorkspaceQueryPort
{
    /**
     * @return list<array{id: int, name: string, slug: string, state: string}>
     */
    public function search(string $query): array;
}
