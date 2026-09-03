<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Tenancy\UseCase\BuildWorkspaceContextPayload;
use App\Application\Tenancy\UseCase\SwitchWorkspaceContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SwitchWorkspaceContextController extends Controller
{
    public function __construct(
        private readonly SwitchWorkspaceContext $switchWorkspaceContext,
        private readonly BuildWorkspaceContextPayload $payload,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);

        $summary = $this->switchWorkspaceContext->handle(
            $validated['workspace_id'],
            (int) $request->user()->getKey(),
        );

        if ($summary === null) {
            return response()->json(['error' => 'workspace_not_found'], 404);
        }

        return response()->json($this->payload->for((int) $request->user()->getKey(), $summary));
    }
}
