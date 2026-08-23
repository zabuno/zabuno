<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Application\Publication\Exception\PublicationPersistenceFailedException;
use App\Application\Publication\Exception\UnreadyDraftException;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\Publication\UseCase\BuildPublicationSnapshot;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StorePublicationController extends Controller
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

        if (! $this->authorization->can($userId, Permission::MenuPublish, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tree = $this->menuCatalog->getDraftTree($workspace, $menu);

        if ($tree === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        try {
            $snapshot = BuildPublicationSnapshot::fromDraftTree($tree);
        } catch (UnreadyDraftException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            $record = $this->publications->publish($workspace, $menu, $tree->locationId, $snapshot, $userId);
        } catch (PublicationPersistenceFailedException) {
            return response()->json(['message' => 'Publication failed.'], 500);
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
        ], 201);
    }
}
