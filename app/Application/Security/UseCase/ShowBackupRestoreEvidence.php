<?php

declare(strict_types=1);

namespace App\Application\Security\UseCase;

use App\Application\Security\Port\BackupRestoreEvidenceRepositoryPort;
use App\Application\Security\Port\MediaBackupRestoreEvidenceRepositoryPort;
use App\Domain\Security\BackupRestoreEvidenceRecord;
use App\Domain\Security\MediaBackupRestoreEvidenceRecord;

final class ShowBackupRestoreEvidence
{
    public function __construct(
        private readonly BackupRestoreEvidenceRepositoryPort $repository,
        private readonly MediaBackupRestoreEvidenceRepositoryPort $mediaRepository,
    ) {}

    public function execute(): ?BackupRestoreEvidenceRecord
    {
        return $this->repository->latest();
    }

    public function latestMedia(): ?MediaBackupRestoreEvidenceRecord
    {
        return $this->mediaRepository->latest();
    }
}
