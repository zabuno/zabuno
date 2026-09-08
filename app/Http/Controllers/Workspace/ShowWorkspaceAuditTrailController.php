<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Support\Port\SupportAccessPort;
use App\Application\Workspace\Port\WorkspaceAuditTrailPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Denetim izi — Ayarlar'ın dördüncü sekmesi (FF-132).
 *
 * KİRACININ BUNU GÖRDÜĞÜ YER BURASIDIR (`docs/122` Y7, `docs/133` §3).
 *
 * Platform ekibinin hesaba bakışı iki kez görünür ve ikisi de kasıtlıdır:
 * zaman çizgisinde bir satır olarak (`data`, kaynak `support-access`) ve
 * listenin ÜSTÜNDE ayrı bir alan olarak (`supportAccess`). İkinci alan,
 * kaydın yüz satırlık bir izin ortasına gömülmemesi için var: `docs/122` §5
 * kaydın kiracının GÖREBİLECEĞİ biçimde yazılmasını istiyor ve
 * "teknik olarak listede" ile "sahibin gözüne çarptı" aynı cümle değil.
 *
 * YENİ UÇ AÇILMADI. Sahibin bu kaydı okumak için öğrenmesi gereken yeni bir
 * yer yok; zaten "bunu kim, ne zaman yaptı?" diye baktığı ekran bunu da
 * söylüyor.
 *
 * İki basamaklı kapı, medya izindeki kuralla AYNI ve sebebi de aynı:
 * çalışma alanını hiç göremeyen için kayıt HİÇ YOKTUR (404), çünkü 403
 * "böyle bir yer var ama sana kapalı" der ve bu da bir bilgidir. Üye olan
 * ama yönetme izni olmayan için 403 doğrudur: yerin varlığını zaten biliyor,
 * kapalı olan yalnız izin kendisi.
 */
final class ShowWorkspaceAuditTrailController extends Controller
{
    public function __construct(
        private readonly WorkspaceAuditTrailPort $trail,
        private readonly AuthorizationPort $authorization,
        private readonly SupportAccessPort $supportAccess,
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

        return response()->json([
            'data' => $this->trail->recent($workspace),
            'supportAccess' => array_map(
                static fn ($session): array => $session->toArray(),
                $this->supportAccess->forWorkspace($workspace, 20),
            ),
        ]);
    }
}
