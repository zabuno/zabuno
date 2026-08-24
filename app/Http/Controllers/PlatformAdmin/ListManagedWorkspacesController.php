<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Platform\UseCase\DiscoverPlatformWorkspaces;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListManagedWorkspacesController extends Controller
{
    public function __construct(
        private readonly DiscoverPlatformWorkspaces $workspaces,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = (string) $request->query('query', '');

        return response()->json($this->workspaces->handle($query));
    }
}
