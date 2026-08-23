<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Tenancy\UseCase\GetCurrentWorkspaceContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurrentWorkspaceContextController extends Controller
{
    public function __construct(
        private readonly GetCurrentWorkspaceContext $getCurrentWorkspaceContext,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $summary = $this->getCurrentWorkspaceContext->handle((int) $request->user()->getKey());

        if ($summary === null) {
            return response()->json(['error' => 'workspace_context_required'], 409);
        }

        return response()->json($summary->toArray());
    }
}
