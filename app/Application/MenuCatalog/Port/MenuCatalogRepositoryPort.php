<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Port;

use App\Application\MenuCatalog\Dto\CategorySummary;
use App\Application\MenuCatalog\Dto\MenuDraftSummary;
use App\Application\MenuCatalog\Dto\MenuDraftTree;
use App\Application\MenuCatalog\Dto\MenuItemSummary;
use App\Application\MenuCatalog\Dto\ProductSummary;
use App\Application\MenuCatalog\Dto\TaxonomyTermSummary;
use App\Application\MenuCatalog\Exception\DuplicateLocationMenuException;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use InvalidArgumentException;

interface MenuCatalogRepositoryPort
{
    /**
     * @throws DuplicateLocationMenuException
     */
    public function createDraftMenu(int $workspaceId, int $locationId, string $name): MenuDraftSummary;

    public function getDraftTree(int $workspaceId, int $menuId): ?MenuDraftTree;

    /**
     * @throws MenuCatalogTenantMismatchException
     */
    public function addCategory(int $workspaceId, int $menuId, string $name): CategorySummary;

    public function createProduct(int $workspaceId, string $name): ProductSummary;

    /**
     * @throws MenuCatalogTenantMismatchException
     */
    public function addMenuItem(int $workspaceId, int $categoryId, int $productId, int $priceMinorAmount, string $currencyCode): MenuItemSummary;

    /**
     * @throws MenuCatalogTenantMismatchException
     */
    public function updateMenuItemPrice(int $workspaceId, int $menuItemId, int $priceMinorAmount, string $currencyCode): MenuItemSummary;

    /**
     * @throws MenuCatalogTenantMismatchException
     */
    public function updateMenuItemVisibility(int $workspaceId, int $menuItemId, bool $isVisible): MenuItemSummary;

    public function createTaxonomyTerm(string $name, string $type): TaxonomyTermSummary;

    /**
     * @throws MenuCatalogTenantMismatchException
     * @throws InvalidArgumentException
     */
    public function attachAllergenToProduct(int $workspaceId, int $productId, int $taxonomyTermId): void;

    /**
     * Replaces the complete allergen set of a product atomically: resolves
     * or creates each named allergen taxonomy term, then syncs the pivot to
     * exactly that set (an empty list detaches all existing allergens).
     *
     * @param  list<string>  $allergenNames
     * @return list<string> resulting allergen names, in the persisted order
     *
     * @throws MenuCatalogTenantMismatchException
     */
    public function replaceProductAllergens(int $workspaceId, int $productId, array $allergenNames): array;
}
