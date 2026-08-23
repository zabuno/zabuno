<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Blind RED integration candidate for the Menu Catalog persistence P1 slice
 * — MenuItem price integrity (frozen decision per task instruction: a
 * MenuItem price is persisted as an exact integer minor amount + ISO-4217
 * currency code, round-tripping through the database without float drift,
 * and each currency's official minor-unit fraction digits — JPY=0, USD=2,
 * KWD=3 — must be derivable from the persisted currency code alone; a
 * single Product row may be referenced by two distinct MenuItem rows with
 * independent prices).
 *
 * None of the MenuCatalogRepositoryPort contract or the underlying
 * products/menu_items tables exist yet, so every test below is expected to
 * fail RED with a binding-resolution error or missing-table
 * QueryException, not a logic assertion failure. See
 * MenuDraftPersistenceTest for the full frozen contract this file
 * exercises.
 *
 * Requirement IDs: MENU-PERSIST-ITEM-PRICE-EXACT-01,
 * MENU-PERSIST-ITEM-PRICE-FRACTION-DIGITS-01,
 * MENU-PERSIST-ITEM-SHARED-PRODUCT-INDEPENDENT-PRICE-01,
 * MENU-PERSIST-ITEM-PRICE-NEGATIVE-REJECT-01,
 * MENU-PERSIST-ITEM-CURRENCY-REJECT-01.
 *
 * Reviewer correction handoff (same P1 package): two focused RED cases are
 * added below — addMenuItem must reject a negative minor amount and an
 * unknown/invalid ISO-4217 currency code with InvalidArgumentException,
 * writing no menu_items row, mirroring the domain-level Money guards
 * already covered by tests/Unit/MenuCatalog/MenuMoneyValueObjectTest.
 */
