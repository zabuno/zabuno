<?php

declare(strict_types=1);

namespace App\Application\Publication\Dto;

final class ScheduledPublicationRecord
{
    /**
     * @param  array<string,mixed>  $snapshot
     * @param  list<int>  $visibleItemIds
     */
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $menuId,
        public readonly int $locationId,
        /** ISO-8601, UTC. Ekrandaki saat bundan türetilir; tersi değil. */
        public readonly string $scheduledFor,
        public readonly string $state,
        public readonly array $snapshot,
        public readonly array $visibleItemIds,
        public readonly ?int $brandId,
        public readonly int $scheduledByUserId,
    ) {}
}
