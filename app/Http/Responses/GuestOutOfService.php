<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\Localization\GuestLocale;
use App\Support\Localization\GuestText;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * SERVİS DIŞI SAAT (FF-139) — çıkmaz sokağın DEĞİL, dürüstlüğün yanıtı.
 *
 * Şube saate göre menü değiştirebiliyor (`docs/109` §7.1). Sahip bir gece
 * menüsü tanımlayıp saatini verip içeriğini yayınlamamış olabilir. O saatte
 * misafirin karşısına çıkabilecek üç şey vardı ve ikisi yalandı:
 *
 * - `GuestDeadEnd` (404): "menü bulunamadı" — restoranın kapandığını
 *   sandırır. Oysa menü duruyor.
 * - Boş menü iskeleti: daha kötüsü; sahibin menüsünü sildiğini sandırır.
 * - Bu sayfa: "bu saatte servis edilen menü yok" + varsa sonraki servisin
 *   saati. Tek doğru olan bu.
 *
 * `GuestDeadEnd` ile AYRI durması bilinçlidir: o yanıt bilinmeyen, bozuk ve
 * devre dışı kodu ayırt edilemez kılmak zorundadır (`QR-PUBLIC-404-UNIFORM-01`).
 * Bu sayfa ise yalnız ZATEN 200 dönebilen bir adres için açılır — çağıran o
 * kapıyı `ResolveGuestMenuView` içinde geçer — dolayısıyla o kuralı delmez.
 *
 * SAAT UYDURULMAZ. `nextServiceClock` yalnız sahibin kendi geçişlerinden
 * okunmuş, yayını olan bir menünün başlangıcıdır; yoksa `null` gelir ve
 * ekranda saat satırı hiç çizilmez.
 */
final class GuestOutOfService
{
    public static function respond(
        Request $request,
        string $brandName,
        string $contentLocale,
        ?string $nextServiceClock,
    ): SymfonyResponse {
        /*
            ARAYÜZ dili misafirin seçimidir, İÇERİK dili restoranındır
            (`docs/85`). Bu sayfada içerik yok ama cümle yine de restoranın
            diliyle BAŞLAR: masadaki kişi menüyü hangi dilde bekliyorsa
            açıklamayı da o dilde bekler.
        */
        $guestLocale = GuestLocale::resolve($request, $contentLocale);

        if ($request->expectsJson()) {
            /*
                JSON isteyen istemciye de AYNI GERÇEK söylenir, ama 404 ile
                değil: 404 "yok" demektir ve burada yoklukta olan bir şey
                yok — servis saati dışındayız.
            */
            return response()->json([
                'state' => 'out_of_service',
                'nextServiceClock' => $nextServiceClock,
            ], 200);
        }

        return response()->view('public-menu-out-of-service', [
            'text' => app(GuestText::class)->outOfService($guestLocale, $nextServiceClock),
            'brandName' => trim($brandName),
        ], 200)
            // Geçici bir hâl indekslenmez; ama menünün kalıcı adresi hâlâ
            // geçerli olduğu için bağlantılar izlenmeye açık kalır.
            ->header('X-Robots-Tag', 'noindex, follow')
            ->cookie(GuestLocale::COOKIE, $guestLocale, 60 * 24 * 365, '/', null, $request->isSecure(), false, false, 'Lax');
    }
}
