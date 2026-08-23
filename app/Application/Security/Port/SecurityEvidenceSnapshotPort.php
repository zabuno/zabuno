<?php

declare(strict_types=1);

namespace App\Application\Security\Port;

interface SecurityEvidenceSnapshotPort
{
    /**
     * @param  list<string>  $suitePaths
     * @return array{git_sha: string, git_dirty: bool, source_snapshot_sha256: string, suite_manifest_sha256: string}
     */
    public function collect(array $suitePaths): array;
}
