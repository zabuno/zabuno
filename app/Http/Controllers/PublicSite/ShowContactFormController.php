<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use App\Support\Localization\SiteText;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Tıkanırsam kime sorarım?" — `docs/88` (P1-01).
 *
 * Bu sorunun cevabı sayfada "henüz bağlı bir iletişim formu yok" yazıyordu.
 */
final class ShowContactFormController extends Controller
{
    public function __construct(
        private readonly CanonicalUrl $canonical,
        private readonly SiteText $siteText,
    ) {}

    public function __invoke(Request $request): View
    {
        return view('public.contact', [
            // Ölçüm kimliği (`docs/100` Faz 3).
            'pageKey' => 'contact',
            'canonicalUrl' => $this->canonical->for($request->getSchemeAndHttpHost(), '/contact'),
            'anchorPrefix' => '/',
            'coreModuleCount' => count(config('core-modules')),
            'plans' => [],
            'st' => $this->siteText->all(
                SiteText::pick($request->getPreferredLanguage(['en', 'tr'])),
            ),
        ]);
    }
}
