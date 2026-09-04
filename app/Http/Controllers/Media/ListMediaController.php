<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Dto\MediaAssetSummary;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListMediaController extends Controller
{
    public function __construct(
        private readonly MediaRepositoryPort $media,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // `?trashed=1` çöpü listeler (`docs/49` Faz 5): geri alınabilir,
        // süresi dolunca kalıcı silinir.
        $assets = $request->boolean('trashed')
            ? $this->media->listTrashed($workspace)
            : $this->assetsInScope($request, $workspace);

        return response()->json([
            'data' => array_map(static fn ($asset) => [
                'id' => $asset->id,
                'workspaceId' => $asset->workspaceId,
                'status' => $asset->status,
                'altText' => $asset->altText,
                'slot' => $asset->slot,
                // Neden beklediğini ya da neden başarısız olduğunu sahip
                // ekranda okur; boşsa alan `null` kalır (`docs/76`).
                'statusReason' => $asset->statusReason,
                // "Bu fotoğraf kütüphanende zaten var" (`docs/49` Faz 3).
                'duplicateOfId' => $asset->duplicateOfId,
                // Kütüphane ekranı (`docs/49` Faz 4/5): küçük resim, kullanım
                // sayısı, sürüm sayısı, dosya bilgisi, yaşam döngüsü.
                'previewUrl' => $asset->previewUrl,
                'usageCount' => $asset->usageCount,
                'versionCount' => $asset->versionCount,
                'originalName' => $asset->originalName,
                'sizeBytes' => $asset->sizeBytes,
                'createdAt' => $asset->createdAt,
                'lifecycle' => $asset->lifecycle,
                // Klasör (`docs/108` §3 madde 1); klasörsüzde null.
                'folderId' => $asset->folderId,
            ], $assets),
        ]);
    }

    /**
     * Klasör süzgeci (`docs/108` §3 madde 1).
     *
     * Süzgeç SORULMADIKÇA hiçbir şey gizlenmez: `folder` parametresi yoksa
     * liste bugünkü davranışının aynısıdır — klasörlü ve klasörsüz tüm
     * varlıklar, kaynağın "Tümü" görünümü. Sessizce klasöre göre süzmek,
     * göç ertesinde sahibin elli fotoğrafının "kaybolması" demek olurdu.
     *
     * `?folder=none` ayrı bir sorudur: "hiç klasörlemediklerim hangileri?"
     * Sahip dağınık kalanı ancak bu görünümle toplayabilir.
     *
     * @return list<MediaAssetSummary>
     */
    private function assetsInScope(Request $request, int $workspace): array
    {
        if (! $request->has('folder')) {
            return $this->media->listForWorkspace($workspace);
        }

        $folder = trim((string) $request->query('folder'));

        // Boş `?folder=` bir süzgeç değil, bir kazadır (temizlenmiş bir
        // form alanı): "Tümü" davranışına düşer.
        if ($folder === '') {
            return $this->media->listForWorkspace($workspace);
        }

        if ($folder === 'none') {
            return $this->media->listInFolder($workspace, null);
        }

        return $this->media->listInFolder($workspace, (int) $folder);
    }
}
