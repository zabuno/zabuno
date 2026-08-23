<?php

declare(strict_types=1);

namespace App\Application\Security\UseCase;

use App\Application\Security\Port\SecurityEvidenceSnapshotPort;
use App\Application\Security\Port\TenantIsolationEvidenceRepositoryPort;
use App\Application\Security\Port\TenantIsolationSuiteRunnerPort;
use App\Domain\Security\TenantIsolationEvidenceRecord;
use App\Domain\Security\TenantIsolationSuiteManifest;
use Illuminate\Support\Carbon;

final class RecordTenantIsolationEvidence
{
    public function __construct(
        private readonly TenantIsolationSuiteRunnerPort $runner,
        private readonly SecurityEvidenceSnapshotPort $snapshot,
        private readonly TenantIsolationEvidenceRepositoryPort $repository,
    ) {}

    public function execute(): TenantIsolationEvidenceRecord
    {
        $paths = TenantIsolationSuiteManifest::paths();

        $result = $this->runner->run($paths);
        $snapshot = $this->snapshot->collect($paths);

        $record = TenantIsolationEvidenceRecord::fromRun(
            status: $result['passed'] ? 'passed' : 'failed',
            durationMs: $result['duration_ms'],
            exitCode: $result['exit_code'],
            gitSha: $snapshot['git_sha'],
            gitDirty: $snapshot['git_dirty'],
            sourceSnapshotSha256: $snapshot['source_snapshot_sha256'],
            suiteManifestSha256: $snapshot['suite_manifest_sha256'],
            outputSha256: hash('sha256', $result['output']),
            ranAt: Carbon::now()->toIso8601String(),
        );

        return $this->repository->append($record);
    }
}
