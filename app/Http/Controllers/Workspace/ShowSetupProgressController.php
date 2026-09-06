<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Workspace\Port\SetupProgressPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kurulum ilerlemesi — panonun kurulum şeridi buradan okur (`docs/107` 1.7).
 *
 * Tek kapı: çalışma alanını GÖREBİLEN herkes okur. Kurulumun neresinde
 * olunduğu gizli bir bilgi değildir; menüyü düzenleyen bir editör de "yayın
 * henüz yapılmadı"yı görmelidir. Görme hakkı olmayan için kayıt HİÇ YOKTUR
 * (404) — 403 "böyle bir yer var" der ve o da bir bilgidir.
 */
final class ShowSetupProgressController extends Controller
{
    public function __construct(
        private readonly SetupProgressPort $progress,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($this->progress->forWorkspace($workspace)->toArray());
    }
}
