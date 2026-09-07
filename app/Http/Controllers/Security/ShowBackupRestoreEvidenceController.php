<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Security\UseCase\ShowBackupRestoreEvidence;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowBackupRestoreEvidenceController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ShowBackupRestoreEvidence $useCase,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::SecurityEvidenceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $record = $this->useCase->execute();

        if ($record === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $record->verifiesIntegrity()) {
            return response()->json(['message' => 'Evidence integrity check failed.'], 500);
        }

        // Medya kaydı yardımcıdır: yoksa `null`, kurcalanmışsa veritabanı
        // kaydıyla aynı kapı — hiçbir şey sunulmaz.
        $media = $this->useCase->latestMedia();

        if ($media !== null && ! $media->verifiesIntegrity()) {
            return response()->json(['message' => 'Evidence integrity check failed.'], 500);
        }

        return response()->json([
            'data' => $record->toArray(),
            'media' => $media?->toArray(),
        ], 200);
    }
}
