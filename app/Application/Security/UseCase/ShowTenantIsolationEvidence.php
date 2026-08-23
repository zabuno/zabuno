<?php

declare(strict_types=1);

namespace App\Application\Security\UseCase;

use App\Application\Security\Port\TenantIsolationEvidenceRepositoryPort;
use App\Domain\Security\TenantIsolationEvidenceRecord;

final class ShowTenantIsolationEvidence
{
    public function __construct(
        private readonly TenantIsolationEvidenceRepositoryPort $repository,
    ) {}

    public function execute(): ?TenantIsolationEvidenceRecord
    {
        return $this->repository->latest();
    }
}
