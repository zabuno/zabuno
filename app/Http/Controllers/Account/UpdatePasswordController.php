<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Oturum açıkken şifre değiştirme — `docs/83` (P1-07).
 *
 * KARAR: diğer oturumlar SONLANDIRILIR.
 *
 * İnsanların şifre değiştirmesinin en yaygın nedeni, birinin onu ele
 * geçirdiğinden şüphelenmektir. Diğer oturumları açık bırakmak işlemin
 * amacını boşa çıkarırdı: şifre değişir, izinsiz giren kişi içeride kalır.
 *
 * Sonlandırma oturum TABLOSUNDAN yapılır (`SESSION_DRIVER=database`), çünkü
 * ölçülebilir olan tek yol budur; "remember token"ı döndürmek yalnız
 * hatırlanan girişleri keser, açık oturumları değil.
 */
final class UpdatePasswordController extends Controller
{
    public function __invoke(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
            // Hatırlanan girişler de geçersizleşir.
            'remember_token' => Str::random(60),
        ])->save();

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        // YALNIZ bu kullanıcının oturumları. Başkasının oturumuna dokunmak,
        // bir kullanıcının işlemini başka birinin hesabına taşırdı.
        $others = DB::table('sessions')->where('user_id', $user->getKey());

        if ($currentSessionId !== null) {
            $others->where('id', '!=', $currentSessionId);
        }

        $ended = $others->delete();

        return response()->json([
            'endedOtherSessions' => (int) $ended,
        ]);
    }
}
