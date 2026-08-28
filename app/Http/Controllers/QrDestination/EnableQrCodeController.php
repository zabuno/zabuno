<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\QrDestination\Exception\QrCodePersistenceFailedException;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Devre dışı bırakılan bir kodu geri açar — `docs/81` (P1-03).
 *
 * `DisableQrCodeController` tek yönlüydü. Yanlışlıkla kapatılan bir kod,
 * masadaki basılı kâğıdı KALICI olarak ölü bırakıyordu ve tek çare yeniden
 * bastırmaktı — yani "kodu bir kez bas, hedefi sonra değiştir" vaadinin
 * ihlali.
 *
 * Açma ve kapama AYNI izne bağlıdır: kapatabilen açabilmelidir, aksi hâlde
 * yetki modeli kullanıcıyı kendi yaptığı işin içine hapsederdi.
 */
final class EnableQrCodeController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly QrCodeRepositoryPort $qrCodes,
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

        if (! $this->authorization->can($userId, Permission::QrDisable, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $updated = $this->qrCodes->enable($qrCode);
        } catch (QrCodePersistenceFailedException) {
            return response()->json(['message' => 'QR code could not be enabled.'], 500);
        }

        return response()->json(['id' => $updated->id, 'state' => $updated->state], 200);
    }
}
