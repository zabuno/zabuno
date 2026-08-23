<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Platform\Port\PlatformAuthorizationPort;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformSuperAdmin
{
    public function __construct(
        private readonly PlatformAuthorizationPort $platformAuthorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->platformAuthorization->isSuperAdmin($userId)) {
            return new JsonResponse(['message' => 'Not Found.'], 404);
        }

        return $next($request);
    }
}
