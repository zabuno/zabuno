<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Superadmin sağlayıcı kasası listesi — `docs/94` (Faz 2).
 *
 * Admin portunu alır: sırrı GERİ OKUYAMAZ. Dönen her şey maskeli durumdur.
 */
final class ListProviderCredentialsController extends Controller
{
    public function __construct(private readonly PlatformCredentialAdminPort $vault) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(array_map(
            static fn (CredentialStatus $status): array => $status->toArray(),
            $this->vault->all(),
        ));
    }
}
