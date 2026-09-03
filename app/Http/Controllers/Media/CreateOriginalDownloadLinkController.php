<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Aslın indirme bağlantısı (`docs/49` Faz 6 madde 2, Faz 7 madde 3).
 *
 * Asıl dosya karantina diskindedir, herkese açık adresi yoktur. Buradan
 * 10 dakikalık İMZALI bir adres verilir; imza kiracıyı ve varlığı
 * taşır, süresi dolunca 403. Sahibin kararı: asıl indirme
 * "tamamen serbest" — her rol `media.download_original` iznine sahiptir;
 * izin yine de AYRI tutulur ki bir gün daraltmak bir satır olsun.
 */
final class CreateOriginalDownloadLinkController extends Controller
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

        if (! $this->authorization->can($userId, Permission::MediaDownloadOriginal, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $asset = $this->media->find($media);

        if ($asset === null || $asset->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $expiresAt = now()->addMinutes(10);

        return response()->json([
            'url' => URL::temporarySignedRoute('media.original', $expiresAt, ['workspace' => $workspace, 'asset' => $media]),
            'expiresAt' => $expiresAt->toIso8601String(),
        ]);
    }
}
