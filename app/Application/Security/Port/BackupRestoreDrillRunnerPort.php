<?php

declare(strict_types=1);

namespace App\Application\Security\Port;

interface BackupRestoreDrillRunnerPort
{
    /**
     * @param  list<string>  $tables
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, backup_sha256: string, restored_db_sha256: string, source_row_count: int, restored_row_count: int, restored_integrity_ok: bool}
     */
    public function run(array $tables): array;
}
