<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Partinin kalıcı hafızası: durum, sayfa ilerlemesi, toplayıcı özeti. */
final class ShowMenuAiBatchController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

    public function __invoke(Request $request, int $workspace, int $batch): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $row = DB::table('ai_batches')->where('id', $batch)->where('workspace_id', $workspace)->first();

        if ($row === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $pages = DB::table('ai_batch_pages')->where('ai_batch_id', $batch)->orderBy('position')->get();

        return response()->json([
            'id' => (int) $row->id,
            'state' => (string) $row->state,
            'totalPages' => (int) $row->total_pages,
            'donePages' => (int) $pages->where('state', 'done')->count(),
            'failedPages' => (int) $pages->where('state', 'failed')->count(),
            'pages' => $pages->map(static fn (object $page): array => [
                'mediaAssetId' => (int) $page->media_asset_id,
                'position' => (int) $page->position,
                'state' => (string) $page->state,
                'artifactId' => $page->ai_artifact_id === null ? null : (int) $page->ai_artifact_id,
                'reason' => $page->failure_reason,
            ])->values()->all(),
            'summary' => $row->collector_summary === null ? null : json_decode((string) $row->collector_summary, true),
        ]);
    }
}
