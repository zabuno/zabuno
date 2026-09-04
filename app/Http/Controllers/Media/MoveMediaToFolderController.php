<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaFolderRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Varlığı klasöre taşıma — `docs/108` §3 madde 1.
 *
 * `folderId: null` bir hata değil, bir yetenektir: yanlış klasöre atılan
 * dosya orada kilitli kalmamalı, "Tümü"ye geri çıkabilmeli.
 *
 * Taşımak dosyanın kendisine DOKUNMAZ: depolama anahtarı, türevler ve
 * sürümler yerinde kalır. Değişen tek şey sahibin onu nerede aradığıdır.
 * Bu yüzden burada yeni bir sürüm de açılmaz.
 */
final class MoveMediaToFolderController extends Controller
{
    public function __construct(
        private readonly MediaFolderRepositoryPort $folders,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $media): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate(['folderId' => ['nullable', 'integer']]);
        $folderId = $validated['folderId'] === null ? null : (int) $validated['folderId'];

        /*
            Hedef klasör bu kiracıda YOKSA 404. Başka bir restoranın klasör
            kimliğini deneyen bir çağrı, "yok" cevabından fazlasını
            öğrenmemeli — 422 ile "geçersiz klasör" demek bile, kimliğin
            başka yerde var olup olmadığını sızdırma yolunu açardı.
        */
        if ($folderId !== null && $this->folders->find($workspace, $folderId) === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // Varlık da aynı kiracının olmalı; olmadığında hiçbir satır
        // değişmez ve cevap yine 404'tür.
        if (! $this->folders->moveAsset($workspace, $media, $folderId)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json(['id' => $media, 'folderId' => $folderId]);
    }
}
