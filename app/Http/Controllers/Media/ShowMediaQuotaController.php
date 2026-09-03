<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaQuotaPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kota sayaçları — sahip "ne kadar yerim kaldı?" sorusunu buradan okur. */
final class ShowMediaQuotaController extends Controller
{
    public function __construct(
        private readonly MediaQuotaPort $quota,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json(['quota' => $this->quota->statusFor($workspace)->toArray()]);
    }
}
