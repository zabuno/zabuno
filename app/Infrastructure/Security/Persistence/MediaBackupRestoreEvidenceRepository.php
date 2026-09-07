<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Persistence;

use App\Application\Security\Port\MediaBackupRestoreEvidenceRepositoryPort;
use App\Domain\Security\MediaBackupRestoreEvidenceRecord;
use Illuminate\Support\Facades\DB;

final class MediaBackupRestoreEvidenceRepository implements MediaBackupRestoreEvidenceRepositoryPort
{
    private const TABLE = 'media_backup_restore_evidence';

    public function append(MediaBackupRestoreEvidenceRecord $record): MediaBackupRestoreEvidenceRecord
    {
        $now = now();

        $id = (int) DB::table(self::TABLE)->insertGetId([
            'key' => $record->key(),
            'status' => $record->status(),
            'scope' => $record->scope(),
            'runner' => $record->runner(),
            'ran_at' => $record->ranAt(),
            'duration_ms' => $record->durationMs(),
            'exit_code' => $record->exitCode(),
            'git_sha' => $record->gitSha(),
            'git_dirty' => $record->gitDirty(),
            'source_snapshot_sha256' => $record->sourceSnapshotSha256(),
            'suite_manifest_sha256' => $record->suiteManifestSha256(),
            'archive_sha256' => $record->archiveSha256(),
            'archive_bytes' => $record->archiveBytes(),
            'source_manifest_sha256' => $record->sourceManifestSha256(),
            'restored_manifest_sha256' => $record->restoredManifestSha256(),
            'source_file_count' => $record->sourceFileCount(),
            'restored_file_count' => $record->restoredFileCount(),
            'source_bytes' => $record->sourceBytes(),
            'restored_bytes' => $record->restoredBytes(),
            'output_sha256' => $record->outputSha256(),
            'integrity_sha256' => $record->integritySha256(),
            'claim' => $record->claim(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->reconstitute((object) array_merge($record->toArray(), ['id' => $id, 'ran_at' => $record->ranAt()]));
    }

    public function latest(): ?MediaBackupRestoreEvidenceRecord
    {
        $row = DB::table(self::TABLE)->orderByDesc('id')->first();

        if ($row === null) {
            return null;
        }

        return $this->reconstitute($row);
    }

    private function reconstitute(object $row): MediaBackupRestoreEvidenceRecord
    {
        return MediaBackupRestoreEvidenceRecord::reconstitute(
            id: (int) $row->id,
            key: (string) $row->key,
            status: (string) $row->status,
            scope: (string) $row->scope,
            runner: (string) $row->runner,
            ranAt: (string) $row->ran_at,
            durationMs: (int) $row->duration_ms,
            exitCode: (int) $row->exit_code,
            gitSha: (string) $row->git_sha,
            gitDirty: (bool) $row->git_dirty,
            sourceSnapshotSha256: (string) $row->source_snapshot_sha256,
            suiteManifestSha256: (string) $row->suite_manifest_sha256,
            archiveSha256: (string) $row->archive_sha256,
            archiveBytes: (int) $row->archive_bytes,
            sourceManifestSha256: (string) $row->source_manifest_sha256,
            restoredManifestSha256: (string) $row->restored_manifest_sha256,
            sourceFileCount: (int) $row->source_file_count,
            restoredFileCount: (int) $row->restored_file_count,
            sourceBytes: (int) $row->source_bytes,
            restoredBytes: (int) $row->restored_bytes,
            outputSha256: (string) $row->output_sha256,
            integritySha256: (string) $row->integrity_sha256,
            claim: (string) $row->claim,
        );
    }
}
