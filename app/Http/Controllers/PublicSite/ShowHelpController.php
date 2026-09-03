<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use App\Support\Localization\HelpLibrary;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "İlk 15 dakika" — `docs/89` (P1-01).
 *
 * OTURUM İSTEMEZ: tıkanan biri oturum açamıyor olabilir ve yardımın kapı
 * tutması, en çok ihtiyaç duyulduğu anda kapıyı kapatırdı.
 */
final class ShowHelpController extends Controller
{
    public function __construct(private readonly CanonicalUrl $canonical) {}

    public function __invoke(Request $request): View
    {
        $locale = HelpLibrary::localeFor($request->getPreferredLanguage(HelpLibrary::SUPPORTED));

        return view('public.help', [
            'canonicalUrl' => $this->canonical->for($request->getSchemeAndHttpHost(), '/help'),
            'anchorPrefix' => '/',
            'coreModuleCount' => count(config('core-modules')),
            'helpView' => HelpLibrary::viewFor($locale),
            'helpLocale' => $locale,
            'pageLocale' => $locale,
        ]);
    }
}
