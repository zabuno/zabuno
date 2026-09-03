<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Application\Tenancy\Profile\UseCase\GetBrand;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowBrandController extends Controller
{
    public function __construct(
        private readonly GetBrand $getBrand,
        private readonly AuthorizationPort $authorization,
        private readonly MenuMediaPort $menuMedia,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $brand = $this->getBrand->handle($workspace);

        if ($brand === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // Logo BAĞI marka tablosunda değil, `media_usages`'ta yaşar
        // (`docs/77`). Ekranın "şu an hangi logo seçili" sorusu için
        // buradan okunur; `null` = logo yok (`docs/98` FF-64).
        return response()->json($brand->toArray() + [
            'logoMediaAssetId' => $this->menuMedia->draftAssetId($workspace, 'brand', $brand->id),
        ]);
    }
}
