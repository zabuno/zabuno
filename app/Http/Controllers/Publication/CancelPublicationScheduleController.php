<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Publication\Port\PublicationSchedulePort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kurulmuş bir planı iptal eder.
 *
 * İPTAL, PLANIN KENDİSİ KADAR ÖNEMLİDİR: zam kararından vazgeçen bir sahip,
 * gece 03:00'e kadar tırnak yiyerek beklemek zorunda kalmamalı. İptal
 * kaydı SİLMEZ — plan `cancelled` olarak durur, çünkü "o gece ne oldu"
 * sorusunun cevabı bir gün sorulur.
 */
final class CancelPublicationScheduleController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly PublicationSchedulePort $schedules,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu, int $schedule): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuPublish, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $this->schedules->cancel($workspace, $menu, $schedule)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json(['id' => $schedule, 'state' => 'cancelled'], 200);
    }
}
