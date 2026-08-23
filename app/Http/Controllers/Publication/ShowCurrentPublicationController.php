<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowCurrentPublicationController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly PublicationRepositoryPort $publications,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $tree = $this->menuCatalog->getDraftTree($workspace, $menu);

        if ($tree === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $record = $this->publications->current($workspace, $menu);

        if ($record === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'id' => $record->id,
            'workspaceId' => $record->workspaceId,
            'menuId' => $record->menuId,
            'locationId' => $record->locationId,
            'version' => $record->version,
            'state' => $record->state,
            'publishedAt' => $record->publishedAt,
            'snapshot' => $record->snapshot,
        ], 200);
    }
}
