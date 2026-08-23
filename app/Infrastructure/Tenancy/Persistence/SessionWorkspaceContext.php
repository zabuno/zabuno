<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Persistence;

use App\Application\Tenancy\Port\WorkspaceContextSessionPort;
use Illuminate\Support\Facades\Session;

final class SessionWorkspaceContext implements WorkspaceContextSessionPort
{
    private const SESSION_KEY = 'tenancy.current_workspace_id';

    public function getCurrentWorkspaceId(): ?int
    {
        $value = Session::get(self::SESSION_KEY);

        return $value === null ? null : (int) $value;
    }

    public function setCurrentWorkspaceId(int $workspaceId): void
    {
        Session::put(self::SESSION_KEY, $workspaceId);
    }

    public function clearCurrentWorkspaceId(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
