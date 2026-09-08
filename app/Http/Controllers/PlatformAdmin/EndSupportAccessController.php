<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Support\Port\SupportAccessPort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Açık oturumu erken bitirir — `docs/133` §2.
 *
 * BU UÇ SÜREYİ DEĞİŞTİRMEZ, YALNIZ KISALTIR. Uzatan bir kardeşi yoktur:
 * `docs/122` §5 süreli olmayı şart koşar ve uzatılabilir bir süre yalnız
 * ertelenmiş bir süresizliktir.
 *
 * SALT-OKUNUR KİLİDİN İKİ İSTİSNASINDAN BİRİ BU YOLDUR
 * (`EnsureSupportAccessIsReadOnly::EXEMPT_PATHS`) ve olması gerektiği gibi
 * kiracı verisine hiç dokunmaz: bitiş damgasından başka bir şey yazmaz.
 *
 * OTURUMU YALNIZ AÇAN KAPATIR. Kimlik istekten değil, oturumun kendisinden
 * okunur; gövdede oturum kimliği taşınmaz, dolayısıyla başkasının oturumunu
 * kapatmanın yolu yoktur.
 */
final class EndSupportAccessController extends Controller
{
    public function __construct(
        private readonly SupportAccessPort $sessions,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $actorUserId = (int) $request->user()->getKey();
        $session = $this->sessions->activeFor($actorUserId);

        if ($session === null) {
            // Zaten kapalı olanı kapatmak bir olay değildir; 404 vermek,
            // süresi dolduğu için kapanmış bir oturumu arıza gibi
            // gösterirdi.
            return response()->json(['session' => null, 'ended' => false]);
        }

        return response()->json([
            'session' => null,
            'ended' => $this->sessions->end($session->id, $actorUserId),
        ]);
    }
}
