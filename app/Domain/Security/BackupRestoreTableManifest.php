<?php

declare(strict_types=1);

namespace App\Domain\Security;

final class BackupRestoreTableManifest
{
    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        return [
            'users',
            'workspaces',
            'workspace_memberships',
            'menus',
        ];
    }

    /**
     * @return list<string>
     */
    public static function sourcePaths(): array
    {
        return [
            'app/Domain/Security/BackupRestoreTableManifest.php',
            'app/Infrastructure/Security/Execution/SqliteBackupRestoreDrillRunner.php',
        ];
    }
}
