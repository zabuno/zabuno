<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

use App\Domain\Taxonomy\TaxonomyTerm;
use InvalidArgumentException;

final class Product
{
    /** @var array<int, TaxonomyTerm> */
    private array $allergens = [];

    private function __construct(
        private readonly string $name,
    ) {}

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function attachAllergen(TaxonomyTerm $term): void
    {
        if ($term->type() !== 'allergen') {
            throw new InvalidArgumentException('Only taxonomy terms of type [allergen] can be attached to a product.');
        }

        foreach ($this->allergens as $existing) {
            if ($existing->name() === $term->name() && $existing->type() === $term->type()) {
                return;
            }
        }

        $this->allergens[] = $term;
    }

    /** @return array<int, TaxonomyTerm> */
    public function allergens(): array
    {
        return $this->allergens;
    }
}
