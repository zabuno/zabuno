<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Security\UseCase\RecordReleaseAttestation;
use App\Domain\Security\ReleaseAttestationKey;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Panelden tanıklık — `docs/98` FF-63.
 *
 * Yalnız superadmin: bu kayıt "Stage 1 çıkabilir" iddiasının bir parçasıdır
 * ve restoran sahibinin işi değildir. Tanık, oturumdaki kullanıcıdır —
 * "kim söyledi" satıra otomatik yazılır, gövdeden alınmaz.
 */
final class StoreReleaseAttestationController extends Controller
{
    public function __construct(private readonly RecordReleaseAttestation $record) {}

    public function __invoke(Request $request): JsonResponse
    {
        $key = ReleaseAttestationKey::tryFrom((string) $request->input('key'));

        if ($key === null) {
            return response()->json(['message' => 'Bilinmeyen madde.'], 422);
        }

        $payload = $request->input('payload', []);

        if (! is_array($payload)) {
            return response()->json(['message' => 'payload bir nesne olmalı.'], 422);
        }

        $clean = [];
        foreach ($payload as $name => $value) {
            if (! is_string($name) || ($value !== null && ! is_scalar($value))) {
                return response()->json(['message' => 'payload değerleri metin olmalı.'], 422);
            }
            $clean[$name] = $value === null ? '' : (string) $value;
        }

        try {
            $id = $this->record->execute(
                $key,
                (string) $request->input('status', ''),
                (string) $request->input('summary', ''),
                $request->input('reference') === null ? null : (string) $request->input('reference'),
                $clean,
                (int) $request->user()->getKey(),
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['id' => $id, 'key' => $key->value], 201);
    }
}
