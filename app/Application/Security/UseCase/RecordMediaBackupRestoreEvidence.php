<?php

declare(strict_types=1);

namespace App\Application\Security\UseCase;

use App\Application\Security\Port\MediaBackupRestoreDrillRunnerPort;
use App\Application\Security\Port\MediaBackupRestoreEvidenceRepositoryPort;
use App\Application\Security\Port\SecurityEvidenceSnapshotPort;
use App\Domain\Security\BackupRestoreTableManifest;
use App\Domain\Security\MediaBackupRestoreEvidenceRecord;
use Illuminate\Support\Carbon;

final class RecordMediaBackupRestoreEvidence
{
    public function __construct(
        private readonly MediaBackupRestoreDrillRunnerPort $runner,
        private readonly SecurityEvidenceSnapshotPort $snapshot,
        private readonly MediaBackupRestoreEvidenceRepositoryPort $repository,
    ) {}

    public function execute(): MediaBackupRestoreEvidenceRecord
    {
        $result = $this->runner->run();
        $snapshot = $this->snapshot->collect(BackupRestoreTableManifest::mediaSourcePaths());

        /*
            Koşucunun "geçti" demesi tek başına yetmez: sayılar, baytlar
            ve manifestler burada bir kez daha karşılaştırılır. Ölçülmemiş
            bir koşu BİLİNMİYOR'dur; "ölçülmedi ama geçti" çelişkisi
            "geçti" lehine çözülmez.
        */
        $measured = ($result['measured'] ?? true) === true;
        $agrees = $result['source_file_count'] === $result['restored_file_count']
            && $result['source_bytes'] === $result['restored_bytes']
            && hash_equals((string) $result['source_manifest_sha256'], (string) $result['restored_manifest_sha256']);

        $status = match (true) {
            ! $measured => 'unknown',
            $result['passed'] && $agrees => 'passed',
            default => 'failed',
        };

        $exitCode = $status === 'passed' ? 0 : ($result['exit_code'] !== 0 ? $result['exit_code'] : 1);

        $record = MediaBackupRestoreEvidenceRecord::fromRun(
            status: $status,
            durationMs: $result['duration_ms'],
            exitCode: $exitCode,
            gitSha: $snapshot['git_sha'],
            gitDirty: $snapshot['git_dirty'],
            sourceSnapshotSha256: $snapshot['source_snapshot_sha256'],
            suiteManifestSha256: $snapshot['suite_manifest_sha256'],
            archiveSha256: $result['archive_sha256'],
            archiveBytes: $result['archive_bytes'],
            sourceManifestSha256: $result['source_manifest_sha256'],
            restoredManifestSha256: $result['restored_manifest_sha256'],
            sourceFileCount: $result['source_file_count'],
            restoredFileCount: $result['restored_file_count'],
            sourceBytes: $result['source_bytes'],
            restoredBytes: $result['restored_bytes'],
            outputSha256: hash('sha256', $result['output']),
            ranAt: Carbon::now()->toIso8601String(),
        );

        return $this->repository->append($record);
    }
}
