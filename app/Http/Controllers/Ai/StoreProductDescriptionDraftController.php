<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\UseCase\GenerateProductDescriptionDraft;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ürün açıklaması taslağı başlatır — `docs/96` (Faz 2, `opt-23`).
 */
final class StoreProductDescriptionDraftController extends Controller
{
    public function __construct(
        private readonly GenerateProductDescriptionDraft $generate,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menuItem): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $availability = $this->generate->availability($workspace);

        if (! $availability->isAvailable()) {
            return response()->json([
                'message' => 'Ürün açıklaması önerisi şu anda kullanılamıyor.',
                'reason' => $availability->value,
            ], 503);
        }

        try {
            $result = $this->generate->handle($workspace, $menuItem);
        } catch (ProviderCallException $exception) {
            return response()->json([
                'message' => 'Öneri denendi ama sağlayıcı yanıt vermedi.',
                'reason' => $exception->reason,
            ], 502);
        }

        if ($result === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $descriptionField = array_values(array_filter(
            $result['artifact']->fields,
            static fn ($field): bool => $field->name === 'description',
        ))[0] ?? null;

        return response()->json([
            'id' => $result['id'],
            // İnceleme ekranı bunu ayrı bir GET olmadan, düzenlenebilir
            // kutuda hemen gösterir (`docs/97` R4).
            'description' => $descriptionField?->value ?? '',
            'confidence' => $descriptionField?->confidence ?? 0.0,
            'uncertainFieldCount' => count($result['artifact']->uncertainFields()),
            'usedFallback' => $result['artifact']->usedFallback,
        ], 201);
    }
}
