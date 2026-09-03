<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sağlayıcı kimlik-bilgisini yazar/döndürür — `docs/94` (Faz 2).
 *
 * Şema dışı alan 422 döner (500 DEĞİL): panel yalnızca o sağlayıcının
 * tanıdığı alanları yazabilir. Sır hiçbir cevaba çıkmaz — dönen tek şey
 * maskeli durumdur. İstek gövdesi log'lanmaz.
 */
final class StoreProviderCredentialController extends Controller
{
    public function __construct(private readonly PlatformCredentialAdminPort $vault) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $target = CredentialProvider::tryFrom($provider);

        if ($target === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $values = [];

        foreach ($request->all() as $name => $value) {
            if (! is_string($name) || $target->field($name) === null) {
                return response()->json([
                    'message' => 'Bu sağlayıcının şemasında olmayan bir alan.',
                ], 422);
            }

            if ($value !== null && ! is_scalar($value)) {
                return response()->json([
                    'message' => 'Alan değeri metin olmalı.',
                ], 422);
            }

            $values[$name] = (string) $value;
        }

        try {
            $this->vault->put($target, $values, (int) $request->user()->getKey());
        } catch (\InvalidArgumentException) {
            return response()->json(['message' => 'Geçersiz alan.'], 422);
        }

        return response()->json($this->vault->status($target)->toArray());
    }
}
