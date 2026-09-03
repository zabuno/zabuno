<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * İnceleme ekranının verisi — `docs/92`.
 *
 * "Bu nereden geldi" sorusunun cevabı taşınır: model kimliği, prompt sürümü
 * ve ALAN BAŞINA güven. Belirsiz alanlar işaretlidir; inceleyen kişi nereye
 * bakacağını bilmeli, altmış satırı tek tek okumak zorunda kalmamalı.
 */
final class ShowMenuAiImportController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

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

        return response()->json([
            'id' => (int) $row->id,
            'capability' => (string) $row->capability,
            'modelIdentity' => (string) $row->model_identity,
            'promptVersion' => (string) $row->prompt_version,
            'schemaVersion' => (string) $row->schema_version,
            'uncertainFieldCount' => (int) $row->uncertain_field_count,
            'usedFallback' => (bool) $row->used_fallback,
            'appliedAt' => $row->applied_at,
            'fields' => (array) json_decode((string) $row->fields, true),
        ]);
    }
}
