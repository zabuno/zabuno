<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Team\UseCase\RemoveTeamMember;
use App\Domain\Authorization\Permission;
use App\Domain\Tenancy\MembershipRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class RemoveTeamMemberController extends Controller
{
    public function __construct(
        private readonly RemoveTeamMember $removeTeamMember,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $member): JsonResponse|Response
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            EKİPTEN KİMİN ÇIKACAĞI SAHİBİN KARARIDIR.

            Yukarıdaki izin kapısı tek başına bunu söyleyemez: `WorkspaceManage`
            `Manager`'da DA var ve olmalı — Manager şube ve karekod yönetir. Bu
            yol yalnız `editor` satırlarını silerken fark edilmiyordu; kaldırma
            davet edilebilen bütün rolleri kapsar hâle gelince açık bir sahiplik
            kontrolü şart oldu. Aksi hâlde bir yönetici, sahibin haberi olmadan
            diğer yöneticileri — ve kendini — ekipten silebilirdi.

            Aynı sınır ve aynı gerekçe rol düzenlemede de var
            (`UpdateTeamMemberRoleController`): kimin ne yapabileceğine karar
            vermek, günlük operasyonu yürütmekten ayrı bir şeydir.

            403, 404 DEĞİL: buraya kadar gelen kişi çalışma alanını zaten
            YÖNETİYOR, varlığını gizlemenin anlamı yok ve çıkış yolu farklıdır
            (sahipten istemek). Bu izne hiç sahip olmayanlar için uç noktanın
            numaralandırmaya kapalı 404 cevabı yukarıda aynen duruyor.
        */
        $requesterRole = (string) DB::table('workspace_memberships')
            ->where('workspace_id', $workspace)
            ->where('user_id', $userId)
            ->value('role');

        if ($requesterRole !== MembershipRole::Owner->value) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $this->removeTeamMember->handle($workspace, $member)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->noContent();
    }
}
