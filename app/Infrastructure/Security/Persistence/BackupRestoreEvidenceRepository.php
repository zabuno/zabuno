<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Persistence;

use App\Application\Security\Port\BackupRestoreEvidenceRepositoryPort;
use App\Domain\Security\BackupRestoreEvidenceRecord;
use Illuminate\Support\Facades\DB;

final class BackupRestoreEvidenceRepository implements BackupRestoreEvidenceRepositoryPort
{
    private const TABLE = 'backup_restore_evidence';

    public function append(BackupRestoreEvidenceRecord $record): BackupRestoreEvidenceRecord
    {
        $now = now();

        $id = (int) DB::table(self::TABLE)->insertGetId([
            'key' => $record->key(),
            'status' => $record->status(),
            'scope' => $record->scope(),
            'runner' => $record->runner(),
            'driver' => $record->driver(),
            'ran_at' => $record->ranAt(),
            'duration_ms' => $record->durationMs(),
            'exit_code' => $record->exitCode(),
            'git_sha' => $record->gitSha(),
            'git_dirty' => $record->gitDirty(),
            'source_snapshot_sha256' => $record->sourceSnapshotSha256(),
            'suite_manifest_sha256' => $record->suiteManifestSha256(),
            'backup_sha256' => $record->backupSha256(),
            'restored_db_sha256' => $record->restoredDbSha256(),
            'source_row_count' => $record->sourceRowCount(),
            'restored_row_count' => $record->restoredRowCount(),
            'backup_bytes' => $record->backupBytes(),
            'backup_ms' => $record->backupMs(),
            'restore_ms' => $record->restoreMs(),
            'output_sha256' => $record->outputSha256(),
            'integrity_sha256' => $record->integritySha256(),
            'claim' => $record->claim(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return BackupRestoreEvidenceRecord::reconstitute(
            id: $id,
            key: $record->key(),
            status: $record->status(),
            scope: $record->scope(),
            runner: $record->runner(),
            driver: $record->driver(),
            ranAt: $record->ranAt(),
            durationMs: $record->durationMs(),
            exitCode: $record->exitCode(),
            gitSha: $record->gitSha(),
            gitDirty: $record->gitDirty(),
            sourceSnapshotSha256: $record->sourceSnapshotSha256(),
            suiteManifestSha256: $record->suiteManifestSha256(),
            backupSha256: $record->backupSha256(),
            restoredDbSha256: $record->restoredDbSha256(),
            sourceRowCount: $record->sourceRowCount(),
            restoredRowCount: $record->restoredRowCount(),
            backupBytes: $record->backupBytes(),
            backupMs: $record->backupMs(),
            restoreMs: $record->restoreMs(),
            outputSha256: $record->outputSha256(),
            integritySha256: $record->integritySha256(),
            claim: $record->claim(),
        );
    }

    public function latest(): ?BackupRestoreEvidenceRecord
    {
        $row = DB::table(self::TABLE)->orderByDesc('id')->first();

        if ($row === null) {
            return null;
        }

        return BackupRestoreEvidenceRecord::reconstitute(
            id: (int) $row->id,
            key: (string) $row->key,
            status: (string) $row->status,
            scope: (string) $row->scope,
            runner: (string) $row->runner,
            driver: (string) $row->driver,
            ranAt: (string) $row->ran_at,
            durationMs: (int) $row->duration_ms,
            exitCode: (int) $row->exit_code,
            gitSha: (string) $row->git_sha,
            gitDirty: (bool) $row->git_dirty,
            sourceSnapshotSha256: (string) $row->source_snapshot_sha256,
            suiteManifestSha256: (string) $row->suite_manifest_sha256,
            backupSha256: (string) $row->backup_sha256,
            restoredDbSha256: (string) $row->restored_db_sha256,
            sourceRowCount: (int) $row->source_row_count,
            restoredRowCount: (int) $row->restored_row_count,
            backupBytes: (int) $row->backup_bytes,
            backupMs: (int) $row->backup_ms,
            restoreMs: (int) $row->restore_ms,
            outputSha256: (string) $row->output_sha256,
            integritySha256: (string) $row->integrity_sha256,
            claim: (string) $row->claim,
        );
    }
}
