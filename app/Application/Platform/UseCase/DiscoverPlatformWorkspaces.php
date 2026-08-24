<?php

declare(strict_types=1);

namespace App\Application\Platform\UseCase;

use App\Application\Platform\Port\PlatformWorkspaceQueryPort;

final class DiscoverPlatformWorkspaces
{
    public function __construct(
        private readonly PlatformWorkspaceQueryPort $workspaces,
    ) {}

    /**
     * @return list<array{id: int, name: string, slug: string, state: string}>
     */
    public function handle(string $query): array
    {
        return $this->workspaces->search($query);
    }
}
