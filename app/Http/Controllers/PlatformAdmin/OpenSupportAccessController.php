<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Support\UseCase\OpenSupportAccess;
use App\Domain\Support\SupportAccessWindow;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\OpenSupportAccessRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Kiracı olarak bakma oturumunu açar — `docs/122` Y7, `docs/133`.
 *
 * BU UÇ DÖRT ŞARTIN GİRİŞ KAPISIDIR ve dördü de burada ya da hemen
 * arkasında zorlanır:
 *
 *  1. **Sebep** — `OpenSupportAccessRequest`; boş, kırpıldığında boş ya da
 *     tek kelimelik bir sebep kabul edilmez.
 *  2. **Süre** — `SupportAccessWindow`; yapılandırmadan gelir, koda gömülü
 *     değildir, uzatılamaz ve tavanı vardır.
 *  3. **Kiracının göreceği kayıt** — `OpenSupportAccess` kaydı yazar ve
 *     sahibe haber verir; kayıt kiracının kendi denetim izi ekranında
 *     görünür (`ShowWorkspaceAuditTrailController`).
 *  4. **Kısıt** — `EnsureSupportAccessIsReadOnly` bu oturum boyunca her
 *     yazma isteğini istek düzeyinde reddeder.
 *
 * İKİNCİ OTURUM AÇILMAZ — VE BUNU BU SINIF YAPMAZ. Açık bir oturum varken
 * bu uca gelen istek, kendisi de bir yazma olduğu için salt-okunur kilide
 * çarpar ve denetleyiciye HİÇ ULAŞMAZ. Burada ikinci bir "zaten açık" kapısı
 * yazmak, aynı kuralın iki yerde yaşaması olurdu; ikinci kopya bir gün
 * birincisinden ayrışır. Üst üste binen oturum, sessizce süre uzatmanın ve
 * iki kiracıya aynı anda bakmanın yoluydu; ikisini de kilit kapatıyor.
 */
final class OpenSupportAccessController extends Controller
{
    public function __construct(
        private readonly OpenSupportAccess $openSupportAccess,
        private readonly SupportAccessWindow $window,
    ) {}

    public function __invoke(OpenSupportAccessRequest $request, int $workspace): JsonResponse
    {
        $row = DB::table('workspaces')->where('id', $workspace)->first(['id', 'name']);

        if ($row === null) {
            // Enumeration-safe: var olmayan kiracı ile yetkisiz erişim aynı
            // cevabı verir (`EnsurePlatformSuperAdmin` ile aynı dil).
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $session = $this->openSupportAccess->handle(
            (int) $row->id,
            (int) $request->user()->getKey(),
            (string) $request->validated('reason'),
            (string) $row->name,
        );

        return response()->json([
            'session' => $session->toArray(),
            'windowMinutes' => $this->window->minutes(),
        ], 201);
    }
}
