<?php

declare(strict_types=1);

namespace App\Application\Publication\Exception;

use RuntimeException;

final class UnreadyDraftException extends RuntimeException
{
    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function noCategory(): self
    {
        return new self('Menu draft has no category.', 'no_category');
    }

    public static function noVisibleItem(): self
    {
        return new self('Menu draft has no visible item.', 'no_visible_item');
    }

    public static function blankCategoryName(): self
    {
        return new self('Menu draft has a category with a blank name.', 'blank_category_name');
    }

    public static function blankVisibleProductName(): self
    {
        return new self('Menu draft has a visible item with a blank product name.', 'blank_visible_product_name');
    }

    public static function nonPositiveVisiblePrice(): self
    {
        return new self('Menu draft has a visible item with a non-positive price.', 'non_positive_visible_price');
    }

    public static function blankVisibleCurrency(): self
    {
        return new self('Menu draft has a visible item with a blank currency.', 'blank_visible_currency');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
