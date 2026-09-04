<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\QrDestination\Port\DiningAreaRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Şubenin salon bölümleri — FF-123.
 *
 * Liste QR kod listesinden TÜRETİLMEZ: masası olmayan bir alan (henüz kart
 * basılmamış yeni bir bahçe) o listede hiç görünmez ve sahibi onu yeniden
 * adlandıramazdı.
 */
final class ListDiningAreasController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly DiningAreaRepositoryPort $areas,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($this->areas->listForLocation($workspace, $location), 200);
    }
}
