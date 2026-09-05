<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Application\MenuCatalog\UseCase\ResolveServingMenu;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MenuCatalog\Support\MenuTreePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Şubenin ŞU AN servis edilen menüsü.
 *
 * Çoklu menüden önce bu yol "şubenin tek menüsü" demekti. Sahibin
 * 2026-09-05 kararından sonra (`docs/109` §7.1) şubede birden çok menü
 * olabiliyor; bu yol da misafirin o an göreceği menüyü döndürür. Tek
 * menülü bir şubede sonuç kelimesi kelimesine aynıdır — geriye uyum burada
 * korunur.
 *
 * Belirli bir menüyü açmak için ayrı yol vardır: `.../menu/{menu}`. Panel,
 * ekranı ilk açtığında buraya sorar (sahip de misafirle aynı şeyi görsün
 * diye), hapa bastığında ötekine.
 */
final class ShowMenuController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MenuTreePayload $payload,
        private readonly ResolveServingMenu $servingMenu,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $menuId = $this->servingMenu->forLocation($location)
            ?? $this->context->menuIdForLocation($workspace, $location);

        if ($menuId === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $tree = $this->menuCatalog->getDraftTree($workspace, $menuId);

        if ($tree === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($this->payload->build($workspace, $tree));
    }
}
