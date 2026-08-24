<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Tenancy\UseCase\CreateOwnedWorkspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateWorkspaceController extends Controller
{
    public function __construct(
        private readonly CreateOwnedWorkspace $createOwnedWorkspace,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $summary = $this->createOwnedWorkspace->handle($validated['name'], (int) $request->user()->getKey());

        return response()->json($summary->toArray(), 201);
    }
}
