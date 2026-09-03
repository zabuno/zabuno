<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bir bağlantının alanlarını ve/veya etiketini günceller — `docs/95` Faz 3.
 *
 * Boş bırakılan bir sır alan öncekini KORUR: panel sırrı geri okuyamadığı
 * için, yalnız etiketi değiştirmek isteyen superadmin anahtarı yeniden
 * girmek zorunda kalmamalı.
 */
final class UpdateProviderConnectionController extends Controller
{
    public function __construct(private readonly PlatformConnectionAdminPort $vault) {}

    public function __invoke(Request $request, int $connection): JsonResponse
    {
        if ($this->vault->connection($connection) === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

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

        $actor = (int) $request->user()->getKey();

        try {
            if ($request->has('label')) {
                $this->vault->renameConnection($connection, (string) $request->input('label'), $actor);
            }

            $this->vault->updateConnection($connection, $values, $actor);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($this->vault->connection($connection)?->toArray());
    }
}
