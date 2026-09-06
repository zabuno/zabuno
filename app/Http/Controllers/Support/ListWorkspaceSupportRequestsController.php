<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Support\Dto\SupportRequestSummary;
use App\Application\Support\UseCase\ListWorkspaceSupportRequests;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Support\Contact\ResponseCommitment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sahibin bu çalışma alanından açtığı talepler — FF-201 (`docs/125` §4).
 *
 * Yetki `workspace.manage`: destek bir yönetim kanalıdır (plan, fatura,
 * hesap) ve editörün göreceği bir liste sahibin fatura sorusunu da açığa
 * çıkarırdı. Yetkisiz üye 404 alır — var olmayan çalışma alanıyla aynı
 * cevap, sayım yapılamasın diye.
 *
 * Taahhüt cümlesi LİSTEYLE BİRLİKTE gelir: panel onu kendi kataloğundan
 * değil sunucudan okur, çünkü cümle tek kaynaktan çıkmak zorunda
 * (`docs/125` §3). Yapılandırılmamışsa `null` ve ekran hiçbir şey yazmaz.
 */
final class ListWorkspaceSupportRequestsController extends Controller
{
    public function __construct(
        private readonly ListWorkspaceSupportRequests $listRequests,
        private readonly AuthorizationPort $authorization,
        private readonly ResponseCommitment $commitment,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $hours = $this->commitment->hours();

        return response()->json([
            'requests' => array_map(
                static fn (SupportRequestSummary $summary): array => $summary->toArray(),
                $this->listRequests->handle($workspace),
            ),
            'commitment' => $hours === null ? null : [
                'hours' => $hours,
                'sentence' => $this->commitment->sentence(app()->getLocale()),
            ],
        ]);
    }
}
