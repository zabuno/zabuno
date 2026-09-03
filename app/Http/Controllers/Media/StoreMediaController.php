<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Dto\MediaIntake;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Application\Media\UseCase\ProcessAcceptedMediaAsset;
use App\Application\Media\UseCase\ScanQuarantinedMediaAsset;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use Illuminate\Http\JsonResponse;

final class StoreMediaController extends Controller
{
    public function __construct(
        private readonly MediaRepositoryPort $media,
        private readonly AuthorizationPort $authorization,
        private readonly ScanQuarantinedMediaAsset $scanQuarantinedMediaAsset,
        private readonly ProcessAcceptedMediaAsset $processAcceptedMediaAsset,
    ) {}

    public function __invoke(StoreMediaRequest $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            IDEMPOTENCY (`docs/49` Faz 2 madde 1). Telefonda bağlantı koparsa
            istemci aynı dosyayı aynı anahtarla yeniden gönderir; sunucu onu
            ikinci bir görsel sanmaz, var olanı döner. Anahtar İSTEMCİNİN
            işidir — sunucunun içerikten türetmesi (checksum) "aynı fotoğrafı
            iki ürüne yükledim" gibi meşru tekrarı da yutardı.
        */
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));

        if ($idempotencyKey !== '' && strlen($idempotencyKey) <= 64) {
            $existing = $this->media->findByIdempotencyKey($workspace, $idempotencyKey);

            if ($existing !== null) {
                return response()->json([
                    'id' => $existing->id,
                    'workspaceId' => $existing->workspaceId,
                    'status' => $existing->status,
                    'altText' => $existing->altText,
                    'slot' => $existing->slot,
                    'replayed' => true,
                ], 200);
            }
        } else {
            $idempotencyKey = null;
        }

        $file = $request->file('file');

        $intake = new MediaIntake(
            temporaryPath: (string) $file->getRealPath(),
            originalName: (string) $file->getClientOriginalName(),
            detectedMimeType: (string) $file->getMimeType(),
            sizeBytes: $file->getSize() ?: 0,
            altText: (string) $request->validated('altText'),
            slot: (string) $request->validated('slot'),
            idempotencyKey: $idempotencyKey,
        );

        $asset = $this->media->intakeToQuarantine($workspace, $intake);

        ($this->scanQuarantinedMediaAsset)($workspace, $asset->id);

        ($this->processAcceptedMediaAsset)($workspace, $asset->id);

        $asset = $this->media->find($asset->id) ?? $asset;

        return response()->json([
            'id' => $asset->id,
            'workspaceId' => $asset->workspaceId,
            'status' => $asset->status,
            'altText' => $asset->altText,
            'slot' => $asset->slot,
        ], 201);
    }
}
