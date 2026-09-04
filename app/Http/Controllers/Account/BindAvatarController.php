<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Application\Media\Port\MediaRepositoryPort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Profil fotoğrafını bağla/kaldır — sahibin isteği (2026-09-04).
 *
 * Avatar AYRI bir dosya yolu değil, bir MEDYA VARLIĞIDIR: tarama, türev
 * üretimi, kota ve silme etkisi zaten o boru hattında (`docs/49`). Burada
 * yapılan tek şey, kullanıcının o varlığa işaret etmesidir.
 *
 * Varlık, kullanıcının ÜYE OLDUĞU bir çalışma alanına ait olmalıdır:
 * başka bir kiracının görselini profil fotoğrafı yapmak, o kiracının
 * dosyasını dışarı sızdırırdı.
 */
final class BindAvatarController extends Controller
{
    public function __construct(private readonly MediaRepositoryPort $media) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'mediaAssetId' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        $assetId = $validated['mediaAssetId'] === null ? null : (int) $validated['mediaAssetId'];

        if ($assetId !== null) {
            $asset = $this->media->find($assetId);

            $memberOfAssetWorkspace = $asset !== null && DB::table('workspace_memberships')
                ->where('workspace_id', $asset->workspaceId)
                ->where('user_id', (int) $user->getKey())
                ->exists();

            if (! $memberOfAssetWorkspace) {
                return response()->json(['message' => 'Not Found.'], 404);
            }
        }

        $user->forceFill(['avatar_media_asset_id' => $assetId])->save();

        return response()->json(['avatarMediaAssetId' => $assetId]);
    }
}
