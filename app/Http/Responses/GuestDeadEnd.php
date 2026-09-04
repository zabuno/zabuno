<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\Localization\GuestText;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Misafirin karşısına çıkan çıkmaz sokak.
 *
 * Bir QR kodu artık bir menüye gitmiyorsa, kodu tarayan kişi bir restoran
 * masasında oturan bir müşteridir. Ona `{"message":"Not Found."}` göstermek
 * ürünün kendisini bozuk gösterir; oysa olan şey anlaşılabilir bir durumdur.
 *
 * KRİTİK: bu yanıt her durumda AYNIDIR — bilinmeyen token, bozuk token ve
 * devre dışı bırakılmış kod ayırt edilemez. Bu, `QR-PUBLIC-404-UNIFORM-01`
 * ile dondurulmuş bir güvenlik kararıdır: farklı yanıt vermek, saldırganın
 * hangi token'ların BİR ZAMANLAR var olduğunu ölçmesine izin verirdi.
 *
 * Bu yüzden yaygın SEO tavsiyesi olan "emekli kaynak için 410 Gone" burada
 * UYGULANMAZ. 410, "bu vardı ve kalıcı olarak gitti" der — yani tam olarak
 * saklamak istediğimiz bilgiyi açık eder.
 */
final class GuestDeadEnd
{
    public static function respond(Request $request): SymfonyResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            Metin ŞABLONDA değil KATALOGDA yaşar (FF-98): şablona yazılmış bir
            cümleyi sahibi hiçbir PO dosyasında bulamaz ve çeviremez.
        */
        return response()->view('public-not-found', [
            'text' => app(GuestText::class)->deadEnd(),
        ], 404)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
