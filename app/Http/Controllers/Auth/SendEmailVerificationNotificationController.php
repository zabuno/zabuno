<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Fortify'ın POST /email/verification-notification ucunu gölgeler.
 *
 * Fortify'ın kendi ucu HER ZAMAN 202 döner. Kayıt akışında bu doğrudur —
 * taşıyıcı arızası hesabı düşürmemeli. Ama YENİDEN GÖNDERME ekranında aynı
 * cevap yalana dönüşür: `VerificationPending` bileşeni `response.ok`
 * değerine bakıp "doğrulama e-postası gönderildi" yazar ve kullanıcı hiç
 * çıkmamış bir e-postayı beklemeye başlar (`docs/110` P0-06).
 *
 * Ekranda "gönderildi" yazması için gerçekten gönderilmiş olması gerekir.
 * Bu uç, çıkmadıysa BAŞARI DÖNMEZ.
 *
 * Gölgeleme deponun mevcut deseni: GET /email/verify/{id}/{hash},
 * POST /register ve POST /logout da aynı şekilde Fortify'dan önce
 * kaydedilir (`routes/web.php`).
 */
final class SendEmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $user = $request->user();

        // Zaten doğrulanmışsa gönderilecek bir şey yok ve bu bir hata
        // değildir: kullanıcı iki sekmede aynı işi yapmış olabilir.
        if ($user->hasVerifiedEmail()) {
            return response()->noContent(202);
        }

        if ($user->deliverEmailVerificationLink()) {
            return response()->noContent(202);
        }

        /*
            SEBEP EKRANA ÇIKMAZ.

            Sağlayıcının cevabı uç adresini, alan adını ve ham gövdeyi
            taşıyabilir; ayrıntı günlüğe yazıldı. Kullanıcıya söylenen tek
            şey durumun kendisi: e-posta çıkmadı. Tahmini süre ya da
            "birazdan gelir" DENMEZ — taşıyıcının ne zaman döneceğini
            bilmiyoruz.
        */
        return response()->json([
            'message' => 'The verification email could not be sent.',
        ], 503);
    }
}
