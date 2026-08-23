<?php

declare(strict_types=1);

namespace App\Application\Security\Port;

use App\Domain\Security\TenantIsolationEvidenceRecord;

interface TenantIsolationEvidenceRepositoryPort
{
    public function append(TenantIsolationEvidenceRecord $record): TenantIsolationEvidenceRecord;

    public function latest(): ?TenantIsolationEvidenceRecord;
}
