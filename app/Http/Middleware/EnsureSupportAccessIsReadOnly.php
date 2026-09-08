<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Support\Port\SupportAccessPort;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kiracı olarak bakma oturumu SALT OKUNURDUR — `docs/122` §5, `docs/133` §4.
 *
 * YASAK BURADA, DENETLEYİCİDE DEĞİL. `docs/122` §5 "o oturumda
 * yapılabilecekler kısıtlıdır" der; kısıtı her denetleyiciye bir `if`
 * olarak dağıtmak, bir gün yazılacak yüz birinci denetleyicinin o `if`'i
 * unutmasıyla biterdi. Burada kural TEK ve isteğin kendisi üzerinde: bu
 * oturumda güvenli olmayan bir HTTP yöntemi hiç ele alınmaz. Yeni bir uç
 * eklemek yasağı gevşetmez, çünkü yeni uç bu katmanın ARKASINDA doğar.
 *
 * "Güvenli yöntem" ayrımı HTTP'nin kendi ayrımıdır (RFC 9110 §9.2.1):
 * GET/HEAD/OPTIONS okumaz-yazmaz sayılır; kalan her şey yazma niyetidir.
 * Ödeme, plan değişikliği, silme, davet ve yayın hepsi POST/PUT/DELETE'tir
 * ve tek tek sayılmalarına gerek yoktur — sayılmış bir liste, listeye
 * girmeyi unutan bir fiil demektir.
 *
 * İSTİSNA LİSTESİ İKİ SATIRDIR VE UZAMAZ:
 *
 *  - `api/admin/support-access/end` — oturumu BİTİRMEK. Bir destek
 *    görevlisinin kendi bakışını kapatamaması, kapıyı sıkmaz; yalnız
 *    süresi dolana kadar açık tutar.
 *  - `logout` — oturumu kapatmak. Ürüne kilitlenmiş bir kullanıcı, bu
 *    paketin amaçladığı güvenlik değil, bir arıza olurdu.
 *
 * İkisi de KİRACI VERİSİNE dokunmaz. Bir gün buraya üçüncü bir satır
 * eklenmek istenirse, o bir kapsam kararıdır ve `docs/133` §4'e aittir.
 *
 * KULLANICI İKİ KORUMADAN OKUNUR: bu ara katman rota grubunda, `auth:sanctum`
 * rota ara katmanından ÖNCE çalışır. Oturum çerezli istekte kullanıcı
 * varsayılan korumadan, jetonlu istekte `sanctum` korumasından gelir;
 * ikisine de bakmak, yalnız birine bakmanın sessizce açık bırakacağı yolu
 * kapatır.
 */
final class EnsureSupportAccessIsReadOnly
{
    /** RFC 9110 §9.2.1 — okuyan, yazmayan yöntemler. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** Kiracı verisine dokunmayan, oturumu BİTİREN iki yol. */
    public const EXEMPT_PATHS = [
        'api/admin/support-access/end',
        'logout',
    ];

    public function __construct(
        private readonly SupportAccessPort $supportAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        $user = $request->user('sanctum') ?? $request->user();

        if ($user === null) {
            return $next($request);
        }

        $session = $this->supportAccess->activeFor((int) $user->getAuthIdentifier());

        if ($session === null) {
            return $next($request);
        }

        if ($request->is(self::EXEMPT_PATHS)) {
            return $next($request);
        }

        /*
            403, 404 DEĞİL. Süperadmin bu ucun var olduğunu zaten biliyor;
            gizlenecek bir şey yok. Gizlenmesi gereken tam tersi: NEDEN
            reddedildiği açıkça yazılır, yoksa destek görevlisi bunu bir
            arıza sanıp aynı işi başka bir yoldan denemeye çalışır.
        */
        return new JsonResponse([
            'message' => 'This support access session is read-only.',
            'supportAccess' => [
                'workspaceId' => $session->workspaceId,
                'expiresAt' => $session->expiresAt,
            ],
        ], 403);
    }
}
