<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaFolderRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Klasör kaldırma — `docs/108` §3 madde 1, §4 "asıl korunur".
 *
 * İki farklı "dolu" vardır ve ikisi aynı cevabı almaz:
 *
 * - **İçinde DOSYA varsa** silinir; dosyalar klasörsüz kalır ve "Tümü"de
 *   görünmeye devam eder. Sahip bir RAF kaldırmıştır, tabakları çöpe
 *   atmamıştır — ve bu iş geri alınabilir: dosyalar orada, yeniden
 *   klasörlenebilir.
 * - **İçinde ALT KLASÖR varsa** reddedilir (409). Alt klasörü de sessizce
 *   silmek, sahibin adını koyduğu bir düzeni tek tıkla yok etmek olurdu ve
 *   bunun geri dönüşü yok. Önce alt klasör kaldırılır, sonra üstü.
 *
 * Ara yol (dosyaları da çöpe atmak) kaynağın değişmez kuralına aykırı
 * olurdu: "hiçbir satır silinmez".
 */
final class DeleteMediaFolderController extends Controller
{
    public function __construct(
        private readonly MediaFolderRepositoryPort $folders,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $folder): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($this->folders->find($workspace, $folder) === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->folders->hasChildren($workspace, $folder)) {
            return response()->json([
                'message' => 'Bu klasörün altında başka klasörler var. Önce onları kaldırın.',
            ], 409);
        }

        $released = $this->folders->deleteAndReleaseFiles($workspace, $folder);

        // Sahibin ekranda okuyacağı cümle buradan doğar: "Klasör kaldırıldı,
        // N dosya Tümü'ne taşındı."
        return response()->json(['id' => $folder, 'releasedFileCount' => $released]);
    }
}
