<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Support\UseCase\ReplyToSupportRequest;
use App\Domain\Support\SupportReplyOutcome;
use App\Domain\Support\SupportRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\ReplyToSupportRequestRequest;
use Illuminate\Http\JsonResponse;

/**
 * Kuyruk satırından tek tıkla cevap — SUPPORT-REPLY-01 (`docs/125` §6).
 *
 * ÇIKAMAYAN BİR CEVAP 409'DUR, 200 DEĞİL: sebep sabit bir koddur, çünkü
 * ham sağlayıcı cümlesi bir anahtar taşıyabilir (`docs/110` P0-06). Satır
 * değişmemiştir ve aynı gövde yeniden gönderilebilir.
 *
 * KULLANIM DURUMU YAPICIDA DEĞİL, ÇAĞRIDA ÇÖZÜLÜR. Yönlendirici denetleyici
 * örneğini bir kez kurup SAKLAR (`Route::getController()`); yapıcıdan geçen
 * bir bildirici, onunla birlikte o AN seçilmiş posta taşıyıcısını da
 * dondurur. Taşıyıcı ise kasadan çalışma zamanında seçilir (`docs/94`):
 * süperadmin anahtarı girip aynı ekranda yeniden gönderdiğinde ikinci
 * deneme YENİ seçimi görmek zorundadır — donmuş bir bildiriciyle
 * göremezdi ve "düzelttim, yine olmadı" derdi.
 */
final class ReplyToSupportRequestController extends Controller
{
    public function __invoke(
        ReplyToSupportRequestRequest $request,
        ReplyToSupportRequest $reply,
        int $supportRequest,
    ): JsonResponse {
        $outcome = $reply->handle(
            $supportRequest,
            (string) $request->validated('body'),
            $request->user()?->getAuthIdentifier() === null ? null : (int) $request->user()?->getAuthIdentifier(),
        );

        // Olmayan talep, rolsüz kullanıcının gördüğüyle AYNI cevabı verir.
        if ($outcome === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($outcome !== SupportReplyOutcome::Sent) {
            return response()->json(['reason' => $outcome->value], 409);
        }

        return response()->json(['status' => SupportRequestStatus::Answered->value]);
    }
}
