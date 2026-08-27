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
        if (! $this->hostIsTrusted($request)) {
            // Host başlığı İSTEMCİDEN gelir. Ona güvenmek, ürettiğimiz
            // kanonik ve imzalı adreslerin saldırganın alan adına kaymasına
            // izin verir — doğrulama e-postasındaki bağlantı oraya giderse
            // kullanıcı kimlik bilgisini saldırgana yazar.
            //
            // Bu denetim çerçevenin `TrustHosts`'u yerine burada yapılır:
            // o, süreç genelinde global statik durum kurar ve tek bir test
            // bütün süiti kırabilir. Host politikasının zaten tek sahibi
            // URL motorudur (`docs/38` §17).
            return response()->json(['message' => 'Bad request.'], 400);
        }

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

    /**
     * Yalnız beyan edilmiş Host'lara cevap verilir.
     *
     * Yerel geliştirme ve test bundan muaftır: aksi hâlde her geliştiricinin
     * makinesi ve her CI koşusu ayrı yapılandırma isterdi ve kural ilk
     * engelde toptan kapatılırdı.
     */
    private function hostIsTrusted(Request $request): bool
    {
        if (app()->environment('local', 'testing')) {
            return true;
        }

        $trusted = $this->policy->resolvedTrustedHosts((string) config('app.url'));

        if ($trusted === []) {
            return true; // beyan yoksa kısıt da yok; boşluk `docs/38` §17'de kayıtlı
        }

        return in_array(strtolower($request->getHost()), $trusted, true);
    }

    private function absolute(Request $request, string $target): string
    {
        $scheme = $this->policy->enforcesScheme() ? $this->policy->canonicalScheme() : $request->getScheme();
        $host = $this->policy->enforcesHost() ? $this->policy->canonicalHost() : $request->getHttpHost();

        return $scheme.'://'.$host.$target;
    }
}
