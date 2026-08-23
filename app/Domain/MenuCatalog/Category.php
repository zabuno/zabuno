<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

use InvalidArgumentException;

final class Category
{
    private int $position = 0;

    /** @var array<int, MenuItem> */
    private array $menuItems = [];

    private function __construct(private readonly string $name) {}

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function addMenuItem(MenuItem $menuItem): void
    {
        $menuItem->setPosition(count($this->menuItems));
        $this->menuItems[] = $menuItem;
    }

    /** @return array<int, MenuItem> */
    public function menuItems(): array
    {
        return $this->menuItems;
    }

    public function reorderMenuItem(MenuItem $menuItem, int $toPosition): void
    {
        if (! in_array($menuItem, $this->menuItems, true)) {
            throw new InvalidArgumentException('Menu item does not belong to this category.');
        }

        $ordered = $this->menuItems;
        usort($ordered, static fn (MenuItem $a, MenuItem $b): int => $a->position() <=> $b->position());

        $withoutTarget = array_values(array_filter(
            $ordered,
            static fn (MenuItem $candidate): bool => $candidate !== $menuItem
        ));

        $toPosition = max(0, min($toPosition, count($withoutTarget)));

        array_splice($withoutTarget, $toPosition, 0, [$menuItem]);

        foreach ($withoutTarget as $index => $reordered) {
            $reordered->setPosition($index);
        }
    }
}
