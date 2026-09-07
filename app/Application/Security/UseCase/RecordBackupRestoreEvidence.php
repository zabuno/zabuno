<?php

declare(strict_types=1);

namespace App\Application\Security\UseCase;

use App\Application\Security\Port\BackupRestoreDrillRunnerPort;
use App\Application\Security\Port\BackupRestoreEvidenceRepositoryPort;
use App\Application\Security\Port\SecurityEvidenceSnapshotPort;
use App\Domain\Security\BackupRestoreEvidenceRecord;
use App\Domain\Security\BackupRestoreTableManifest;
use Illuminate\Support\Carbon;

final class RecordBackupRestoreEvidence
{
    public function __construct(
        private readonly BackupRestoreDrillRunnerPort $runner,
        private readonly SecurityEvidenceSnapshotPort $snapshot,
        private readonly BackupRestoreEvidenceRepositoryPort $repository,
    ) {}

    public function execute(): BackupRestoreEvidenceRecord
    {
        $tables = BackupRestoreTableManifest::tables();

        $result = $this->runner->run($tables);
        $driver = $this->runner->driver();
        $snapshot = $this->snapshot->collect(BackupRestoreTableManifest::sourcePaths());

        /*
            Koşucunun "geçti" demesi yetmez: geri yüklenen kopyanın bütünlük
            sinyali de açıkça doğru olmalı (eksik sinyal = yanlış sinyal).
            Ölçülmemiş bir koşu ise ne geçmiş ne kalmıştır — BİLİNMİYOR.
            "Ölçülmedi ama geçti" diyen bir sonuç kendiyle çelişir ve
            çelişki hiçbir zaman "geçti" lehine çözülmez.
        */
        $measured = ($result['measured'] ?? true) === true;
        $integrityOk = ($result['restored_integrity_ok'] ?? false) === true;

        $status = match (true) {
            ! $measured => 'unknown',
            $result['passed'] && $integrityOk => 'passed',
            default => 'failed',
        };

        $exitCode = $status === 'passed' ? 0 : ($result['exit_code'] !== 0 ? $result['exit_code'] : 1);

        $record = BackupRestoreEvidenceRecord::fromRun(
            status: $status,
            durationMs: $result['duration_ms'],
            exitCode: $exitCode,
            gitSha: $snapshot['git_sha'],
            gitDirty: $snapshot['git_dirty'],
            sourceSnapshotSha256: $snapshot['source_snapshot_sha256'],
            suiteManifestSha256: $snapshot['suite_manifest_sha256'],
            backupSha256: $result['backup_sha256'],
            restoredDbSha256: $result['restored_db_sha256'],
            sourceRowCount: $result['source_row_count'],
            restoredRowCount: $result['restored_row_count'],
            outputSha256: hash('sha256', $result['output']),
            ranAt: Carbon::now()->toIso8601String(),
            driver: $driver,
            backupBytes: (int) ($result['backup_bytes'] ?? 0),
            backupMs: (int) ($result['backup_ms'] ?? 0),
            restoreMs: (int) ($result['restore_ms'] ?? 0),
        );

        return $this->repository->append($record);
    }
}
