<?php

declare(strict_types=1);

namespace App\Application\Security\UseCase;

use App\Application\Security\Port\BackupRestoreEvidenceRepositoryPort;
use App\Domain\Security\BackupRestoreEvidenceRecord;

final class ShowBackupRestoreEvidence
{
    public function __construct(
        private readonly BackupRestoreEvidenceRepositoryPort $repository,
    ) {}

    public function execute(): ?BackupRestoreEvidenceRecord
    {
        return $this->repository->latest();
    }
}
