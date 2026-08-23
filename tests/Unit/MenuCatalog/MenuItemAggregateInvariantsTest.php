<?php

declare(strict_types=1);

namespace Tests\Unit\MenuCatalog;

use App\Domain\MenuCatalog\Category;
use App\Domain\MenuCatalog\MenuItem;
use App\Domain\MenuCatalog\Product;
use App\Domain\MenuCatalog\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Blind RED unit candidate for the S1-WP03 Menu Catalog Product/MenuItem
 * domain split (frozen MASTER decisions per task instruction: MenuItem is a
 * distinct aggregate wrapping a shared Product with its own Money price and
 * its own visibility, so one Product can appear as several MenuItems with
 * different prices and independently toggled visibility; Category owns
 * MenuItem ordering via a sequential unique integer `position`, mirroring
 * Menu's Category ordering; a MenuItem not belonging to a Category must be
 * rejected on reorder without corrupting existing positions).
 *
 * None of App\Domain\MenuCatalog\MenuItem exists yet, and Category does not
 * yet expose addMenuItem/reorderMenuItem/menuItems, so every test below is
 * expected to fail RED with a class-not-found/method-not-found error, not a
 * logic assertion failure.
 *
 * Requirement IDs: MENU-ITEM-PRICE-01, MENU-ITEM-VISIBILITY-DEFAULT-01,
 * MENU-ITEM-VISIBILITY-TOGGLE-01, MENU-ITEM-SHARED-PRODUCT-INDEPENDENCE-01,
 * MENU-ITEM-CATEGORY-ORDER-01, MENU-ITEM-CATEGORY-POSITION-UNIQUE-01,
 * MENU-ITEM-REORDER-MEMBERSHIP-GUARD-01.
 */
final class MenuItemAggregateInvariantsTest extends TestCase
{
    // --- MENU-ITEM-VISIBILITY-DEFAULT-01 -------------------------------------

    public function test_a_newly_created_menu_item_defaults_to_not_visible(): void
    {
        $product = Product::create('Mercimek Çorbası');
        $menuItem = MenuItem::create($product, Money::fromMinorAmount(4500, 'TRY'));

        self::assertFalse($menuItem->isVisible(), 'ITEM-VISIBILITY-DEFAULT-01: yeni oluşturulan menu item varsayılan olarak visible=false olmalı.');
    }

    // --- MENU-ITEM-VISIBILITY-TOGGLE-01 ---------------------------------------

    public function test_a_menu_item_can_be_made_visible_and_hidden_again(): void
    {
        $product = Product::create('Mercimek Çorbası');
        $menuItem = MenuItem::create($product, Money::fromMinorAmount(4500, 'TRY'));

        $menuItem->makeVisible();
        self::assertTrue($menuItem->isVisible());

        $menuItem->hide();
        self::assertFalse($menuItem->isVisible());
    }

    // --- MENU-ITEM-PRICE-01 -----------------------------------------------------

    public function test_a_menu_item_carries_its_own_price_as_a_money_value_object(): void
    {
        $price = Money::fromMinorAmount(4500, 'TRY');
        $product = Product::create('Mercimek Çorbası');
        $menuItem = MenuItem::create($product, $price);

        self::assertTrue($menuItem->price()->equals($price));
        self::assertSame($product, $menuItem->product());
    }

    // --- MENU-ITEM-SHARED-PRODUCT-INDEPENDENCE-01 --------------------------------

    public function test_one_product_shared_by_two_menu_items_has_independent_prices_and_visibility(): void
    {
        $sharedProduct = Product::create('Mercimek Çorbası');

        $lunchItem = MenuItem::create($sharedProduct, Money::fromMinorAmount(4500, 'TRY'));
        $dinnerItem = MenuItem::create($sharedProduct, Money::fromMinorAmount(6000, 'TRY'));

        $lunchItem->makeVisible();

        self::assertSame($sharedProduct, $lunchItem->product());
        self::assertSame($sharedProduct, $dinnerItem->product());

        self::assertSame(4500, $lunchItem->price()->minorAmount());
        self::assertSame(6000, $dinnerItem->price()->minorAmount());

        self::assertTrue($lunchItem->isVisible(), 'ITEM-SHARED-PRODUCT-INDEPENDENCE-01: bir MenuItem visible yapıldığında aynı Product\'ı paylaşan diğer MenuItem etkilenmemeli.');
        self::assertFalse($dinnerItem->isVisible(), 'ITEM-SHARED-PRODUCT-INDEPENDENCE-01: bir MenuItem visible yapıldığında aynı Product\'ı paylaşan diğer MenuItem etkilenmemeli.');
    }

