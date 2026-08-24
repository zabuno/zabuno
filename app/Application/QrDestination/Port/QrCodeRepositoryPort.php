<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Port;

use App\Application\QrDestination\Dto\QrCodeRecord;
use App\Application\QrDestination\Exception\QrCodePersistenceFailedException;

interface QrCodeRepositoryPort
{
    /**
     * @throws QrCodePersistenceFailedException
     */
    public function create(int $workspaceId, int $locationId, int $menuId, string $token): QrCodeRecord;

    /**
     * @return list<QrCodeRecord>
     */
    public function listForLocation(int $workspaceId, int $locationId): array;

    public function findById(int $qrCodeId): ?QrCodeRecord;

    /**
     * @throws QrCodePersistenceFailedException
     */
    public function disable(int $qrCodeId): QrCodeRecord;

    public function tokenExists(string $token): bool;

    public function findActiveByToken(string $token): ?QrCodeRecord;
}
