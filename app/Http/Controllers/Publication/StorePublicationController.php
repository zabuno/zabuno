<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Application\Publication\Exception\PublicationPersistenceFailedException;
use App\Application\Publication\Exception\UnreadyDraftException;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\Publication\UseCase\AssembleDraftSnapshot;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StorePublicationController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly PublicationRepositoryPort $publications,
        private readonly MenuMediaPort $menuMedia,
        private readonly AssembleDraftSnapshot $assembler,
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

        try {
            /*
                Montaj TEK YERDE (`AssembleDraftSnapshot`): aynı işi artık
                planlama ve önizleme de istiyor. Üç kopya olsaydı biri bir
                gün görselleri unuturdu ve sahip, önizlemede gördüğü
                fotoğrafın yayında olmadığını ancak misafir sorduğunda
                anlardı.

                Kimlik, görseller ve logo yayın ANINDA çözülür ve snapshot'a
                donar (`docs/75`, `docs/76`, `docs/77`).
            */
            $assembled = $this->assembler->forMenu($workspace, $menu);
        } catch (UnreadyDraftException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($assembled === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $snapshot = $assembled['snapshot'];
        $visibleItemIds = $assembled['visibleItemIds'];
        $brandId = $assembled['brandId'];

        try {
            $record = $this->publications->publish($workspace, $menu, $assembled['locationId'], $snapshot, $userId);
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
