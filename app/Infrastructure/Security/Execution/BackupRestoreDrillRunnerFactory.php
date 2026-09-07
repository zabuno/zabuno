<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Execution;

use App\Application\Security\Port\BackupRestoreDrillRunnerPort;
use App\Application\Security\Port\MediaBackupRestoreDrillRunnerPort;
use RuntimeException;

/**
 * Koşucu, BAĞLANTIYA göre seçilir; bir bayrağa göre değil (docs/124).
 *
 * Üretim `pgsql`. Yalnız SQLite bilen bir bağlama orada fırlatıyordu ve
 * günlük tatbikat hiçbir kayıt üretemeyecekti. Çalışma kökü `storage/app`
 * hacminde ama `private/` (medya kökü) DIŞINDADIR: medya arşivi kendi
 * tatbikat artıklarını yutmasın.
 */
final class BackupRestoreDrillRunnerFactory
{
    private const WORK_ROOT = 'app/backup-restore-drill';

    public static function databaseRunnerFromConfig(): BackupRestoreDrillRunnerPort
    {
        $connection = (string) config('database.default');

        return match ($connection) {
            'sqlite' => self::sqlite(),
            'pgsql' => new PostgresBackupRestoreDrillRunner(
                (array) config('database.connections.pgsql'),
                storage_path(self::WORK_ROOT),
            ),
            default => throw new RuntimeException(sprintf(
                'The backup/restore drill has no runner for the "%s" database connection; only sqlite and pgsql are supported.',
                $connection,
            )),
        };
    }

    public static function mediaRunnerFromConfig(): MediaBackupRestoreDrillRunnerPort
    {
        return new TarMediaBackupRestoreDrillRunner(
            (string) config('filesystems.disks.local.root'),
            storage_path(self::WORK_ROOT),
        );
    }

    private static function sqlite(): SqliteBackupRestoreDrillRunner
    {
        $database = (string) config('database.connections.sqlite.database');

        if ($database === '' || $database === ':memory:' || ! is_file($database) || ! is_readable($database)) {
            throw new RuntimeException('The backup/restore drill runner requires a real, readable sqlite database file.');
        }

        return new SqliteBackupRestoreDrillRunner($database, storage_path(self::WORK_ROOT));
    }
}
