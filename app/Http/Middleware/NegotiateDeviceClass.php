<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Device\DeviceClass;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cihaz sınıfını çözer ve yanıtın ÖNBELLEKLENEBİLİRLİĞİNİ buna göre işaretler.
 *
 * `Vary` burada süs değil, doğruluk şartıdır. Aynı adres (`/app`) cihaza göre
 * FARKLI HTML döndürüyor; `Vary` olmadan araya giren herhangi bir önbellek —
 * tarayıcı, vekil, CDN — ilk gelen yanıtı herkese servis eder. Sonuç, teşhisi
 * çok zor bir arızadır: masaüstü kullanıcısı mobil düzeni görür, sayfayı
 * yenileyince düzelir, ve kayıtlarda hiçbir iz kalmaz.
 *
 * `Accept-CH` ise tarayıcıdan ipucu İSTER. İlk istekte ipucu gelmez —
 * tarayıcı onu ancak sunucu talep ettikten sonra gönderir. Bu yüzden ilk
 * karar User-Agent'a dayanır ve sonraki isteklerde yapılandırılmış ipuca
 * yükselir.
 */
final class NegotiateDeviceClass
{
    /** Cihaz sınıfının istek içinde taşındığı anahtar. */
    public const ATTRIBUTE = 'zabuno_device_class';

    public function handle(Request $request, Closure $next): Response
    {
        $device = DeviceClass::detect($request);

        $request->attributes->set(self::ATTRIBUTE, $device);

        /** @var Response $response */
        $response = $next($request);

        $response->setVary(['Sec-CH-UA-Mobile', 'User-Agent'], false);
        $response->headers->set('Accept-CH', 'Sec-CH-UA-Mobile');

        return $response;
    }
}
