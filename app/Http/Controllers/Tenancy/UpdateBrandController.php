<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Entitlement\Exception\EntitlementDeniedException;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Application\Tenancy\Profile\UseCase\UpdateBrand;
use App\Domain\Authorization\Permission;
use App\Domain\Entitlement\Entitlement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\UpdateBrandRequest;
use Illuminate\Http\JsonResponse;

final class UpdateBrandController extends Controller
{
    public function __construct(
        private readonly UpdateBrand $updateBrand,
        private readonly AuthorizationPort $authorization,
        private readonly RequireEntitlement $requireEntitlement,
    ) {}

    public function __invoke(UpdateBrandRequest $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /*
            MARKA GÖRÜNÜMÜ PLANA BAĞLIDIR — ama SESSİZCE DEĞİL (FF-174).

            Kapı yalnız görünüme dokunan isteklere kurulur: adını ya da saat
            dilimini düzelten bir restoran plansız da düzeltmeye devam eder
            (`Entitlement` kapsam kuralı: ek yetki verir, temel yolculuğu
            kapatmaz).

            402 kullanılır, 403 değil: kullanıcı yetkisiz DEĞİL, planı bu
            yeteneği içermiyor — çıkış yolu erişim talebi değil, plan
            yükseltmesidir. Yanıt hangi yeteneğin eksik olduğunu da söyler;
            "bu depoda yapılamayan iş çizilmez" kuralının sunucu tarafı
            budur: yapılamayan iş SESSİZCE yok sayılmaz, adıyla reddedilir.
        */
        if ($request->touchesBranding()) {
            try {
                $this->requireEntitlement->handle($workspace, Entitlement::BrandingCustom);
            } catch (EntitlementDeniedException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'entitlement' => $e->entitlement->value,
                ], 402);
            }
        }

        $brand = $this->updateBrand->handle($workspace, $request->validated());

        if ($brand === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($brand->toArray());
    }
}
