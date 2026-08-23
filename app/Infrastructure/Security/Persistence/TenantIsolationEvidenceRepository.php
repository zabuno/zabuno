<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Persistence;

use App\Application\Security\Port\TenantIsolationEvidenceRepositoryPort;
use App\Domain\Security\TenantIsolationEvidenceRecord;
use Illuminate\Support\Facades\DB;

final class TenantIsolationEvidenceRepository implements TenantIsolationEvidenceRepositoryPort
{
    private const TABLE = 'tenant_isolation_evidence';

    public function append(TenantIsolationEvidenceRecord $record): TenantIsolationEvidenceRecord
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
            'output_sha256' => $record->outputSha256(),
            'integrity_sha256' => $record->integritySha256(),
            'claim' => $record->claim(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return TenantIsolationEvidenceRecord::reconstitute(
            id: $id,
            key: $record->key(),
            status: $record->status(),
            scope: $record->scope(),
            runner: $record->runner(),
            ranAt: $record->ranAt(),
            durationMs: $record->durationMs(),
            exitCode: $record->exitCode(),
            gitSha: $record->gitSha(),
            gitDirty: $record->gitDirty(),
            sourceSnapshotSha256: $record->sourceSnapshotSha256(),
            suiteManifestSha256: $record->suiteManifestSha256(),
            outputSha256: $record->outputSha256(),
            integritySha256: $record->integritySha256(),
            claim: $record->claim(),
        );
    }

    public function latest(): ?TenantIsolationEvidenceRecord
    {
        $row = DB::table(self::TABLE)->orderByDesc('id')->first();

        if ($row === null) {
            return null;
        }

        return TenantIsolationEvidenceRecord::reconstitute(
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
            outputSha256: (string) $row->output_sha256,
            integritySha256: (string) $row->integrity_sha256,
            claim: (string) $row->claim,
        );
    }
}
