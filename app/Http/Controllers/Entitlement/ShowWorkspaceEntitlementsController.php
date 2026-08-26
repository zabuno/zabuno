<?php

declare(strict_types=1);

namespace App\Http\Controllers\Entitlement;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\Entitlement\Entitlement;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bir workspace'in planının hangi yetenekleri verdiğini bildirir (CORE-04).
 *
 * BİLİNEN her yeteneği döner — yalnız verilenleri değil. Arayüz "planınızda
 * yok" diyebilmek için neyin var OLMADIĞINI da bilmek zorundadır; yalnız
 * verilenleri dönmek, kullanıcıyı bir özelliğin varlığından habersiz bırakır
 * ve yükseltme yolunu görünmez kılar.
 *
 * Bu uç bir KAPI DEĞİLDİR, bir sorgudur. Yetki kararı her zaman sunucuda,
 * eylemin kendi ucunda verilir (`RequireEntitlement`); arayüzün bir düğmeyi
 * gizlemesi güvenlik sınırı sayılmaz.
 */
final class ShowWorkspaceEntitlementsController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly EntitlementRepositoryPort $entitlements,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        // Üye olmayana workspace'in varlığı bile sızmaz.
        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $granted = $this->entitlements->forWorkspace($workspace);

        return response()->json([
            'capabilities' => array_map(
                static fn (Entitlement $entitlement): array => [
                    'key' => $entitlement->value,
                    'label' => $entitlement->label(),
                    'granted' => $granted->grants($entitlement),
                ],
                Entitlement::cases(),
            ),
        ]);
    }
}
