<?php

declare(strict_types=1);

namespace App\Application\Security\Port;

use App\Domain\Security\MediaBackupRestoreEvidenceRecord;

interface MediaBackupRestoreEvidenceRepositoryPort
{
    public function append(MediaBackupRestoreEvidenceRecord $record): MediaBackupRestoreEvidenceRecord;

    public function latest(): ?MediaBackupRestoreEvidenceRecord;
}
