<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Execution;

use App\Application\Security\Port\BackupRestoreDrillRunnerPort;
use App\Domain\Security\BackupRestoreTableManifest;
use RuntimeException;
use SQLite3;
use Throwable;

final class SqliteBackupRestoreDrillRunner implements BackupRestoreDrillRunnerPort
{
    private const DEFAULT_WORK_ROOT_NAME = 'zabuno-backup-restore-drill';

    private const TEMP_CHILD_PREFIX = 'drill-';

    public function __construct(
        private readonly string $sourcePath,
        private readonly ?string $workRoot = null,
    ) {}

    /**
     * @param  list<string>  $tables
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, backup_sha256: string, restored_db_sha256: string, source_row_count: int, restored_row_count: int, restored_integrity_ok: bool}
     */
    public function run(array $tables): array
    {
        if ($tables !== BackupRestoreTableManifest::tables()) {
            throw new \InvalidArgumentException('BackupRestoreDrillRunnerPort::run() only accepts the frozen table manifest.');
        }

        $startedAt = hrtime(true);
        $tempChild = null;

        try {
            if ($this->sourcePath === '' || $this->sourcePath === ':memory:' || ! is_file($this->sourcePath) || ! is_readable($this->sourcePath)) {
                throw new RuntimeException('Unsupported or missing SQLite source database.');
            }

            $workRoot = $this->resolveWorkRoot();
            if (! is_dir($workRoot) && ! @mkdir($workRoot, 0700, true) && ! is_dir($workRoot)) {
                throw new RuntimeException('Unable to prepare the backup/restore drill work root.');
            }

            $tempChild = $workRoot.DIRECTORY_SEPARATOR.self::TEMP_CHILD_PREFIX.bin2hex(random_bytes(16));
            if (! mkdir($tempChild, 0700, true)) {
                throw new RuntimeException('Unable to create a guarded temp child for the backup/restore drill.');
            }

            $backupPath = $tempChild.DIRECTORY_SEPARATOR.'backup.sqlite';
            $restoredPath = $tempChild.DIRECTORY_SEPARATOR.'restored.sqlite';

            [$sourceRowCount, $backupOk] = $this->backupSource($tables, $backupPath);

            if (! $backupOk) {
                throw new RuntimeException('SQLite online backup failed.');
            }

            if (! copy($backupPath, $restoredPath)) {
                throw new RuntimeException('Unable to copy the backup image into an isolated restore image.');
            }

            [$restoredRowCount, $restoredIntegrityOk] = $this->verifyRestored($tables, $restoredPath);

            $backupSha256 = hash_file('sha256', $backupPath);
            $restoredDbSha256 = hash_file('sha256', $restoredPath);

            if ($backupSha256 === false || $restoredDbSha256 === false) {
                throw new RuntimeException('Unable to hash the backup/restore drill artifacts.');
            }

            $passed = $restoredIntegrityOk
                && $sourceRowCount === $restoredRowCount
                && hash_equals($backupSha256, $restoredDbSha256);

            return [
                'passed' => $passed,
                'exit_code' => $passed ? 0 : 1,
                'duration_ms' => $this->elapsedMs($startedAt),
                'output' => $passed ? 'backup/restore drill passed' : 'backup/restore drill failed integrity or count verification',
                'backup_sha256' => $backupSha256,
                'restored_db_sha256' => $restoredDbSha256,
                'source_row_count' => $sourceRowCount,
                'restored_row_count' => $restoredRowCount,
                'restored_integrity_ok' => $restoredIntegrityOk,
            ];
        } catch (Throwable $e) {
            return [
                'passed' => false,
                'exit_code' => 1,
                'duration_ms' => $this->elapsedMs($startedAt),
                'output' => 'backup/restore drill failed: '.$e->getMessage(),
                'backup_sha256' => str_repeat('0', 64),
                'restored_db_sha256' => str_repeat('0', 64),
                'source_row_count' => 0,
                'restored_row_count' => 0,
                'restored_integrity_ok' => false,
            ];
        } finally {
            $this->cleanupTempChild($tempChild);
        }
    }

    /**
     * @param  list<string>  $tables
     * @return array{0: int, 1: bool}
     */
    private function backupSource(array $tables, string $backupPath): array
    {
        $source = new SQLite3($this->sourcePath, SQLITE3_OPEN_READONLY);
        $source->enableExceptions(true);
        $source->busyTimeout(5000);

        try {
            $source->exec('BEGIN');

            $rowCount = $this->countRows($source, $tables);

            $destination = new SQLite3($backupPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $destination->enableExceptions(true);

            try {
                $backupOk = $source->backup($destination);
            } finally {
                $destination->close();
            }

            $source->exec('COMMIT');

            return [$rowCount, $backupOk];
        } finally {
            $source->close();
        }
    }

    /**
     * @param  list<string>  $tables
     * @return array{0: int, 1: bool}
     */
    private function verifyRestored(array $tables, string $restoredPath): array
    {
        $restored = new SQLite3($restoredPath, SQLITE3_OPEN_READONLY);
        $restored->enableExceptions(true);

        try {
            $integrityResult = $restored->querySingle('PRAGMA integrity_check');
            $integrityOk = $integrityResult === 'ok';
            $rowCount = $this->countRows($restored, $tables);

            return [$rowCount, $integrityOk];
        } finally {
            $restored->close();
        }
    }

    /**
     * @param  list<string>  $tables
     */
    private function countRows(SQLite3 $db, array $tables): int
    {
        $total = 0;

        foreach ($tables as $table) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
                throw new RuntimeException('Refusing to count an unsafe table name.');
            }

            $count = $db->querySingle('SELECT COUNT(*) FROM "'.$table.'"');
            $total += (int) $count;
        }

        return $total;
    }

    private function resolveWorkRoot(): string
    {
        if ($this->workRoot !== null) {
            return $this->workRoot;
        }

        return sys_get_temp_dir().DIRECTORY_SEPARATOR.self::DEFAULT_WORK_ROOT_NAME;
    }

    private function cleanupTempChild(?string $tempChild): void
    {
        if ($tempChild === null) {
            return;
        }

        $workRoot = $this->resolveWorkRoot();
        $basename = basename($tempChild);

        if (! str_starts_with($tempChild, rtrim($workRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            return;
        }

        if (! str_starts_with($basename, self::TEMP_CHILD_PREFIX)) {
            return;
        }

        if (! is_dir($tempChild)) {
            return;
        }

        foreach (['backup.sqlite', 'restored.sqlite'] as $artifact) {
            $path = $tempChild.DIRECTORY_SEPARATOR.$artifact;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($tempChild);
    }

    private function elapsedMs(int|float $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
