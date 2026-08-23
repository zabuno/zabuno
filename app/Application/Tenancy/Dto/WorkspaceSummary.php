<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Dto;

use App\Domain\Tenancy\WorkspaceState;

final class WorkspaceSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly WorkspaceState $state,
    ) {}

    /**
     * @return array{id:int,name:string,slug:string,state:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'state' => $this->state->value,
        ];
    }
}
