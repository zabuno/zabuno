<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactMessageRequest;
use App\Mail\ContactMessageReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
            $name = (string) $request->validated('name');
            $email = (string) $request->validated('email');
            $body = (string) $request->validated('message');

            $id = (int) DB::table('contact_messages')->insertGetId([
                'name' => $name,
                'email' => $email,
                'message' => $body,
                'locale' => $request->getPreferredLanguage(['tr', 'en']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->notify($id, $name, $email, $body);
        }

        return redirect('/contact')->with('contact.sent', true);
    }

    /**
     * Bildirimi gönderir — SAKLAMADAN SONRA.
     *
     * Gönderim başarısız olsa bile mesaj durur ve sebebi kayda geçer:
     * sağlayıcı bir gün cevap vermediğinde kaybolan bir talep olmamalı.
     * Ziyaretçi bunu GÖRMEZ; gönderim bizim iç meselemiz ve onun mesajı
     * kaybolmadı (`docs/93`).
     */
    private function notify(int $id, string $name, string $email, string $body): void
    {
        $to = config('contact.notify');

        // Adres yoksa gönderim de yok — ve bu bir hata değildir.
        if (! is_string($to) || trim($to) === '') {
            return;
        }

        try {
            Mail::to($to)->send(new ContactMessageReceived($name, $email, $body));

            DB::table('contact_messages')->where('id', $id)->update([
                'delivered_at' => now(),
                'delivery_failure' => null,
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('contact_messages')->where('id', $id)->update([
                // Sebep KIRPILIR: sütun sınırlı ve bir yığın izi burada
                // okunmaz; ilk cümle "neden gitmedi" sorusunu cevaplar.
                'delivery_failure' => mb_substr($exception->getMessage(), 0, 190),
                'updated_at' => now(),
            ]);
        }
    }
}
