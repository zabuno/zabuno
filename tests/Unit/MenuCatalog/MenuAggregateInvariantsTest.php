<?php

declare(strict_types=1);

namespace Tests\Unit\MenuCatalog;

use App\Domain\MenuCatalog\Category;
use App\Domain\MenuCatalog\Menu;
use App\Domain\MenuCatalog\MenuState;
use App\Domain\MenuCatalog\Product;
use App\Domain\Taxonomy\TaxonomyTerm;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Blind RED unit candidate for the S1-WP03 Menu Catalog Product/MenuItem
 * domain split (frozen MASTER decisions per task instruction: Product is a
 * catalog-level identity carrying only name + allergens; price, visibility
 * and position are owned by MenuItem, a distinct aggregate that wraps a
 * Product for a given Category placement — a single Product can be shared by
 * multiple MenuItems with independent prices and independent visibility;
 * Category/Menu ordering uses an integer `position`; a general taxonomy term
 * must carry type=allergen before it can be attached to a Product, and the
 * Product<->term relation is many-to-many with no duplicate attach).
 *
 * Product::create now takes name only (price moved to MenuItem::create).
 * Category/Menu ordering tests are unchanged in shape. MenuItem-owned
 * concerns (price, visibility, per-category position) have moved to
 * tests/Unit/MenuCatalog/MenuItemAggregateInvariantsTest.php.
 *
 * Requirement IDs: MENU-DRAFT-STATE-01, MENU-CATEGORY-ORDER-01,
 * MENU-CATEGORY-POSITION-UNIQUE-01, MENU-ALLERGEN-TYPE-GUARD-01,
 * MENU-ALLERGEN-ATTACH-NO-DUPLICATE-01, MENU-CATEGORY-REORDER-MEMBERSHIP-GUARD-01,
 * MENU-ALLERGEN-ATTACH-SEMANTIC-DEDUPLICATION-01,
 * MENU-PRODUCT-MENUITEM-SPLIT-01 (Product::create(name) signature narrowed).
 */
final class MenuAggregateInvariantsTest extends TestCase
{
    // --- MENU-DRAFT-STATE-01 ------------------------------------------------

    public function test_a_newly_created_menu_starts_in_the_draft_state(): void
    {
        $menu = Menu::createDraft(locationId: 1, name: 'Ana Menü');

        self::assertSame(MenuState::Draft, $menu->state(), 'DRAFT-STATE-01: yeni oluşturulan menü draft durumunda başlamalı.');
        self::assertSame(1, $menu->locationId());
        self::assertSame('Ana Menü', $menu->name());
    }

    // --- MENU-CATEGORY-ORDER-01 --------------------------------------------

    public function test_categories_added_to_a_menu_receive_sequential_integer_positions(): void
    {
        $menu = Menu::createDraft(locationId: 1, name: 'Ana Menü');

        $starters = Category::create('Başlangıçlar');
        $mains = Category::create('Ana Yemekler');

        $menu->addCategory($starters);
        $menu->addCategory($mains);

        self::assertSame(0, $starters->position(), 'CATEGORY-ORDER-01: ilk eklenen kategori position 0 almalı.');
        self::assertSame(1, $mains->position(), 'CATEGORY-ORDER-01: ikinci eklenen kategori position 1 almalı.');
    }

    public function test_reordering_a_category_updates_its_position_and_shifts_others(): void
    {
        $menu = Menu::createDraft(locationId: 1, name: 'Ana Menü');
        $starters = Category::create('Başlangıçlar');
        $mains = Category::create('Ana Yemekler');
        $desserts = Category::create('Tatlılar');

        $menu->addCategory($starters);
        $menu->addCategory($mains);
        $menu->addCategory($desserts);

        $menu->reorderCategory($desserts, toPosition: 0);

        self::assertSame(0, $desserts->position(), 'CATEGORY-ORDER-01: taşınan kategori hedef position\'a taşınmalı.');
        self::assertSame(1, $starters->position(), 'CATEGORY-ORDER-01: geri kalan kategoriler kaydırılmalı.');
        self::assertSame(2, $mains->position());
    }

    // --- MENU-CATEGORY-POSITION-UNIQUE-01 -----------------------------------

    public function test_a_menu_never_holds_two_categories_with_the_same_position(): void
    {
        $menu = Menu::createDraft(locationId: 1, name: 'Ana Menü');
        $starters = Category::create('Başlangıçlar');
        $mains = Category::create('Ana Yemekler');
        $desserts = Category::create('Tatlılar');

        $menu->addCategory($starters);
        $menu->addCategory($mains);
        $menu->addCategory($desserts);
        $menu->reorderCategory($mains, toPosition: 0);

        $positions = array_map(static fn (Category $category): int => $category->position(), $menu->categories());

        self::assertSame($positions, array_unique($positions), 'CATEGORY-POSITION-UNIQUE-01: aynı menüde iki kategori aynı position\'ı taşıyamaz.');
    }

