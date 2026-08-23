<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Dto;

final class DiningAreaRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $locationId,
        public readonly string $label,
    ) {}
}
