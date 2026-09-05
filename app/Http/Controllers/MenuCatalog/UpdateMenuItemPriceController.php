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
        private readonly MenuAuditPort $audit,
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

        /*
            DENETİM İZİ (FF-154) — bu kontrolcü paketin var oluş sebebidir:
            *"dün kebabın fiyatını kim değiştirdi?"*

            Kayıt eylem BAŞARIYLA bittikten sonra yazılır (medya izindeki
            aynı gerekçe: denenip olmamış bir şeyi kaydetmek yanlış olurdu)
            ve öncesi, yazmadan ÖNCE okunmuş bağlamdan gelir — sonradan
            sorulabilecek bir yer kalmıyor.

            DEĞİŞMEYEN FİYAT YAZILMAZ: arayüz aynı formu iki kez
            kaydedebilir ve "380 → 380" satırları izi kendi kendine
            gürültüye çevirirdi.
        */
        $before = MenuAuditEntry::price($menuItemContext->priceMinorAmount, $menuItemContext->currencyCode);
        $after = MenuAuditEntry::price($summary->priceMinorAmount, $summary->currencyCode);

        if ($before !== $after) {
            $this->audit->record(MenuAuditEntry::forItem(
                $workspace,
                $menuItemContext->menuId,
                $menuItem,
                $menuItemContext->productName,
                MenuAuditAction::ItemPriceChanged,
                $before,
                $after,
                $userId,
            ));
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
