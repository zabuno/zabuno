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
 * TOPLU onay — `docs/96` Faz 3.
 *
 * Bu bir "hepsini onayla" DEĞİLDİR (`docs/16` AI-13'ün yasakladığı şey o).
 * Fark şu: kullanıcı satırları zaten TEK bir inceleme listesinde gördü;
 * burada onayladığı şey o listenin tamamıdır — dört fotoğrafın dört ayrı
 * onay düğmesi olması, aynı kararı dört kez sormak olurdu ve otomasyon
 * yanlılığını azaltmaz, yalnız yorar.
 *
 * Her artifact'ın yetkisi ve sahipliği AYRI AYRI doğrulanır: bir listeye
 * başka bir tenant'ın artifact kimliğini iliştirmek işe yaramaz.
 *
 * Rapor BİRLEŞTİRİLİR ama reddedilen satırlar hangi taslaktan geldiğini
 * taşır — "3. satır okunamadı" hangi fotoğraf olduğu belli değilse
 * kullanıcı onu elle tamamlayamaz.
 */
final class ApplyBulkMenuAiImportController extends Controller
{
    private const MAX_ARTIFACTS = 10;

    public function __construct(
        private readonly ApplyMenuArtifact $apply,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'artifactIds' => ['required', 'array', 'min:1', 'max:'.self::MAX_ARTIFACTS],
            'artifactIds.*' => ['integer', 'min:1'],
        ]);

        $menuId = (int) DB::table('menus')->where('workspace_id', $workspace)->value('id');

        if ($menuId === 0) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $importedItems = 0;
        $importedCategories = 0;
        $rejectedRows = [];

        foreach (array_values(array_unique($validated['artifactIds'])) as $artifactId) {
            $row = DB::table('ai_artifacts')
                ->where('id', (int) $artifactId)
                ->where('workspace_id', $workspace)
                ->first();

            if ($row === null) {
                $rejectedRows[] = [
                    'artifactId' => (int) $artifactId,
                    'row' => 'artifact',
                    'reason' => 'Bu taslak bulunamadı.',
                ];

                continue;
            }

            $result = $this->apply->handle($workspace, $menuId, (int) $row->id);

            $importedItems += (int) ($result['importedItems'] ?? 0);
            $importedCategories += (int) ($result['importedCategories'] ?? 0);

            foreach ((array) ($result['rejectedRows'] ?? []) as $rejected) {
                $rejectedRows[] = [
                    'artifactId' => (int) $row->id,
                    'row' => (string) ($rejected['row'] ?? ''),
                    'reason' => (string) ($rejected['reason'] ?? ''),
                ];
            }
        }

        return response()->json([
            'importedItems' => $importedItems,
            'importedCategories' => $importedCategories,
            'rejectedRows' => $rejectedRows,
        ]);
    }
}
