<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Markanın logosunu bağlar — `docs/77` (P0-03 logo tamamlaması).
 *
 * Logo misafirin gördüğü ilk şeydir; menüde ada eşlik eder ve yayın
 * anında snapshot'a donar.
 */
final class BindBrandLogoController extends Controller
{
    public function __construct(
        private readonly MenuMediaPort $menuMedia,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $brandId = DB::table('brands')->where('workspace_id', $workspace)->value('id');

        if ($brandId === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = $request->validate([
            'mediaAssetId' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        $mediaAssetId = $validated['mediaAssetId'] === null ? null : (int) $validated['mediaAssetId'];

        if (! $this->menuMedia->bindBrandLogo($workspace, (int) $brandId, $mediaAssetId)) {
            return response()->json([
                'message' => 'Bu görsel henüz kullanıma hazır değil. İşlenmesi bitince yeniden deneyin.',
            ], 422);
        }

        return response()->json(['brandId' => (int) $brandId, 'mediaAssetId' => $mediaAssetId]);
    }
}
