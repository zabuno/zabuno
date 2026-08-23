<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\ValueObject\Money;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCatalog\UpdateMenuItemPriceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class UpdateMenuItemPriceController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(UpdateMenuItemPriceRequest $request, int $workspace, int $menuItem): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $menuItemContext = $this->context->menuItemContext($menuItem);

        if ($menuItemContext === null || $menuItemContext->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $price = (string) $request->validated('price');
        $currency = (string) $request->validated('currency');

        try {
            $money = Money::fromDecimalPriceForBrand($price, $currency, $menuItemContext->brandCurrencyCode);
        } catch (InvalidArgumentException $e) {
            $field = $e->getCode() === Money::INVALID_FIELD_PRICE ? 'price' : 'currency';

            throw ValidationException::withMessages([$field => [$e->getMessage()]]);
        }

        try {
            $summary = $this->menuCatalog->updateMenuItemPrice($workspace, $menuItem, $money->minorAmount(), $money->currencyCode());
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'id' => $summary->id,
            'categoryId' => $summary->categoryId,
            'productId' => $summary->productId,
            'priceMinorAmount' => $summary->priceMinorAmount,
            'currencyCode' => $summary->currencyCode,
            'position' => $summary->position,
            'isVisible' => $summary->isVisible,
        ]);
    }
}
