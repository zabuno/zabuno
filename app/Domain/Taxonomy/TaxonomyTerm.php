<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy;

final class TaxonomyTerm
{
    private function __construct(
        private readonly string $name,
        private readonly string $type,
    ) {}

    public static function create(string $name, string $type): self
    {
        return new self($name, $type);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }
}
