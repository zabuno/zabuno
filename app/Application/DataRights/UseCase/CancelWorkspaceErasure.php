<?php

declare(strict_types=1);

namespace App\Application\DataRights\UseCase;

use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Domain\DataRights\DataRequestKind;
use App\Domain\DataRights\DataRequestState;

/**
 * "Vazgeçtim" — FF-226.
 *
 * Pencerenin tek anlamı budur: geri alınabilir olması. İptal SATIRI
 * SİLMEZ, hâlini değiştirir — "bir gün silme istendi ve vazgeçildi"
 * cümlesi de bir kayıttır ve denetim izinde görünür.
 */
final readonly class CancelWorkspaceErasure
{
    public function __construct(private DataRequestRepositoryPort $requests) {}

    public function handle(int $workspaceId, int $requestId, int $userId): bool
    {
        $request = $this->requests->find($requestId);

        if ($request === null
            || $request->workspaceId !== $workspaceId
            || $request->kind !== DataRequestKind::Erasure
            || $request->state !== DataRequestState::Scheduled) {
            return false;
        }

        $this->requests->markCancelled($requestId, $userId);

        return true;
    }
}
