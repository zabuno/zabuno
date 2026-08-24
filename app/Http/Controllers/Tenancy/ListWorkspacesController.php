<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Tenancy\UseCase\ListOwnWorkspaces;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListWorkspacesController extends Controller
{
    public function __construct(
        private readonly ListOwnWorkspaces $listOwnWorkspaces,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $summaries = $this->listOwnWorkspaces->handle((int) $request->user()->getKey());

        return response()->json(array_map(
            fn ($summary) => $summary->toArray(),
            $summaries,
        ));
    }
}
