<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Yayın geçmişi — `docs/81` (P1-05).
 *
 * "Hangi sürüm canlı?" sorusunun cevabı her zaman görünür olmalı. Yanlış
 * fiyatı gören misafirle tartışan sahip, tam olarak bunu sorar.
 *
 * Snapshot'lar listeye KONMAZ: kırk yayınlık bir geçmişte her biri menünün
 * tamamını taşır ve liste ekranı megabaytlarca veri indirirdi.
 */
final class ListPublicationsController extends Controller
{
    public function __construct(
        private readonly PublicationRepositoryPort $publications,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $live = $this->publications->current($workspace, $menu);

        return response()->json([
            'data' => array_map(static fn ($record): array => [
                'id' => $record->id,
                'version' => $record->version,
                'state' => $record->state,
                'publishedAt' => $record->publishedAt,
                'isLive' => $live !== null && $live->id === $record->id,
            ], $this->publications->history($workspace, $menu)),
        ]);
    }
}
