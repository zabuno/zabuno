<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bir sağlayıcıyı kapatır — `docs/94` (Faz 2).
 *
 * Kapatma SİLME değildir: kayıt korunur, yalnız `resolve` onu artık
 * çözmez (kasa env yedeğine ya da "kapalı"ya düşer). Denetim satırı bırakır.
 */
final class DisableProviderCredentialController extends Controller
{
    public function __construct(private readonly PlatformCredentialAdminPort $vault) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $target = CredentialProvider::tryFrom($provider);

        if ($target === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $this->vault->disable($target, (int) $request->user()->getKey());

        return response()->json($this->vault->status($target)->toArray());
    }
}
