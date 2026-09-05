<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Dto;

final class MenuDraftSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $locationId,
        public readonly string $name,
        public readonly string $state,
        /** Menü hapının ekrandaki sırası (`docs/109` §7.1). */
        public readonly int $sortOrder = 0,
    ) {}
}
