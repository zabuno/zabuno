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
use App\Http\Requests\MenuCatalog\StoreMenuItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class StoreMenuItemController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MenuAuditPort $audit,
    ) {}

    public function __invoke(StoreMenuItemRequest $request, int $workspace, int $category): JsonResponse
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

        $productId = (int) $request->validated('productId');

        if ($this->context->productWorkspaceId($productId) !== $workspace) {
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

        try {
            $menuItem = $this->menuCatalog->addMenuItem($workspace, $category, $productId, $money->minorAmount(), $money->currencyCode());
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            DENETİM İZİ (FF-154). Bu uç nokta VAR OLAN bir ürünü ikinci bir
            kategoriye ekler; sahip için yine "menüye bir satır eklendi"
            olayıdır ve tek adımlı yoldan (`menu-entries`) ayrı bir dil
            konuşmamalı. Ad, yeni satırın bağlamından okunur — ürün adı
            `products` tablosunda durur ve bu cevap onu taşımıyor.
        */
        $createdContext = $this->context->menuItemContext($menuItem->id);

        $this->audit->record(MenuAuditEntry::forItem(
            $workspace,
            $categoryContext->menuId,
            $menuItem->id,
            $createdContext?->productName,
            MenuAuditAction::ItemAdded,
            null,
            MenuAuditEntry::price($menuItem->priceMinorAmount, $menuItem->currencyCode),
            $userId,
        ));

        return response()->json([
            'id' => $menuItem->id,
            'categoryId' => $menuItem->categoryId,
            'productId' => $menuItem->productId,
            'priceMinorAmount' => $menuItem->priceMinorAmount,
            'currencyCode' => $menuItem->currencyCode,
            'position' => $menuItem->position,
            'isVisible' => $menuItem->isVisible,
        ], 201);
    }
}
