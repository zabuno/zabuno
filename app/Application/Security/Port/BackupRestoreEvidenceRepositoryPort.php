<?php

declare(strict_types=1);

namespace App\Application\Security\Port;

use App\Domain\Security\BackupRestoreEvidenceRecord;

interface BackupRestoreEvidenceRepositoryPort
{
    public function append(BackupRestoreEvidenceRecord $record): BackupRestoreEvidenceRecord;

    public function latest(): ?BackupRestoreEvidenceRecord;
}
