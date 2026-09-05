<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\MenuCatalog\Port\OutOfStockPort;
use App\Application\Publication\Exception\UnreadyDraftException;
use App\Application\Publication\UseCase\AssembleDraftSnapshot;
use App\Application\Publication\UseCase\ResolveGuestMenuView;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Taslağın önizlemesi — misafirin göreceği sayfanın AYNISI, ama yayınlanmış
 * sürüm yerine TASLAKTAN üretilir.
 *
 * Üç şeyi aynı anda doğru yapmak zorundadır:
 *
 * 1. AYNI ŞABLON. Ayrı bir "önizleme görünümü" yazılsaydı, ikisi zamanla
 *    ayrışır ve sahip önizlemede görmediği bir şeyi yayında görürdü —
 *    önizlemenin tek işi budur ve o an değerini kaybederdi.
 * 2. ARAMA MOTORUNA KAPALI. `noindex`: bu sayfa hiçbir zaman aranıp
 *    bulunmamalı, çünkü yayınlanmamış fiyatlar taşır.
 * 3. KENDİNİ SÖYLER. Sayfanın başında "bu bir önizleme, misafirler henüz
 *    bunu görmüyor" yazar. Bağlantı bir grup sohbetine düşerse, onu açan
 *    kişi de bunun canlı menü olmadığını görür.
 * 4. MİSAFİRİN SAATİNİ DE GÖSTERİR (FF-143). Şube o anda kapalıysa,
 *    misafirin sayfasındaki şerit burada da çizilir. Çizilmeseydi önizleme
 *    tam da var olma sebebine —"masadaki misafir bunu nasıl görecek?"— yanlış
 *    cevap verirdi ve sahip, gece saatinde menüsüne bakan misafirin ne
 *    gördüğünü ancak YAYINLADIKTAN sonra öğrenirdi.
 *
 *    İKİ UYARI BİRBİRİNİ EZMEZ. "Bu bir önizleme" ile "şu anda kapalıyız"
 *    farklı iki gerçektir ve aynı anda doğrudurlar; ikisi de ayrı ayrı
 *    çizilir.
 *
 * ÖLÇÜM AYRI YÜZEYDİR (`docs/46`, `docs/84`): yüzey `menu_preview` olarak
 * bildirilir ve menünün kalıcı anahtarı GÖNDERİLMEZ. Aksi hâlde sahibin
 * kendi kontrolleri, misafir taramalarının arasına karışır ve "kaç kez
 * görüntülendi" sayısı sessizce şişerdi.
 */
final class ShowDraftPreviewController extends Controller
{
    public function __construct(
        private readonly AssembleDraftSnapshot $assembler,
        private readonly OutOfStockPort $outOfStock,
        /*
            Kapalılık kararı BURADA VERİLMEZ, oradan SORULUR (FF-143).

            Bu sayfa taslağı çizer, yayınlanmış sürümü değil; dolayısıyla
            `forAddressedMenu` yolundan hiç geçmez ve elinde bir
            `GuestMenuView` yoktur. Yine de aynı soruyu sorar ve aynı yerden
            cevap alır — kendi hesabını yapsaydı, aynı şube için önizleme ile
            misafirin sayfası bir gün iki farklı saat söylerdi.
        */
        private readonly ResolveGuestMenuView $guestMenuView,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): SymfonyResponse
    {
        $blockedReason = null;

        try {
            $assembled = $this->assembler->forMenu($workspace, $menu);
        } catch (UnreadyDraftException $e) {
            /*
                Hazır olmayan taslak da GÖSTERİLİR — boş olarak, sebebiyle
                birlikte. "Bulunamadı" demek yanlış olurdu: menü duruyor,
                yalnız yayınlanacak durumda değil ve sahibin öğrenmesi
                gereken tam olarak budur.
            */
            $assembled = ['snapshot' => ['categories' => []]];
            $blockedReason = $e->getMessage();
        }

        if ($assembled === null) {
            abort(404);
        }

        return response()->view('public-menu', [
            'snapshot' => $assembled['snapshot'],
            'outOfStockItemIds' => $this->outOfStock->forMenu($menu),
            'previewNotice' => 'Draft preview — your guests are not seeing this yet.',
            'previewBlockedReason' => $blockedReason,
            /*
                Saati girilmemiş şube, okunamayan hafta ve AÇIK şube — üçü de
                `null` döner ve şerit hiç çizilmez. Hazır olmayan taslakta bile
                sorulur: menü yayınlanamıyor olabilir ama şubenin kapısı yine
                de açık ya da kapalıdır ve bu iki soru birbirine bağlı değildir.
            */
            'closedNotice' => $this->guestMenuView->closedNoticeForMenu($workspace, $menu),
            'analyticsContext' => [
                'zabuno_surface' => 'menu_preview',
                'zabuno_tenant_id' => (string) $workspace,
                'zabuno_menu_id' => (string) $menu,
            ],
        ], 200);
    }
}
