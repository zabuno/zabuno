<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Port;

use App\Application\QrDestination\Dto\BulkQrCreationResult;
use App\Application\QrDestination\Exception\BulkQrCreationFailedException;

interface BulkQrCreationPort
{
    /**
     * @param  list<string>  $areaLabels  ordered "Area 1".."Area M" labels to persist
     * @param  list<string>  $tableNames  ordered table names, round-robin assigned across the persisted areas
     * @param  callable(string): bool  $isTokenTaken
     *
     * @throws BulkQrCreationFailedException
     */
    public function create(
        int $workspaceId,
        int $locationId,
        int $menuId,
        array $areaLabels,
        array $tableNames,
        int $seatCountPerTable,
        callable $isTokenTaken,
    ): BulkQrCreationResult;
}
