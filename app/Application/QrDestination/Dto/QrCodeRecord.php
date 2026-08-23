<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Dto;

final class QrCodeRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $locationId,
        public readonly int $menuId,
        public readonly string $token,
        public readonly string $destinationType,
        public readonly string $state,
    ) {}
}
