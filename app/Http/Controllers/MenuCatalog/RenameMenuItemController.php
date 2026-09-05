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
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCatalog\RenameMenuItemRequest;
use Illuminate\Http\JsonResponse;

/**
 * Ürün adını düzeltir — `docs/73` (P0-01).
 *
 * Ad `products` tablosunda durur, menü satırında değil: aynı ürün iki
 * kategoride görünüyorsa yazım hatası ikisinde birden düzelir.
 */
final class RenameMenuItemController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MenuAuditPort $audit,
    ) {}

    public function __invoke(RenameMenuItemRequest $request, int $workspace, int $menuItem): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        /*
            İKİ AŞAMALI KAPI — depodaki diğer kontrolcülerle aynı dil.

            Görme izni yoksa 404: çalışma alanının VARLIĞI bile sızmamalı.
            Görme var ama yönetme yoksa 403: kaynak var, yetki yok — ve
            kullanıcının çıkış yolu farklıdır (erişim istemek).
        */
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

        $productName = (string) $request->validated('productName');

        try {
            $summary = $this->menuCatalog->renameMenuItemProduct(
                $workspace,
                $menuItem,
                $productName,
                $request->validated('description'),
                $request->has('description'),
            );
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            DENETİM İZİ (FF-154). Ad, misafirin menüde OKUDUĞU metindir;
            "Adana Kebap" bir gün "Adana Kebap (acı)" olduğunda bunu kimin
            yazdığı sorulabilir bir sorudur.

            Kaydın ETİKETİ YENİ ad: iz bir gün listelendiğinde satır bugünkü
            menüyle eşleşmeli. Eski ad kaybolmaz, "öncesi" sütununda durur.

            Açıklama değişikliği ize YAZILMAZ ve aynı çağrıdan geçse bile
            burada bir olay saymayız: açıklama pazarlama metnidir, sahibin
            "kim değiştirdi" diye sorduğu şey değil.
        */
        if ($menuItemContext->productName !== $productName) {
            $this->audit->record(MenuAuditEntry::forItem(
                $workspace,
                $menuItemContext->menuId,
                $menuItem,
                $productName,
                MenuAuditAction::ItemRenamed,
                $menuItemContext->productName,
                $productName,
                $userId,
            ));
        }

        return response()->json([
            'id' => $summary->id,
            'categoryId' => $summary->categoryId,
            'productId' => $summary->productId,
            'isVisible' => $summary->isVisible,
            'position' => $summary->position,
        ]);
    }
}
