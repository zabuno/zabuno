<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Application\Publication\Exception\PublicationPersistenceFailedException;
use App\Application\Publication\Exception\UnreadyDraftException;
use App\Application\Publication\Port\MenuIdentityPort;
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
        private readonly MenuIdentityPort $identities,
        private readonly MenuMediaPort $menuMedia,
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

        // Görseller de yayın ANINDA çözülür ve SÜRÜM kimliğiyle donar
        // (`docs/77`): sonradan üretilen bir sürüm eski yayını değiştirmez.
        $itemImages = [];
        $visibleItemIds = [];

        foreach ($tree->categories as $category) {
            foreach ($category['items'] as $item) {
                if (! $item['isVisible']) {
                    continue;
                }

                $visibleItemIds[] = (int) $item['id'];
                $image = $this->menuMedia->snapshotImage($workspace, 'menu_item', (int) $item['id']);

                if ($image !== null) {
                    $itemImages[(int) $item['id']] = $image;
                }
            }
        }

        $brandId = $this->identities->brandIdForMenu($workspace, $menu);
        $logo = $brandId === null ? null : $this->menuMedia->snapshotImage($workspace, 'brand', $brandId);

        try {
            // Kimlik yayın ANINDA okunur ve snapshot'a donar (`docs/75`).
            $snapshot = BuildPublicationSnapshot::fromDraftTree(
                $tree,
                $this->identities->forMenu($workspace, $menu),
                $itemImages,
                $logo,
            );
        } catch (UnreadyDraftException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            $record = $this->publications->publish($workspace, $menu, $tree->locationId, $snapshot, $userId);
        } catch (PublicationPersistenceFailedException) {
            return response()->json(['message' => 'Publication failed.'], 500);
        }

        // "Bu görsel yayında mı" sorusunun cevabı buradan doğar; onsuz
        // paneldeki bir silme misafirin gözü önünde menüyü bozar
        // (`docs/76` kriter 4).
        $this->menuMedia->recordPublicationUsages($workspace, $record->id, $visibleItemIds, $brandId);

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
