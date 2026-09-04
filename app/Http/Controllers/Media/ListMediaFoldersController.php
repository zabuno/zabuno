<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Dto\MediaFolderSummary;
use App\Application\Media\Port\MediaFolderRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kenar çubuğundaki klasör listesi — `docs/108` §3 madde 1.
 *
 * Kapı `ListMediaController` ile AYNIDIR: klasörde gezinmek kütüphaneye
 * bakmaktır, medyayı yönetmek değil. Salt okunur bir üye de "Kampanyalar"
 * klasörüne girip afişi bulabilmeli; yalnız o klasörü yeniden
 * adlandıramaz.
 */
final class ListMediaFoldersController extends Controller
{
    public function __construct(
        private readonly MediaFolderRepositoryPort $folders,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'data' => array_map(static fn (MediaFolderSummary $folder): array => [
                'id' => $folder->id,
                'name' => $folder->name,
                'parentId' => $folder->parentId,
                'position' => $folder->position,
                // Kaynağın kenar çubuğundaki "Ürünler 4" sayısı.
                'fileCount' => $folder->fileCount,
            ], $this->folders->listForWorkspace($workspace)),
        ]);
    }
}
