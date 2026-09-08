<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Support\Port\SupportAccessPort;
use App\Domain\Support\SupportAccessWindow;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\OpenSupportAccessRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Şu an bir kiracıya bakıyor muyum?" — `docs/133` §2.
 *
 * Ekran bunu HER AÇILIŞTA sunucuya sorar, kendi belleğinde tutmaz: süre
 * sunucuda dolar ve tarayıcıda tutulan bir sayaç, sekme uyuduğunda ya da
 * makine kapandığında yalan söyler.
 *
 * PENCERE UZUNLUĞU DA BURADAN GELİR: ekran "kaç dakika" cümlesini kendi
 * kaynağından yazmaz, çünkü yapılandırma sunucudadır ve iki kaynak bir gün
 * ayrışırdı.
 */
final class ShowSupportAccessController extends Controller
{
    public function __construct(
        private readonly SupportAccessPort $sessions,
        private readonly SupportAccessWindow $window,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $session = $this->sessions->activeFor((int) $request->user()->getKey());

        return response()->json([
            'session' => $session?->toArray(),
            'windowMinutes' => $this->window->minutes(),
            'reasonMinLength' => OpenSupportAccessRequest::REASON_MIN,
        ]);
    }
}
