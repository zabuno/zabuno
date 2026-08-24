<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Port;

interface WorkspaceContextSessionPort
{
    public function getCurrentWorkspaceId(): ?int;

    public function setCurrentWorkspaceId(int $workspaceId): void;

    public function clearCurrentWorkspaceId(): void;
}
