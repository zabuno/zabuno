<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\QrDestination\QrToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class StoreQrCodeController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly PublicationRepositoryPort $publications,
        private readonly QrCodeRepositoryPort $qrCodes,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::QrCreate, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $menuId = (int) $request->input('menuId');

        if ($menuId <= 0 || $this->context->menuIdForLocation($workspace, $location) !== $menuId) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->publications->current($workspace, $menuId) === null) {
            return response()->json(['message' => 'No current publication for this menu.'], 422);
        }

        try {
            $token = QrToken::generateAvoiding(
                fn (string $candidate): bool => $this->qrCodes->tokenExists($candidate)
            );

            $record = $this->qrCodes->create($workspace, $location, $menuId, $token->value());
        } catch (RuntimeException) {
            return response()->json(['message' => 'QR code creation failed.'], 500);
        }

        return response()->json([
            'id' => $record->id,
            'workspaceId' => $record->workspaceId,
            'locationId' => $record->locationId,
            'menuId' => $record->menuId,
            'token' => $record->token,
            'resolverUrl' => url("/q/{$record->token}"),
            'destinationType' => $record->destinationType,
            'state' => $record->state,
        ], 201);
    }
}