    // --- MENU-ITEM-CATEGORY-ORDER-01 ------------------------------------------

    public function test_menu_items_added_to_a_category_receive_sequential_integer_positions(): void
    {
        $category = Category::create('Çorbalar');

        $soup = MenuItem::create(Product::create('Mercimek Çorbası'), Money::fromMinorAmount(4500, 'TRY'));
        $stew = MenuItem::create(Product::create('Ezogelin Çorbası'), Money::fromMinorAmount(5000, 'TRY'));

        $category->addMenuItem($soup);
        $category->addMenuItem($stew);

        self::assertSame(0, $soup->position(), 'ITEM-CATEGORY-ORDER-01: ilk eklenen menu item position 0 almalı.');
        self::assertSame(1, $stew->position(), 'ITEM-CATEGORY-ORDER-01: ikinci eklenen menu item position 1 almalı.');
    }

    public function test_reordering_a_menu_item_updates_its_position_and_shifts_others(): void
    {
        $category = Category::create('Çorbalar');
        $soup = MenuItem::create(Product::create('Mercimek Çorbası'), Money::fromMinorAmount(4500, 'TRY'));
        $stew = MenuItem::create(Product::create('Ezogelin Çorbası'), Money::fromMinorAmount(5000, 'TRY'));
        $salad = MenuItem::create(Product::create('Çoban Salata'), Money::fromMinorAmount(3500, 'TRY'));

        $category->addMenuItem($soup);
        $category->addMenuItem($stew);
        $category->addMenuItem($salad);

        $category->reorderMenuItem($salad, toPosition: 0);

        self::assertSame(0, $salad->position(), 'ITEM-CATEGORY-ORDER-01: taşınan menu item hedef position\'a taşınmalı.');
        self::assertSame(1, $soup->position(), 'ITEM-CATEGORY-ORDER-01: geri kalan menu itemlar kaydırılmalı.');
        self::assertSame(2, $stew->position());
    }

    // --- MENU-ITEM-CATEGORY-POSITION-UNIQUE-01 -----------------------------------

    public function test_a_category_never_holds_two_menu_items_with_the_same_position(): void
    {
        $category = Category::create('Çorbalar');
        $soup = MenuItem::create(Product::create('Mercimek Çorbası'), Money::fromMinorAmount(4500, 'TRY'));
        $stew = MenuItem::create(Product::create('Ezogelin Çorbası'), Money::fromMinorAmount(5000, 'TRY'));
        $salad = MenuItem::create(Product::create('Çoban Salata'), Money::fromMinorAmount(3500, 'TRY'));

        $category->addMenuItem($soup);
        $category->addMenuItem($stew);
        $category->addMenuItem($salad);
        $category->reorderMenuItem($stew, toPosition: 0);

        $positions = array_map(static fn (MenuItem $item): int => $item->position(), $category->menuItems());

        self::assertSame($positions, array_unique($positions), 'ITEM-CATEGORY-POSITION-UNIQUE-01: aynı kategoride iki menu item aynı position\'ı taşıyamaz.');
    }

    // --- MENU-ITEM-REORDER-MEMBERSHIP-GUARD-01 --------------------------------

    public function test_reordering_a_menu_item_that_is_not_a_member_of_the_category_is_rejected_without_corrupting_positions(): void
    {
        $category = Category::create('Çorbalar');
        $soup = MenuItem::create(Product::create('Mercimek Çorbası'), Money::fromMinorAmount(4500, 'TRY'));
        $stew = MenuItem::create(Product::create('Ezogelin Çorbası'), Money::fromMinorAmount(5000, 'TRY'));
        $category->addMenuItem($soup);
        $category->addMenuItem($stew);

        $foreignItem = MenuItem::create(Product::create('Yabancı Ürün'), Money::fromMinorAmount(1000, 'TRY'));

        $this->expectException(InvalidArgumentException::class);

        try {
            $category->reorderMenuItem($foreignItem, toPosition: 0);
        } finally {
            self::assertSame(0, $soup->position(), 'ITEM-REORDER-MEMBERSHIP-GUARD-01: kategoriye ait olmayan menu itemla reorder denemesi mevcut menu itemların position\'ını bozmamalı.');
            self::assertSame(1, $stew->position(), 'ITEM-REORDER-MEMBERSHIP-GUARD-01: kategoriye ait olmayan menu itemla reorder denemesi mevcut menu itemların position\'ını bozmamalı.');
            self::assertCount(2, $category->menuItems(), 'ITEM-REORDER-MEMBERSHIP-GUARD-01: reddedilen reorder kategoriye yeni bir menu item eklememeli.');
        }
    }
}
