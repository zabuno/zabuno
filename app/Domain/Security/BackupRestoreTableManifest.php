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
     * Veritabanı tatbikatının kaynak anlık görüntüsüne giren dosyalar.
     * Her koşucu buradadır: PostgreSQL koşucusu değişip özet aynı kalsaydı
     * kayıt, hangi kodun koştuğunu yanlış söylerdi.
     *
     * @return list<string>
     */
    public static function sourcePaths(): array
    {
        return [
            'app/Domain/Security/BackupRestoreTableManifest.php',
            'app/Infrastructure/Security/Execution/PostgresBackupRestoreDrillRunner.php',
            'app/Infrastructure/Security/Execution/SqliteBackupRestoreDrillRunner.php',
        ];
    }

    /**
     * Medya tatbikatının kaynak anlık görüntüsüne giren dosyalar.
     *
     * @return list<string>
     */
    public static function mediaSourcePaths(): array
    {
        return [
            'app/Domain/Security/MediaBackupRestoreEvidenceRecord.php',
            'app/Infrastructure/Security/Execution/TarMediaBackupRestoreDrillRunner.php',
        ];
    }
}
