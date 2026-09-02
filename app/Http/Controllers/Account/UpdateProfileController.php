<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;

/**
 * Kullanıcı kendi adını düzeltir — `docs/83` (P1-07).
 *
 * Self-service bir üründe kullanıcı kendi hesabını kendi onarır. Yanlış
 * yazılmış bir ad için destek talebi açmak zorunda kalmak, ürünün "kendi
 * kendine yeter" iddiasını her gün çürütür.
 */
final class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['name' => (string) $request->validated('name')])->save();

        return response()->json(['id' => (int) $user->getKey(), 'name' => (string) $user->name]);
    }
}
