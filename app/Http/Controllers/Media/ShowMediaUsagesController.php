<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Bu görsel nerede kullanılıyor?" — `docs/49` Faz 5 madde 1-2.
 *
 * Silmeden önce ETKİ ÖNİZLEMESİ bununla kurulur: sahip, "Adana Kebap'ın
 * fotoğrafı ve marka logosu" olduğunu görüp ona göre karar verir. Yayında
 * kullanılanlar ayrıca işaretlenir — onlar zaten silinemez.
 */
final class ShowMediaUsagesController extends Controller
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

        return response()->json(['usages' => $this->media->usagesFor($workspace, $media)]);
    }
}
