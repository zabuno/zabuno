<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactMessageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Gelen mesajı SAKLAR — `docs/88` (P1-01).
 *
 * Saklamak, göndermekten önce gelir. Gereksinim iletişimi gerçek e-postaya
 * bağlıyor ve o madde sağlayıcı hesabını bekliyor; ama "ulaşmak" için
 * e-posta şart değil. Saklanan bir mesaj kaybolmaz — e-postaya bağlamak,
 * sağlayıcı gelene kadar formu ölü tutardı.
 */
final class StoreContactMessageController extends Controller
{
    public function __invoke(StoreContactMessageRequest $request): RedirectResponse
    {
        $honeypot = trim((string) $request->validated('website'));

        /*
            BAL KÜPÜ dolduysa istek SESSİZCE düşer ve başarı gibi görünür.

            Bota "yakalandın" demek, bir sonraki denemede o alanı atlamasını
            öğretirdi. İnsan bu alanı görmez, dolayısıyla dolduramaz.
        */
        if ($honeypot === '') {
            DB::table('contact_messages')->insert([
                'name' => (string) $request->validated('name'),
                'email' => (string) $request->validated('email'),
                'message' => (string) $request->validated('message'),
                'locale' => $request->getPreferredLanguage(['tr', 'en']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect('/contact')->with('contact.sent', true);
    }
}
