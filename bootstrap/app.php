<?php

use App\Http\Middleware\CanonicalUrl;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        /*
         * Ters vekil güveni.
         *
         * Üretimde uygulamanın önünde TLS'i sonlandıran bir vekil var
         * (`docker-compose.yml` → `proxy`). Vekil güvenilmezse uygulama
         * isteğin HTTPS olduğunu BİLEMEZ: ürettiği mutlak adresler `http://`
         * çıkar, sitemap yanlış şema yayar ve şema zorlaması açıkken sonsuz
         * yönlendirme döngüsü oluşur. İlk yerel denemede yakalanmamıştı,
         * çünkü konteyner vekilsiz test edilmişti.
         *
         * `at: '*'` neden güvenli: `app` servisi ana makineye HİÇBİR port
         * yayımlamıyor ve yalnız `internal` Docker ağından erişilebiliyor.
         * Yani bu başlıkları gönderebilecek tek istemci vekilin kendisi.
         * `DeploymentContractTest` o kısıtı ayrıca zorluyor — port yayımı
         * eklendiği gün burası da yeniden düşünülmeli.
         *
         * `X-Forwarded-Host` bilinçli olarak güvenilir: bu bir SaaS ve aynı
         * yazılım birden çok alan adında çalışır. Host'u sabitlemek, ikinci
         * alan adını birincinin adreslerine mahkûm ederdi.
         */
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // URL motoru güvenlik başlıklarından ÖNCE çalışır: kanonik olmayan
        // bir adres zaten yönlendirilecekse, o yanıtı işlemenin anlamı yok.
        $middleware->prepend(CanonicalUrl::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Gövde `post_max_size`'ı aştığında Laravel bunu ZATEN yakalıyor
         * (`ValidatePostSize` → `PostTooLargeException`) ve 413 döndürüyor.
         * Eksik olan tespit değil, SUNUM: varsayılan yanıt "The POST data is
         * too large." diyor, hangi alanın sorunlu olduğunu söylemiyor ve
         * sınırın ne olduğunu vermiyor. Form da bunu alan hatası olarak
         * gösteremiyor, çünkü `errors` yok.
         *
         * Doğrulama hatalarıyla aynı biçime sokuyoruz: `errors.file`. Böylece
         * mesaj, kullanıcının düzeltmesi gereken alanın yanında çıkar.
         */
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $limit = (string) ini_get('post_max_size');

            return response()->json([
                'message' => 'Upload too large.',
                'errors' => [
                    'file' => [sprintf(
                        'The upload is larger than this server accepts (%s). Choose a smaller file.',
                        $limit === '' ? 'the server limit' : $limit
                    )],
                ],
            ], 413);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
