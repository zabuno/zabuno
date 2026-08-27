<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Url\CanonicalUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Herkese açık pazarlama ve yasal sayfalar.
 *
 * Bu sayfalar SUNUCUDA üretilir ve React paketini hiç yüklemez. Karar
 * ölçümle alındı: istemcide üretildiklerinde bir tarayıcı botunun gördüğü
 * gövde 1.736 bayttı ve içeriği `<div id="app"></div>` ibaretti — yani
 * ürünün kendi tanıtımı ne arama motorunda ne de JavaScript çalıştırmayan
 * AI botlarında görünüyordu.
 *
 * Etkileşim gerektiren yüzeyler (`/app`, `/platform`) React olarak kalır;
 * burada etkileşim yok, yalnız metin ve bağlantı var.
 */
final class FoundationStatusController extends Controller
{
    /** Yasal sayfa yollarının başlıkları. */
    private const LEGAL_TITLES = [
        'terms' => 'Terms',
        'privacy' => 'Privacy',
        'kvkk' => 'KVKK',
    ];

    public function __construct(private readonly CanonicalUrl $canonical) {}

    public function __invoke(Request $request): View
    {
        $path = trim($request->getPathInfo(), '/');
        $shared = [
            'coreModuleCount' => count(config('core-modules')),
            'canonicalUrl' => $this->canonical->for($request->getSchemeAndHttpHost(), $request->getPathInfo()),
            // Yasal sayfalarda gezinti çıpaları ana sayfaya işaret eder;
            // burada o başlıklar yok.
            'anchorPrefix' => $path === '' ? '' : '/',
        ];

        if (isset(self::LEGAL_TITLES[$path])) {
            return view('public.legal', $shared + ['title' => self::LEGAL_TITLES[$path]]);
        }

        return view('public.home', $shared);
    }
}
