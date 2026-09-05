<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Team\UseCase\TransferWorkspaceOwnership;
use App\Domain\Authorization\Permission;
use App\Domain\Tenancy\MembershipRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class TransferWorkspaceOwnershipController extends Controller
{
    public function __construct(
        private readonly TransferWorkspaceOwnership $transferWorkspaceOwnership,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $member): JsonResponse|Response
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            SAHİPLİĞİ KİMİN DEVREDECEĞİ SAHİBİN KARARIDIR.

            Bu kontrol bir davranış EKLEMİYOR: `transferOwnership` zaten
            isteği yapanın tam olarak mevcut sahip olmasını şart koşuyor ve
            Yönetici'nin denemesi reddediliyordu. Ama reddin DİLİ yanlıştı —
            depo `false` dönüyor, buradaki tek çıkış 404'e düşüyordu. Yani
            404 bir karar değil, bir yan etkiydi.

            Kardeş uçlar aynı dili konuşmalı. `RemoveTeamMemberController` ve
            `UpdateTeamMemberRoleController` bu sınırı iki aşamalı bir kapıyla
            söylüyor ve burası geride kalmıştı: aynı kişi, aynı ekranda, iki
            komşu düğme için iki farklı cevap alıyordu.

            İzin HİÇ yoksa 404: çalışma alanının varlığı bile sızmamalı — o
            kapı yukarıda aynen duruyor. İzin var ama sahip değilse 403:
            `WorkspaceManage` Yönetici'de DE var ve olmalı (şubeyi ve karekodu
            o yürütür), ama buraya kadar gelen kişi çalışma alanını zaten
            YÖNETİYOR — varlığını gizlemenin anlamı yok ve çıkış yolu
            farklıdır (sahipten istemek). 404 deseydik, yöneticiye "o üyelik
            yok" der ve onu olmayan bir sorunu aramaya gönderirdik.
        */
        $requesterRole = (string) DB::table('workspace_memberships')
            ->where('workspace_id', $workspace)
            ->where('user_id', $userId)
            ->value('role');

        if ($requesterRole !== MembershipRole::Owner->value) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $this->transferWorkspaceOwnership->handle($workspace, $userId, $member)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->noContent();
    }
}
