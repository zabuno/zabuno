<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Support\Dto\SupportRequestAdminRow;
use App\Application\Support\UseCase\ListPlatformSupportRequests;
use App\Domain\Support\SupportRequestStatus;
use App\Http\Controllers\Controller;
use App\Infrastructure\Support\Mail\MailSupportNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Süperadmin destek kuyruğu — FF-201 (`docs/125` §5). Ekranı henüz yok.
 *
 * Tanınmayan bir `status` süzgeci HATA DEĞİL, süzgeçsiz listedir: kuyruğa
 * bakan biri yanlış bir kelime yüzünden boş ekran görmemeli.
 *
 * CEVAP ADRESİNİN DURUMU BAŞLIKTA, GÖVDEDE DEĞİL (SUPPORT-REPLY-01). Gövde
 * dondurulmuş bir satır şemasıdır (`PlatformSupportRequestAdminTest` onun
 * anahtar sırasını sayıyor) ve bir yapılandırma olgusu satırın alanı da
 * değildir: `SUPPORT_EMAIL` boşken müşteri cevaba "cevapla"yamaz, bunu
 * görevliye söylemek gerekir, ama bu kuyruğun bir satırı hakkında bir şey
 * söylemez. Başlık üç durumlu okunur: `configured` / `missing` — ekran ilk
 * yükte hiçbirini görmediyse `unknown` sayar ve UYARMAZ.
 */
final class ListSupportRequestsController extends Controller
{
    /** Adresin KENDİSİ değil, VAR OLUP OLMADIĞI taşınır. */
    public const REPLY_TO_HEADER = 'X-Support-Reply-To';

    public function __construct(
        private readonly ListPlatformSupportRequests $listRequests,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $status = SupportRequestStatus::tryFrom((string) $request->query('status', ''));

        return response()->json(array_map(
            static fn (SupportRequestAdminRow $row): array => $row->toArray(),
            $this->listRequests->handle($status),
        ))->header(
            self::REPLY_TO_HEADER,
            MailSupportNotifier::replyToAddress() === null ? 'missing' : 'configured',
        );
    }
}
