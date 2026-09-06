<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Application\Support\Dto\SupportRequestSummary;
use App\Application\Support\Port\SupportRequestRepositoryPort;

final class ListWorkspaceSupportRequests
{
    public function __construct(
        private readonly SupportRequestRepositoryPort $requests,
    ) {}

    /** @return list<SupportRequestSummary> */
    public function handle(int $workspaceId): array
    {
        return $this->requests->listByWorkspaceId($workspaceId);
    }
}
