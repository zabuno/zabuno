<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Yeni bağlantı açar — `docs/95` Faz 3 UX sözleşmesi adım 5.
 *
 * Şema dışı alan, bilinmeyen sağlayıcı, boş etiket ve tutarsız kapsam
 * 422 döner (500 DEĞİL): bunların hepsi kullanıcının düzeltebileceği
 * girdi hatalarıdır. Sır hiçbir cevaba çıkmaz; dönen tek şey maskeli
 * bağlantı durumudur. İstek gövdesi log'lanmaz.
 */
final class StoreProviderConnectionController extends Controller
{
    public function __construct(private readonly PlatformConnectionAdminPort $vault) {}

    public function __invoke(Request $request): JsonResponse
    {
        $provider = CredentialProvider::tryFrom((string) $request->input('provider'));

        if ($provider === null) {
            return response()->json(['message' => 'Bilinmeyen sağlayıcı.'], 422);
        }

        $scope = CredentialScope::tryFrom((string) $request->input('scope', 'platform_owned'));

        if ($scope === null) {
            return response()->json(['message' => 'Bilinmeyen kapsam.'], 422);
        }

        $workspaceId = $request->input('workspaceId');
        $fields = $request->input('fields', []);

        if (! is_array($fields)) {
            return response()->json(['message' => 'Alanlar bir nesne olmalı.'], 422);
        }

        $values = [];

        foreach ($fields as $name => $value) {
            if (! is_string($name) || ($value !== null && ! is_scalar($value))) {
                return response()->json(['message' => 'Alan değeri metin olmalı.'], 422);
            }

            $values[$name] = (string) $value;
        }

        try {
            $id = $this->vault->createConnection(
                $provider,
                (string) $request->input('label', ''),
                $scope,
                $workspaceId === null ? null : (int) $workspaceId,
                $values,
                (int) $request->user()->getKey(),
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($this->vault->connection($id)?->toArray(), 201);
    }
}
