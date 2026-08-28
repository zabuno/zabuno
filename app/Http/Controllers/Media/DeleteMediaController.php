<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteMediaController extends Controller
{
    public function __construct(
        private readonly MediaRepositoryPort $media,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $media): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $asset = $this->media->find($media);

        if ($asset === null || $asset->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->media->isUsedByPublication($workspace, $media)) {
            // 409: istek geçerli, DURUM izin vermiyor. Yayın, sahibin
            // onayladığı donmuş hâldir; bir temizlik onu misafirin gözü
            // önünde bozamaz (`docs/76`, kriter 4).
            return response()->json([
                'message' => 'Bu görsel yayınlanmış bir menüde kullanılıyor. '
                    .'Önce menüden kaldırıp yeniden yayınlayın, sonra silin.',
            ], 409);
        }

        $this->media->delete($media);

        return response()->json(null, 204);
    }
}
