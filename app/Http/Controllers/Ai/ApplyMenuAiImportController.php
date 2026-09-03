<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Ai\UseCase\ApplyMenuArtifact;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * İnsan onayı — `docs/92`.
 *
 * Onay TASLAĞA yazar; misafirin gördüğü menü sahip "Yayınla"ya basana kadar
 * değişmez. Yetki BURADA YENİDEN doğrulanır: artifact'ı üreten çağrının
 * yetkisi, onu uygulayan çağrının yetkisi değildir.
 */
final class ApplyMenuAiImportController extends Controller
{
    public function __construct(
        private readonly ApplyMenuArtifact $apply,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $artifact): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $row = DB::table('ai_artifacts')
            ->where('id', $artifact)
            ->where('workspace_id', $workspace)
            ->first();

        if ($row === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $menuId = (int) DB::table('menus')->where('workspace_id', $workspace)->value('id');

        if ($menuId === 0) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $result = $this->apply->handle($workspace, $menuId, (int) $row->id);

        return response()->json($result);
    }
}
