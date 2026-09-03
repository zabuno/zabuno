<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bir bağlantıyı kapatır ya da yeniden açar — `docs/95` Faz 3.
 *
 * KAPATMAK SİLMEK DEĞİLDİR: kayıt, maskesi ve denetim izi yerinde kalır.
 * Bir hesabı yanlışlıkla kapatan superadmin, anahtarı yeniden girmeden
 * geri açabilmeli — ve "sildim, geri alamıyorum" diye bir durum olmamalı.
 * Bu yüzden silme ucu bilerek YOKTUR.
 */
final class SetProviderConnectionStateController extends Controller
{
    public function __construct(private readonly PlatformConnectionAdminPort $vault) {}

    public function __invoke(Request $request, int $connection, string $state): JsonResponse
    {
        if ($this->vault->connection($connection) === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $actor = (int) $request->user()->getKey();

        if ($state === 'disable') {
            $this->vault->disableConnection($connection, $actor);
        } else {
            $this->vault->enableConnection($connection, $actor);
        }

        return response()->json($this->vault->connection($connection)?->toArray());
    }
}
