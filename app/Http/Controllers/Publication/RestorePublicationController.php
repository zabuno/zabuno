<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Publication\Exception\PublicationPersistenceFailedException;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Önceki bir yayına dönmek — `docs/81` (P1-05).
 *
 * GEÇMİŞ SİLİNMEZ: eski snapshot YENİ bir yayın olarak yazılır. Bir yayını
 * yok saymak, "ne zaman ne yayındaydı" sorusunu cevapsız bırakırdı.
 *
 * TASLAĞA DOKUNULMAZ: sahip taslakta düzeltme yapıyor olabilir ve geri
 * alma onun yarım işini silmemeli. Geri alma MİSAFİRİN gördüğünü düzeltir,
 * sahibin çalışmasını değil.
 */
final class RestorePublicationController extends Controller
{
    public function __construct(
        private readonly PublicationRepositoryPort $publications,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu, int $publication): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // Geri almak YAYINLAMAKTIR: misafirin gördüğünü değiştirir ve aynı
        // izni ister.
        if (! $this->authorization->can($userId, Permission::MenuPublish, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $source = $this->publications->find($workspace, $menu, $publication);

        if ($source === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        try {
            $record = $this->publications->publish(
                $workspace,
                $menu,
                $source->locationId,
                $source->snapshot,
                $userId,
            );
        } catch (PublicationPersistenceFailedException) {
            return response()->json(['message' => 'Publication failed.'], 500);
        }

        return response()->json([
            'id' => $record->id,
            'workspaceId' => $record->workspaceId,
            'menuId' => $record->menuId,
            'locationId' => $record->locationId,
            'version' => $record->version,
            'state' => $record->state,
            'publishedAt' => $record->publishedAt,
            'restoredFromVersion' => $source->version,
            'snapshot' => $record->snapshot,
        ], 201);
    }
}
