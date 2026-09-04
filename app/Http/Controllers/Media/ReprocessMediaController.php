<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaAuditPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Application\Media\UseCase\ReprocessMediaAsset;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Bu görseli yeniden üret" — `docs/49` Faz 3 madde 5.
 *
 * Yayına dokunmaz: yeni sürüm taslak bağların gördüğü sürümdür; canlı menü
 * kendi snapshot'ındaki sürümü göstermeye devam eder (`docs/77`).
 */
final class ReprocessMediaController extends Controller
{
    public function __construct(
        private readonly MediaRepositoryPort $media,
        private readonly ReprocessMediaAsset $reprocess,
        private readonly AuthorizationPort $authorization,
        private readonly MediaAuditPort $audit,
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

        $asset = $this->media->find($media);

        if ($asset === null || $asset->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $outcome = ($this->reprocess)($workspace, $media);

        if ($outcome === 'not-ready') {
            return response()->json(['message' => 'Yalnız hazır bir görsel yeniden üretilebilir.'], 409);
        }

        // Denetim izi (`docs/49` Faz 7 madde 4): eylem BAŞARIYLA bittikten
        // sonra yazılır — denenip olmamış bir şeyi kaydetmek yanlış olurdu.
        $this->audit->record($workspace, $media, 'reprocessed', $userId);

        return response()->json([
            'outcome' => $outcome,
            'versions' => $this->media->versionsFor($workspace, $media),
        ], $outcome === 'reprocessed' ? 200 : 502);
    }
}
