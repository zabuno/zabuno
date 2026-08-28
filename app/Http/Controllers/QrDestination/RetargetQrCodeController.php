<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\QrDestination\Exception\QrCodePersistenceFailedException;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Basılı kodun hedefini taşır — `docs/81` (P1-03).
 *
 * Sahip 40 masa için kod bastı, sonra menüsünü yeniden düzenledi ve
 * kodların YENİ menüye bakmasını istiyor. Bunu yapamamak, "bir kez bas"
 * vaadini bir kez daha bastırmaya çeviriyordu.
 *
 * TOKEN DEĞİŞMEZ. Masadaki kâğıt aynı kâğıttır; değişen yalnız açtığı menü.
 */
final class RetargetQrCodeController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly MenuCatalogApiContextPort $context,
    ) {}

    public function __invoke(Request $request, int $workspace, int $qrCode): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $record = $this->qrCodes->findById($qrCode);

        if ($record === null || $record->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // Hedef değiştirmek, kodun ne gösterdiğini değiştirmektir: kod
        // OLUŞTURMA izniyle aynı ağırlıkta.
        if (! $this->authorization->can($userId, Permission::QrCreate, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'menuId' => ['required', 'integer', 'min:1'],
        ]);

        $menuId = (int) $validated['menuId'];

        $menu = DB::table('menus')
            ->where('id', $menuId)
            ->where('workspace_id', $workspace)
            ->first();

        // Menü BU çalışma alanının olmalı; olmazsa 404 — başka bir
        // restoranın menüsünün varlığı bile sızmamalı.
        if ($menu === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            ŞUBE KİLİDİ YOK — ve bu bilinçli.

            Şema şube başına TEK menü tutuyor (`menus.location_id` tekil),
            dolayısıyla "aynı şubede başka bir menü" diye bir şey yok.
            Hedef değiştirmenin tek gerçek anlamı, basılı bir kodun BAŞKA
            BİR ŞUBENİN menüsünü göstermesi: kart fiziksel olarak taşınmış
            ya da şube yapısı değişmiştir.

            Bunu yasaklamak, tam da kaçınmak istediğimiz "yeniden bastır"
            tuzağını kurardı. Kiracı sınırı yeterli koruma: menü BU çalışma
            alanının olmak zorunda.

            Kodun kendi şubesi de taşınır; aksi hâlde ölçüm, kodun artık
            göstermediği şubeye yazılırdı.
        */
        try {
            $updated = $this->qrCodes->retarget($qrCode, $menuId, (int) $menu->location_id);
        } catch (QrCodePersistenceFailedException) {
            return response()->json(['message' => 'QR destination could not be changed.'], 500);
        }

        return response()->json([
            'id' => $updated->id,
            'menuId' => $updated->menuId,
            'token' => $updated->token,
            'state' => $updated->state,
        ], 200);
    }
}
