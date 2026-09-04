<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Media\Port\MediaRepositoryPort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthenticatedUserController extends Controller
{
    public function __construct(private readonly MediaRepositoryPort $media) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
            Profil fotoğrafının önizleme adresi medya deposundan gelir; burada
            ikinci bir türetme kuralı yazılmaz. Aksi hâlde bir gün türev adresi
            değiştiğinde avatar sessizce kırık kalırdı.
        */
        $avatarAsset = $user->avatar_media_asset_id === null
            ? null
            : $this->media->find((int) $user->avatar_media_asset_id);

        return response()->json([
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            /*
                Profil fotoğrafı: varlık kimliği ve DEĞİŞMEZ küçük adres.
                Ekran ikisini de alır — kimlik "hangi görsel" sorusunu,
                adres "ne çizilecek" sorusunu cevaplar.
            */
            'avatarMediaAssetId' => $user->avatar_media_asset_id === null
                ? null
                : (int) $user->avatar_media_asset_id,
            'avatarUrl' => $avatarAsset?->previewUrl,
        ]);
    }
}
