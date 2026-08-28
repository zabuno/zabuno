<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Port;

use App\Application\MenuCatalog\Dto\CategorySummary;
use App\Application\MenuCatalog\Dto\MenuDraftSummary;
use App\Application\MenuCatalog\Dto\MenuDraftTree;
use App\Application\MenuCatalog\Dto\MenuEntrySummary;
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
     * Ürünü YARATIR, menüye ekler ve alerjenlerini yazar — hepsi tek işlemde.
     *
     * Bunlar üç ayrı çağrı olarak da yapılabilirdi; nitekim arayüz uzun süre
     * öyle yaptı ve kullanıcıya üç ayrı form gösterdi. Sorun yalnız tıklama
     * sayısı değildi: ikinci çağrı başarısız olduğunda hiçbir menüde
     * görünmeyen ÖKSÜZ bir ürün geride kalıyordu. Kullanıcı bunu göremez,
     * dolayısıyla temizleyemez.
     *
     * Tek işlem, o sessiz artığı imkânsız kılar: ya satırın tamamı oluşur ya
     * da hiçbiri.
     *
     * @param  list<string>  $allergenNames
     *
     * @throws MenuCatalogTenantMismatchException
     */
    public function addMenuEntry(
        int $workspaceId,
        int $categoryId,
        string $productName,
        int $priceMinorAmount,
        string $currencyCode,
        array $allergenNames,
    ): MenuEntrySummary;

    /**
     * @throws MenuCatalogTenantMismatchException
     */
    public function updateMenuItemPrice(int $workspaceId, int $menuItemId, int $priceMinorAmount, string $currencyCode): MenuItemSummary;

    /**
     * @throws MenuCatalogTenantMismatchException
     */
    public function updateMenuItemVisibility(int $workspaceId, int $menuItemId, bool $isVisible): MenuItemSummary;

    /**
     * Menüyü İŞLETMEK için gereken dört iş — `docs/73` (P0-01).
     *
     * Ürün bunlar olmadan bir menüyü yayımlayabiliyor ama işletemiyordu:
     * yanlış yazılan bir ürünün tek çaresi gizleyip doğrusunu yeniden
     * eklemekti ve yanlış olan veritabanında kalıyordu.
     */
    public function deleteMenuItem(int $workspaceId, int $menuItemId): void;

    public function deleteCategory(int $workspaceId, int $categoryId): void;

    public function renameCategory(int $workspaceId, int $categoryId, string $name): CategorySummary;

    public function renameMenuItemProduct(int $workspaceId, int $menuItemId, string $productName): MenuItemSummary;

    /**
     * Sıralama TOPLU yapılır ve liste TAM olmalıdır.
     *
     * `unique(category_id, position)` yüzünden satırları tek tek güncellemek
     * yolun ortasında çakışır: ikinci ürünü birinci sıraya taşımak, birinci
     * ürün hâlâ oradayken imkânsızdır. Kısmî bir liste ise listelenmeyen
     * satırları öngörülemez bir yere bırakırdı.
     *
     * @param  list<int>  $menuItemIds
     */
    public function reorderMenuItems(int $workspaceId, int $categoryId, array $menuItemIds): void;

    /** @param  list<int>  $categoryIds */
    public function reorderCategories(int $workspaceId, int $menuId, array $categoryIds): void;

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
