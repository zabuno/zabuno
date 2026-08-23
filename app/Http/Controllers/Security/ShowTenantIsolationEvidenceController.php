<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Security\UseCase\ShowTenantIsolationEvidence;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowTenantIsolationEvidenceController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ShowTenantIsolationEvidence $useCase,
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

        return response()->json([
            'data' => [
                'id' => $record->id(),
                'key' => $record->key(),
                'status' => $record->status(),
                'scope' => $record->scope(),
                'runner' => $record->runner(),
                'ran_at' => $record->ranAt(),
                'duration_ms' => $record->durationMs(),
                'exit_code' => $record->exitCode(),
                'git_sha' => $record->gitSha(),
                'git_dirty' => $record->gitDirty(),
                'source_snapshot_sha256' => $record->sourceSnapshotSha256(),
                'suite_manifest_sha256' => $record->suiteManifestSha256(),
                'output_sha256' => $record->outputSha256(),
                'integrity_sha256' => $record->integritySha256(),
                'claim' => $record->claim(),
            ],
        ], 200);
    }
}
