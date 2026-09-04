<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaFolderRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\Media\FolderNesting;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Klasör adını düzeltme — `docs/108` §3 madde 1.
 *
 * Yalnız AD değişir. Üst klasör burada değiştirilemez: bir klasörü başka
 * bir klasörün altına taşımak, altındaki her dosyanın adresini de
 * değiştiren ayrı bir karardır ve kaynakta böyle bir eylem yok. Ad
 * düzeltmesi geri alınabilir bir iş, ağaç taşıma değil.
 */
final class RenameMediaFolderController extends Controller
{
    public function __construct(
        private readonly MediaFolderRepositoryPort $folders,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $folder): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:'.FolderNesting::MAX_NAME_LENGTH],
        ]);

        $name = trim((string) $validated['name']);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Klasör adı boş olamaz.']);
        }

        /*
            Klasör bu kiracıda aranır. Başka bir deponun klasör kimliği
            burada 404'tür, 403 değil — 403 "böyle bir klasör var ama sana
            kapalı" der ve bu da bir bilgidir.
        */
        $existing = $this->folders->find($workspace, $folder);

        if ($existing === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->folders->nameTaken($workspace, $name, $existing->parentId, $folder)) {
            throw ValidationException::withMessages(['name' => 'Bu adda bir klasör zaten var.']);
        }

        $this->folders->rename($workspace, $folder, $name);

        return response()->json([
            'id' => $existing->id,
            'name' => $name,
            'parentId' => $existing->parentId,
        ]);
    }
}
