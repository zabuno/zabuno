<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Entitlement\Exception\EntitlementDeniedException;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Application\Team\UseCase\ResendTeamInvitation;
use App\Domain\Authorization\Permission;
use App\Domain\Entitlement\Entitlement;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bekleyen bir daveti yeniden gönderir (`docs/110` P0-06, kabul ölçütü 3).
 *
 * Sınırlar kardeş uçlardan DEVRALINIR, burada yeniden icat edilmez: yetki
 * `Permission::WorkspaceManage` (davet oluşturan uçla aynı kural), plan
 * kapısı CORE-04 ile 402, reddediş enumeration-safe 404, hız sınırı
 * rotada `throttle:5,1`. Farklı bir kural koymak, aynı yeteneğin iki ayrı
 * kapısı olması demekti.
 */
final class ResendTeamInvitationController extends Controller
{
    public function __construct(
        private readonly ResendTeamInvitation $resendTeamInvitation,
        private readonly AuthorizationPort $authorization,
        private readonly RequireEntitlement $requireEntitlement,
    ) {}

    public function __invoke(Request $request, int $workspace, int $invitation): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        /*
            YETKİSİ OLMAYAN "YOK" CEVABI ALIR.

            403 deseydik, yabancı bir kullanıcı davet id'lerini tek tek
            deneyerek hangilerinin var olduğunu öğrenebilirdi. Kardeş uçlar
            (liste, iptal) da aynı sebeple 404 döner.
        */
        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // CORE-04: bu yetenek plana bağlıdır. Yeniden gönderme, davetin
        // kendisiyle aynı yetenektir — ayrı bir kapı açmak, plan dışı bir
        // çalışma alanının davet e-postası göndermesine yol açardı.
        try {
            $this->requireEntitlement->handle($workspace, Entitlement::TeamInvitations);
        } catch (EntitlementDeniedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'entitlement' => $e->entitlement->value,
            ], 402);
        }

        $summary = $this->resendTeamInvitation->handle($workspace, $invitation);

        /*
            BEKLEYEN OLMAYAN DAVET YENİDEN GÖNDERİLEMEZ.

            İptal edilmiş bir daveti yeniden göndermek, sahibin kapattığı
            kapıyı sessizce açmak; kabul edilmişi göndermek ise artık üye
            olan birine geçersiz bir bağlantı yollamak olurdu.
        */
        if ($summary === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            YANIT SÖZ VERMEZ, DURUM BİLDİRİR.

            `delivery` alanı "gönderildi/gönderilemedi/bilinmiyor" der ve
            hiçbir hâlde tahmini süre ya da "e-posta yolda" demez. Taşıyıcı
            mesajı devraldıysa gelen kutusuna düştüğünü buradan bilemeyiz.
        */
        return response()->json($summary->toArray(), 200);
    }
}
