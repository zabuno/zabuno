<?php

use App\Http\Middleware\CanonicalUrl;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
