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
 * Alt metni (görselin ADI) sonradan düzeltme — `docs/49` §5.2 "re-naming".
 *
 * Çoklu yüklemede alt metin dosya adından türetilir ("IMG_8734" → "IMG 8734");
 * sahip bunu sonra çekmecede "Adana kebap" yapar. Depolama anahtarı ve
 * rendition adresleri DEĞİŞMEZ — yalnız insanın okuduğu ad değişir.
 */
final class UpdateMediaAltTextController extends Controller
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

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate(['altText' => ['required', 'string', 'max:255']]);

        if (! $this->media->updateAltText($workspace, $media, trim((string) $validated['altText']))) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $asset = $this->media->find($media);

        return response()->json(['id' => $media, 'altText' => $asset?->altText ?? $validated['altText']]);
    }
}
