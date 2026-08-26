<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

use App\Domain\Money\Money;

final class MenuItem
{
    private bool $visible = false;

    private int $position = 0;

    private function __construct(
        private readonly Product $product,
        private readonly Money $price,
    ) {}

    public static function create(Product $product, Money $price): self
    {
        return new self($product, $price);
    }

    public function product(): Product
    {
        return $this->product;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function makeVisible(): void
    {
        $this->visible = true;
    }

    public function hide(): void
    {
        $this->visible = false;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
