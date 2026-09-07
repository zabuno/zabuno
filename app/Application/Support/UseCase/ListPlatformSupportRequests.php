<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Application\Support\Dto\SupportRequestAdminRow;
use App\Application\Support\Port\SupportRequestRepositoryPort;
use App\Domain\Support\SupportRequestStatus;

final class ListPlatformSupportRequests
{
    public function __construct(
        private readonly SupportRequestRepositoryPort $requests,
    ) {}

    /** @return list<SupportRequestAdminRow> */
    public function handle(?SupportRequestStatus $status): array
    {
        return $this->requests->listForPlatform($status);
    }
}