    // --- MENU-PRODUCT-MENUITEM-SPLIT-01 --------------------------------------

    public function test_a_product_is_created_from_name_only_and_carries_no_price_or_visibility(): void
    {
        $product = Product::create('Mercimek Çorbası');

        self::assertSame('Mercimek Çorbası', $product->name());
        self::assertFalse(
            method_exists($product, 'price') || method_exists($product, 'isVisible'),
            'PRODUCT-MENUITEM-SPLIT-01: Product artık price/visibility taşımamalı, bunlar MenuItem\'e taşınmalı.'
        );
    }

    // --- MENU-ALLERGEN-TYPE-GUARD-01 ------------------------------------------

    public function test_attaching_a_non_allergen_taxonomy_term_to_a_product_is_rejected(): void
    {
        $product = Product::create('Mercimek Çorbası');
        $notAnAllergenTerm = TaxonomyTerm::create('floor-1', type: 'floor-area');

        $this->expectException(InvalidArgumentException::class);

        $product->attachAllergen($notAnAllergenTerm);
    }

    public function test_attaching_an_allergen_typed_taxonomy_term_succeeds(): void
    {
        $product = Product::create('Mercimek Çorbası');
        $glutenTerm = TaxonomyTerm::create('gluten', type: 'allergen');

        $product->attachAllergen($glutenTerm);

        self::assertCount(1, $product->allergens());
        self::assertSame('gluten', $product->allergens()[0]->name());
    }

    // --- MENU-ALLERGEN-ATTACH-NO-DUPLICATE-01 --------------------------------

    public function test_attaching_the_same_allergen_term_twice_does_not_duplicate_it(): void
    {
        $product = Product::create('Mercimek Çorbası');
        $glutenTerm = TaxonomyTerm::create('gluten', type: 'allergen');

        $product->attachAllergen($glutenTerm);
        $product->attachAllergen($glutenTerm);

        self::assertCount(1, $product->allergens(), 'ALLERGEN-ATTACH-NO-DUPLICATE-01: aynı terim iki kez eklenirse tekrarlanmamalı (many-to-many, no duplicate).');
    }

    // --- Reviewer P2 correction: MENU-CATEGORY-REORDER-MEMBERSHIP-GUARD-01 ---

    public function test_reordering_a_category_that_is_not_a_member_of_the_menu_is_rejected_without_corrupting_positions(): void
    {
        $menu = Menu::createDraft(locationId: 1, name: 'Ana Menü');
        $starters = Category::create('Başlangıçlar');
        $mains = Category::create('Ana Yemekler');
        $menu->addCategory($starters);
        $menu->addCategory($mains);

        $foreignCategory = Category::create('Yabancı Kategori');

        $this->expectException(InvalidArgumentException::class);

        try {
            $menu->reorderCategory($foreignCategory, toPosition: 0);
        } finally {
            self::assertSame(0, $starters->position(), 'CATEGORY-REORDER-MEMBERSHIP-GUARD-01: menüye ait olmayan kategoriyle reorder denemesi mevcut kategorilerin position\'ını bozmamalı.');
            self::assertSame(1, $mains->position(), 'CATEGORY-REORDER-MEMBERSHIP-GUARD-01: menüye ait olmayan kategoriyle reorder denemesi mevcut kategorilerin position\'ını bozmamalı.');
            self::assertCount(2, $menu->categories(), 'CATEGORY-REORDER-MEMBERSHIP-GUARD-01: reddedilen reorder menüye yeni bir kategori eklememeli.');
        }
    }

    // --- Reviewer P2 correction: MENU-ALLERGEN-ATTACH-SEMANTIC-DEDUPLICATION-01 ---

    public function test_attaching_two_distinct_term_instances_with_the_same_semantic_identity_does_not_duplicate(): void
    {
        $product = Product::create('Mercimek Çorbası');
        $glutenTermOne = TaxonomyTerm::create('gluten', type: 'allergen');
        $glutenTermTwo = TaxonomyTerm::create('gluten', type: 'allergen');

        $product->attachAllergen($glutenTermOne);
        $product->attachAllergen($glutenTermTwo);

        self::assertCount(1, $product->allergens(), 'ALLERGEN-ATTACH-SEMANTIC-DEDUPLICATION-01: name+type semantik kimliği aynı olan iki farklı TaxonomyTerm instance\'ı tekrarlanmamalı.');
    }
}
