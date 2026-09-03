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
 * Eski sürümü geri getir — YENİ sürüm olarak (`docs/49` Faz 3 madde 2).
 *
 * Geçmiş yeniden yazılmaz: bir yayın snapshot'ı hâlâ eski sürümü
 * gösteriyorsa o satır olduğu yerde durur. Geri alma, o sürümün
 * rendition'larını en üste kopyalar.
 */
final class RestoreMediaVersionController extends Controller
{
    public function __construct(
        private readonly MediaRepositoryPort $media,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $media, int $version): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $asset = $this->media->find($media);

        if ($asset === null || $asset->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $restored = $this->media->restoreVersion($workspace, $media, $version);

        if ($restored === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'restoredVersionId' => $restored,
            'versions' => $this->media->versionsFor($workspace, $media),
        ]);
    }
}
