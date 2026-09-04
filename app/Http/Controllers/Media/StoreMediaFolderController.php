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
 * Klasör açma — `docs/108` §3 madde 1.
 *
 * Üç şey burada reddedilir ve üçü de sessizce düzeltilmekten iyidir:
 * yabancı bir üst klasör, üçüncü seviye ve aynı seviyede tekrar eden ad.
 * Sessiz düzeltme (köke taşımak, ada "(2)" eklemek) sahibi klasörünü
 * koymadığı yerde aramaya bırakırdı.
 */
final class StoreMediaFolderController extends Controller
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

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:'.FolderNesting::MAX_NAME_LENGTH],
            'parentId' => ['nullable', 'integer'],
        ]);

        $name = trim((string) $validated['name']);
        $parentId = $validated['parentId'] === null ? null : (int) $validated['parentId'];

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Klasör adı boş olamaz.']);
        }

        if ($parentId !== null) {
            /*
                Üst klasör bu kiracıda aranır. Yabancı bir kimlik burada
                doğrulama hatası olur, 404 değil: sahip kendi deposunda
                var olmayan bir klasörü seçmiştir; başka bir deponun
                klasörünün var olup olmadığı ona söylenmez.
            */
            $parent = $this->folders->find($workspace, $parentId);

            if ($parent === null) {
                throw ValidationException::withMessages(['parentId' => 'Üst klasör bulunamadı.']);
            }

            // Kaynakta üçüncü seviye yok (`FolderNesting::MAX_DEPTH`).
            if ($parent->parentId !== null) {
                throw ValidationException::withMessages([
                    'parentId' => 'Klasörler en fazla '.FolderNesting::MAX_DEPTH.' seviye derinleşebilir.',
                ]);
            }
        }

        if ($this->folders->nameTaken($workspace, $name, $parentId)) {
            throw ValidationException::withMessages(['name' => 'Bu adda bir klasör zaten var.']);
        }

        $folder = $this->folders->create($workspace, $name, $parentId);

        return response()->json([
            'id' => $folder->id,
            'name' => $folder->name,
            'parentId' => $folder->parentId,
            'position' => $folder->position,
            'fileCount' => 0,
        ], 201);
    }
}
