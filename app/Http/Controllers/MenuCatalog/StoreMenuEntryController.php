<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\MenuAuditAction;
use App\Domain\Money\Money;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCatalog\StoreMenuEntryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Menüye bir ürün eklemenin TEK uç noktası.
 *
 * Öncesinde arayüz üç ayrı isteği sırayla yapıyordu: ürün yarat, menü satırı
 * yarat, alerjenleri yaz. Kullanıcı bunun için üç ayrı form doldurup üç kez
 * kaydediyordu — ve ikinci istek düşerse hiçbir menüde görünmeyen öksüz bir
 * ürün geride kalıyordu.
 *
 * Var olan üç uç nokta KALDIRILMADI: menüde zaten olan bir ürünü başka bir
 * kategoriye eklemek gibi işler onları kullanır. Kaldırılan şey,
 * KULLANICIDAN onları sırayla çalıştırmasını istemekti.
 */
final class StoreMenuEntryController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MenuAuditPort $audit,
    ) {}

    public function __invoke(StoreMenuEntryRequest $request, int $workspace, int $category): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $categoryContext = $this->context->categoryContext($category);

        if ($categoryContext === null || $categoryContext->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $price = (string) $request->validated('price');
        $currency = (string) $request->validated('currency');

        try {
            $money = Money::fromDecimalPriceForBrand($price, $currency, $categoryContext->brandCurrencyCode);
        } catch (InvalidArgumentException $e) {
            $field = $e->getCode() === Money::INVALID_FIELD_PRICE ? 'price' : 'currency';

            throw ValidationException::withMessages([$field => [$e->getMessage()]]);
        }

        /** @var list<string> $allergens */
        $allergens = array_values(array_filter(
            array_map(
                static fn (mixed $name): string => trim((string) $name),
                (array) ($request->validated('allergens') ?? []),
            ),
            static fn (string $name): bool => $name !== '',
        ));

        try {
            $entry = $this->menuCatalog->addMenuEntry(
                $workspace,
                $category,
                trim((string) $request->validated('productName')),
                $money->minorAmount(),
                $money->currencyCode(),
                $allergens,
            );
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            DENETİM İZİ (FF-154). "Menüye bu ürünü kim ekledi?" — özellikle
            dört rollü bir ekipte sorulur. Sonrası, satırın DOĞDUĞU fiyattır:
            ilk fiyat kaydedilmezse ilk zam da öncesiz kalırdı.
        */
        $this->audit->record(MenuAuditEntry::forItem(
            $workspace,
            $categoryContext->menuId,
            $entry->menuItemId,
            $entry->productName,
            MenuAuditAction::ItemAdded,
            null,
            MenuAuditEntry::price($entry->priceMinorAmount, $entry->currencyCode),
            $userId,
        ));

        return response()->json([
            'id' => $entry->menuItemId,
            'categoryId' => $entry->categoryId,
            'productId' => $entry->productId,
            'productName' => $entry->productName,
            'priceMinorAmount' => $entry->priceMinorAmount,
            'currencyCode' => $entry->currencyCode,
            'position' => $entry->position,
            'isVisible' => $entry->isVisible,
            'allergens' => $entry->allergens,
        ], 201);
    }
}
