<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\Contact\ResponseCommitment;
use App\Support\Localization\SiteText;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Tıkanırsam kime sorarım?" — `docs/88` (P1-01).
 *
 * Bu sorunun cevabı sayfada "henüz bağlı bir iletişim formu yok" yazıyordu.
 * FF-201 (`docs/125`): sayfa artık gönderim sonrası REFERANSI gösterir ve
 * yalnız yapılandırılmışsa yanıt taahhüdünü yazar.
 */
final class ShowContactFormController extends Controller
{
    public function __construct(
        private readonly SiteShell $shell,
        private readonly ResponseCommitment $commitment,
    ) {}

    public function __invoke(Request $request): View
    {
        // Kabuk verisi TEK yerden (`SiteShell`); sayfa yalnız kendi
        // gövdesinin ihtiyacını ekler.
        $shell = $this->shell->context($request, 'contact', '/contact');

        // Taahhüt cümlesi kabuğun seçtiği dille aynı dilde kurulur.
        $locale = SiteText::pick($request->getPreferredLanguage(['en', 'tr']));

        $reference = $request->session()->get('contact.reference');
        $reference = is_string($reference) && $reference !== '' ? $reference : null;

        return view('public.contact', $shell + [
            'plans' => [],
            // Referans cümlesi BURADA kurulur: şablon yer tutucu bilmez.
            'sentReference' => $reference === null
                ? null
                : str_replace('{reference}', $reference, $shell['st']['contactSentReference']),
            // `null` = yapılandırılmamış = sayfada hiçbir taahhüt yok.
            'commitment' => $this->commitment->sentence($locale),
        ]);
    }
}
