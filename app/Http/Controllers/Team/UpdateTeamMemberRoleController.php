<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Domain\Tenancy\MembershipRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Yanlış verilmiş bir rolü düzeltir — `docs/83` (P1-07).
 *
 * Önceden tek çare üyeyi SİLİP yeniden davet etmekti: kişi erişimini
 * kaybediyor, yeni bir davet e-postası bekliyor ve bu sırada iş duruyordu.
 */
final class UpdateTeamMemberRoleController extends Controller
{
    public function __construct(private readonly AuthorizationPort $authorization) {}

    public function __invoke(Request $request, int $workspace, int $member): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        /*
            İKİ AŞAMALI KAPI — depodaki diğer kontrolcülerle aynı dil.

            Görme izni yoksa 404: çalışma alanının VARLIĞI bile sızmamalı.
            Görüyor ama yetkisi yoksa 403: kaynak var, yetki yok — ve
            kullanıcının çıkış yolu farklıdır (sahipten istemek).
        */
        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            Rol dağıtmak SAHİBİN işidir.

            İzin listesiyle ayrılamaz: `WorkspaceManage` Manager'da da var ve
            olmalı — Manager şube ve karekod yönetir. Ama kimin ne
            yapabileceğine karar vermek ayrı bir şeydir; sahiplik devri de
            aynı sebeple sahibe bağlıdır.
        */
        $requesterRole = (string) DB::table('workspace_memberships')
            ->where('workspace_id', $workspace)
            ->where('user_id', $userId)
            ->value('role');

        if ($requesterRole !== MembershipRole::Owner->value) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            /*
                Yalnız DAĞITILABİLİR roller.

                `owner` listede yok: sahiplik rol düzenlemeyle verilmez,
                DEVREDİLİR — ayrı bir akışı ve ayrı bir sonucu vardır.
                `member` de yok: yeni kimse salt okunur bir role
                düşürülmemeli, o rol yalnız eski kayıtlar için var.
            */
            'role' => ['required', 'string', Rule::in(array_map(
                static fn (MembershipRole $role): string => $role->value,
                MembershipRole::invitable(),
            ))],
        ]);

        $membership = DB::table('workspace_memberships')
            ->where('id', $member)
            ->where('workspace_id', $workspace)
            ->first();

        if ($membership === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ((string) $membership->role === MembershipRole::Owner->value) {
            // Sahibi düşürmek, çalışma alanını SAHİPSİZ bırakabilirdi — ve
            // sahipsiz bir çalışma alanını kimse onaramaz. Sahiplik ayrı bir
            // akışla devredilir.
            return response()->json([
                'message' => 'Sahibin rolü buradan değiştirilemez. Sahipliği devretmek için devir akışını kullanın.',
            ], 422);
        }

        DB::table('workspace_memberships')->where('id', $member)->update([
            'role' => $validated['role'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'id' => $member,
            'workspaceId' => $workspace,
            'role' => (string) $validated['role'],
        ]);
    }
}
