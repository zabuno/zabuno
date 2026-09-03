<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Ai\Batch\StartMenuBatch;
use App\Application\Ai\UseCase\ExtractMenuFromImage;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 40 sayfalık menü (`docs/98` FF-75): eşzamanlı 10 sayfa sınırının
 * ötesi kuyruğa gider. Yanıt hemen döner (202); ekran partiyi
 * `GET ai-batches/{batch}` ile izler. Tenant'a ait olmayan fotoğraf
 * satır düzeyinde reddedilir — parti başlamadan.
 */
final class StoreMenuAiBatchController extends Controller
{
    public function __construct(
        private readonly ExtractMenuFromImage $extract,
        private readonly StartMenuBatch $start,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $availability = $this->extract->availability($workspace);

        if (! $availability->isAvailable()) {
            return response()->json([
                'message' => 'Fotoğraftan menü okuma şu anda kullanılamıyor.',
                'reason' => $availability->value,
            ], 503);
        }

        $maxPages = (int) config('ai.batch.max_pages', 40);
        $validated = $request->validate([
            'mediaAssetIds' => ['required', 'array', 'min:1', 'max:'.$maxPages],
            'mediaAssetIds.*' => ['integer', 'min:1'],
        ]);

        if (DB::table('menus')->where('id', $menu)->where('workspace_id', $workspace)->doesntExist()) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $requested = array_values(array_unique(array_map('intval', $validated['mediaAssetIds'])));
        $owned = DB::table('media_assets')
            ->whereIn('id', $requested)
            ->where('workspace_id', $workspace)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $rejected = array_values(array_diff($requested, $owned));
        $pages = array_values(array_filter($requested, static fn (int $id): bool => in_array($id, $owned, true)));

        if ($pages === []) {
            return response()->json(['message' => 'Hiçbir fotoğraf bu çalışma alanına ait değil.', 'rejected' => $rejected], 422);
        }

        $batchId = $this->start->handle($workspace, $menu, $userId, $pages);

        return response()->json(['id' => $batchId, 'totalPages' => count($pages), 'rejected' => $rejected], 202);
    }
}
