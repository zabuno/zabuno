<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Ai\UseCase\ApplyProductDescriptionDraft;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * İnsan onayı — `docs/96` (Faz 2, `opt-23`). Yetki BURADA YENİDEN doğrulanır
 * — aynı disiplin: `ApplyMenuAiImportController`.
 */
final class ApplyProductDescriptionDraftController extends Controller
{
    public function __construct(
        private readonly ApplyProductDescriptionDraft $apply,
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
            ->where('capability', 'product.description')
            ->first();

        if ($row === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // İnceleyen kişi öneriyi düzenlemiş olabilir (`docs/97` R4) —
        // boş bırakılırsa (istekte hiç yoksa) taslağın kendi metni uygulanır.
        $editedDescription = $request->has('description')
            ? (string) $request->input('description')
            : null;

        $result = $this->apply->handle($workspace, (int) $row->id, $editedDescription);

        return response()->json($result);
    }
}
