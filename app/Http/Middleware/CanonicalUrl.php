<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Url\UrlNormalizer;
use App\Domain\Url\UrlPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * URL motorunun uygulama noktası.
 *
 * Neden middleware? Çünkü kanonik biçim bir sayfanın değil, TÜM yüzeyin
 * sözleşmesidir. Her controller'ın kendi normalizasyonunu yapması, ilk
 * unutulan controller'da politikanın bitmesi demektir.
 *
 * Yalnız GET ve HEAD yönlendirilir. Bir POST'u yönlendirmek gövdeyi kaybeder
 * ve kullanıcının yazdığı formu sessizce siler.
 */
final class CanonicalUrl
{
    public function __construct(
        private readonly UrlPolicy $policy,
        private readonly UrlNormalizer $normalizer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->policy->rejectsDuplicateQueryKeys()
            && $this->normalizer->hasDuplicateQueryKeys((string) $request->server->get('QUERY_STRING', ''))
        ) {
            // Sessizce birini seçmek yerine reddediyoruz: hangi değerin
            // kazandığı katmana göre değişirse, yetki kararı da değişebilir.
            return response()->json([
                'message' => 'Duplicate query parameter.',
            ], 400);
        }

        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $normalized = $this->normalizer->normalize($request->getPathInfo(), $request->query->all());

        if ($normalized->changed) {
            // Tek sıçrama: hedef doğrudan nihai kanonik biçimdir, zincir yok.
            return redirect($this->absolute($request, $normalized->target()), $this->policy->normalizationRedirectStatus());
        }

        return $next($request);
    }

    private function absolute(Request $request, string $target): string
    {
        $scheme = $this->policy->enforcesScheme() ? $this->policy->canonicalScheme() : $request->getScheme();
        $host = $this->policy->enforcesHost() ? $this->policy->canonicalHost() : $request->getHttpHost();

        return $scheme.'://'.$host.$target;
    }
}
