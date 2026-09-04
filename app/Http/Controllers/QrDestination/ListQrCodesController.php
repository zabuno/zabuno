<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\QrDestination\Dto\QrCodeRecord;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListQrCodesController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly QrCodeRepositoryPort $qrCodes,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $items = array_map(
            static fn (QrCodeRecord $record): array => [
                'id' => $record->id,
                'workspaceId' => $record->workspaceId,
                'locationId' => $record->locationId,
                'menuId' => $record->menuId,
                'token' => $record->token,
                'resolverUrl' => url("/q/{$record->token}"),
                'destinationType' => $record->destinationType,
                'state' => $record->state,
                // FF-109: kodun insan adı. `null` da bir cevaptır — masaya
                // bağlı olmayan kod için ad uydurulmaz.
                'tableName' => $record->tableName,
                'areaLabel' => $record->areaLabel,
            ],
            $this->qrCodes->listForLocation($workspace, $location),
        );

        return response()->json($items, 200);
    }
}
