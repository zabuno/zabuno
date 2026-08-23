<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

use InvalidArgumentException;

final class Menu
{
    /** @var array<int, Category> */
    private array $categories = [];

    private function __construct(
        private readonly int $locationId,
        private readonly string $name,
        private MenuState $state,
    ) {}

    public static function createDraft(int $locationId, string $name): self
    {
        return new self($locationId, $name, MenuState::Draft);
    }

    public function state(): MenuState
    {
        return $this->state;
    }

    public function locationId(): int
    {
        return $this->locationId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function addCategory(Category $category): void
    {
        $category->setPosition(count($this->categories));
        $this->categories[] = $category;
    }

    /** @return array<int, Category> */
    public function categories(): array
    {
        return $this->categories;
    }

    public function reorderCategory(Category $category, int $toPosition): void
    {
        if (! in_array($category, $this->categories, true)) {
            throw new InvalidArgumentException('Category does not belong to this menu.');
        }

        $ordered = $this->categories;
        usort($ordered, static fn (Category $a, Category $b): int => $a->position() <=> $b->position());

        $withoutTarget = array_values(array_filter(
            $ordered,
            static fn (Category $candidate): bool => $candidate !== $category
        ));

        $toPosition = max(0, min($toPosition, count($withoutTarget)));

        array_splice($withoutTarget, $toPosition, 0, [$category]);

        foreach ($withoutTarget as $index => $reordered) {
            $reordered->setPosition($index);
        }
    }
}