final class MenuItemPriceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceWithLocation(User $owner, string $slugSeed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Kadıköy Şubesi',
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId];
    }

    private function repository(): MenuCatalogRepositoryPort
    {
        return app(MenuCatalogRepositoryPort::class);
    }

    // --- MENU-PERSIST-ITEM-PRICE-EXACT-01 -----------------------------------

    public function test_a_menu_item_price_round_trips_as_an_exact_integer_minor_amount_and_currency(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-exact');
        $repository = $this->repository();

        $menu = $repository->createDraftMenu($workspaceId, $locationId, 'Ana Menü');
        $category = $repository->addCategory($workspaceId, $menu->id, 'Ana Yemekler');
        $product = $repository->createProduct($workspaceId, 'Adana Kebap');

        $item = $repository->addMenuItem($workspaceId, $category->id, $product->id, 18999, 'USD');

        self::assertSame(18999, $item->priceMinorAmount, 'ITEM-PRICE-EXACT-01: minor amount tam sayı olarak korunmalı.');
        self::assertSame('USD', $item->currencyCode);

        $persisted = DB::table('menu_items')->where('id', $item->id)->first();
        self::assertSame(18999, (int) $persisted->price_minor_amount, 'ITEM-PRICE-EXACT-01: veritabanına yazılan minor amount float sapması taşımamalı.');
        self::assertSame('USD', $persisted->currency_code);
    }

    // --- MENU-PERSIST-ITEM-PRICE-FRACTION-DIGITS-01 --------------------------

    #[DataProvider('currencyFractionDigitsProvider')]
    public function test_persisted_currency_code_carries_correct_official_fraction_digits(string $currencyCode, int $minorAmount, int $expectedFractionDigits): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-fraction-'.strtolower($currencyCode));
        $repository = $this->repository();

        $menu = $repository->createDraftMenu($workspaceId, $locationId, 'Ana Menü');
        $category = $repository->addCategory($workspaceId, $menu->id, 'Ana Yemekler');
        $product = $repository->createProduct($workspaceId, 'Adana Kebap');

        $item = $repository->addMenuItem($workspaceId, $category->id, $product->id, $minorAmount, $currencyCode);

        self::assertSame($currencyCode, $item->currencyCode);
        self::assertSame(
            $expectedFractionDigits,
            \Brick\Money\Currency::of($item->currencyCode)->getDefaultFractionDigits(),
            'ITEM-PRICE-FRACTION-DIGITS-01: persist edilen currency code\'dan resmi küsurat basamağı türetilebilmeli.'
        );
    }

    public static function currencyFractionDigitsProvider(): array
    {
        return [
            'JPY has zero fraction digits' => ['JPY', 500, 0],
            'USD has two fraction digits' => ['USD', 1999, 2],
            'KWD has three fraction digits' => ['KWD', 1500, 3],
        ];
    }

    // --- MENU-PERSIST-ITEM-SHARED-PRODUCT-INDEPENDENT-PRICE-01 ---------------

    public function test_one_persisted_product_shared_by_two_menu_items_keeps_independent_prices(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-shared-product');
        $repository = $this->repository();

        $menu = $repository->createDraftMenu($workspaceId, $locationId, 'Ana Menü');
        $lunchCategory = $repository->addCategory($workspaceId, $menu->id, 'Öğle Menüsü');
        $dinnerCategory = $repository->addCategory($workspaceId, $menu->id, 'Akşam Menüsü');
        $sharedProduct = $repository->createProduct($workspaceId, 'Mercimek Çorbası');

        $lunchItem = $repository->addMenuItem($workspaceId, $lunchCategory->id, $sharedProduct->id, 4500, 'TRY');
        $dinnerItem = $repository->addMenuItem($workspaceId, $dinnerCategory->id, $sharedProduct->id, 6000, 'TRY');

        self::assertSame($sharedProduct->id, $lunchItem->productId);
        self::assertSame($sharedProduct->id, $dinnerItem->productId);
        self::assertNotSame($lunchItem->id, $dinnerItem->id);
        self::assertSame(4500, $lunchItem->priceMinorAmount);
        self::assertSame(6000, $dinnerItem->priceMinorAmount);
        self::assertSame(1, DB::table('products')->where('id', $sharedProduct->id)->count(), 'ITEM-SHARED-PRODUCT-INDEPENDENT-PRICE-01: tek Product satırı iki MenuItem tarafından paylaşılmalı, çoğaltılmamalı.');
        self::assertSame(2, DB::table('menu_items')->where('product_id', $sharedProduct->id)->count());
    }

    // --- MENU-PERSIST-ITEM-PRICE-NEGATIVE-REJECT-01 --------------------------

    public function test_adding_a_menu_item_with_a_negative_minor_amount_is_rejected_and_writes_no_row(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-negative-reject');
        $repository = $this->repository();

        $menu = $repository->createDraftMenu($workspaceId, $locationId, 'Ana Menü');
        $category = $repository->addCategory($workspaceId, $menu->id, 'Ana Yemekler');
        $product = $repository->createProduct($workspaceId, 'Adana Kebap');

        $beforeCount = DB::table('menu_items')->count();

        try {
            $repository->addMenuItem($workspaceId, $category->id, $product->id, -100, 'TRY');
            self::fail('ITEM-PRICE-NEGATIVE-REJECT-01: negatif minor amount ile menu item eklemek reddedilmeli.');
        } catch (\InvalidArgumentException) {
            // expected
        }

        self::assertSame(
            $beforeCount,
            DB::table('menu_items')->count(),
            'ITEM-PRICE-NEGATIVE-REJECT-01: reddedilen negatif tutarlı ekleme menu_items tablosuna satır yazmamalı.'
        );
    }

    // --- MENU-PERSIST-ITEM-CURRENCY-REJECT-01 ---------------------------------

    public function test_adding_a_menu_item_with_an_unknown_currency_code_is_rejected_and_writes_no_row(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId] = $this->workspaceWithLocation($owner, 'zeytin-price-currency-reject');
        $repository = $this->repository();

        $menu = $repository->createDraftMenu($workspaceId, $locationId, 'Ana Menü');
        $category = $repository->addCategory($workspaceId, $menu->id, 'Ana Yemekler');
        $product = $repository->createProduct($workspaceId, 'Adana Kebap');

        $beforeCount = DB::table('menu_items')->count();

        try {
            $repository->addMenuItem($workspaceId, $category->id, $product->id, 1000, 'ZZZ');
            self::fail('ITEM-CURRENCY-REJECT-01: bilinmeyen/geçersiz ISO-4217 currency code ile menu item eklemek reddedilmeli.');
        } catch (\InvalidArgumentException) {
            // expected
        }

        self::assertSame(
            $beforeCount,
            DB::table('menu_items')->count(),
            'ITEM-CURRENCY-REJECT-01: reddedilen geçersiz currency\'li ekleme menu_items tablosuna satır yazmamalı.'
        );
    }
}
