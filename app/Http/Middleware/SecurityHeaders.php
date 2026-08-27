<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Url\UrlPolicy;
use App\Support\Analytics\AnalyticsConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tarayıcıya güvenlik sınırlarını söyleyen başlıklar — ASVS V3.
 *
 * Bunlar sunucunun tarayıcıya verdiği talimatlardır ve bir saldırı olduktan
 * SONRA değil, olmadan önce iş görür. En kritik olanı CSP'dir: yayınlanan
 * menü sayfası restoranın kendi yazdığı metni gösterir; oraya bir gün script
 * sızarsa, CSP onun çalışmasını engelleyen son settir.
 *
 * CSP nonce tabanlıdır. `'unsafe-inline'` KULLANILMAZ: onu eklemek, CSP'nin
 * XSS'e karşı koruduğu tek şeyi geri vermek olurdu. Bunun bedeli, her satır
 * içi script/style etiketinin nonce taşıma zorunluluğudur — bu bilinçli bir
 * takastır.
 */
final class SecurityHeaders
{
    public const NONCE_ATTRIBUTE = 'csp-nonce';

    public function __construct(private readonly UrlPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(24);
        $request->attributes->set(self::NONCE_ATTRIBUTE, $nonce);
        view()->share('cspNonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        foreach ($this->headers($nonce, $request) as $name => $value) {
            // Var olan bir başlığı ezmeyiz: bir controller kendi kararını
            // vermişse (örneğin bir dosya indirmesi için farklı bir
            // `Content-Disposition` politikası) onu bilerek vermiştir.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /** @return array<string, string> */
    private function headers(string $nonce, Request $request): array
    {
        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),
            // MIME sniffing, bir metin dosyasını script gibi çalıştırabilir.
            'X-Content-Type-Options' => 'nosniff',
            // Referer, QR ile açılan bir menüde workspace/menü kimliği taşır;
            // dış siteye tam URL göndermek gereksiz bir sızıntıdır.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Eski tarayıcılar için `frame-ancestors`'ın karşılığı.
            'X-Frame-Options' => 'DENY',
            // İstemediğimiz güçlü API'leri baştan kapatırız.
            'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // Yönetim ve kimlik yüzeyleri arama sonuçlarında görünmemeli.
        //
        // `robots.txt` bunu TEK BAŞINA çözmez: taramayı engeller, ama başka
        // bir yerden link verilmiş bir adres taranmadan da indekslenebilir.
        // Bu başlık ise "sonuçlarda gösterme" der ve botun sayfayı
        // çekebilmesini gerektirir — bu yüzden ikisi birlikte kullanılır ve
        // aynı yol robots.txt'te Disallow EDİLMEZ.
        if ($this->policy->isNoIndexPath($request->getPathInfo())) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
        }

        // HSTS yalnız HTTPS üzerinden anlamlıdır ve HTTP üzerinden gönderilirse
        // yok sayılır; ayrıca yerel geliştirmede tarayıcıyı kilitlememek için
        // yalnız güvenli bağlantıda verilir.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        // Ölçüm araçları kapalıyken bu dizi tamamen boştur ve CSP aşağıdaki
        // sıkı halinde kalır. Açıkken YALNIZ açılan aracın adresleri girer.
        $analytics = AnalyticsConfiguration::fromConfig();

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
            // `strict-dynamic`: nonce taşıyan script'in YÜKLEDİĞİ script'ler
            // de güvenilir sayılır. Google Tag Manager tam olarak böyle
            // çalışır — GA4/Metrica/Hotjar etiketlerini kendisi enjekte eder.
            // Bu yüzden GTM için host beyaz listesine gerek yoktur; onu
            // yükleyen satır zaten nonce taşır.
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",
            "style-src 'self' 'nonce-{$nonce}'",
            // `style` ÖZNİTELİĞİ ayrı tutulur ve bilinçli olarak serbesttir.
            // React'te `style={{ gap: 'var(--space-fluid-md)' }}` yazan her
            // bileşen bir style özniteliği üretir; bunları yasaklamak
            // arayüzü kırardı ve ekip kaçınılmaz olarak politikayı toptan
            // gevşetirdi. Bir style özniteliği script çalıştıramaz; kalan
            // risk (CSS ile veri çıkarımı) kabul edilmiş ve kaydedilmiştir
            // (security/OWASP-ASVS-BASELINE.md V3).
            "style-src-attr 'unsafe-inline'",
        ];

        // Ölçüm izinleri. `connect-src`/`img-src` olmadan GTM yüklenir ama
        // hiçbir ölçüm SUNUCUYA ULAŞAMAZ: sayfa sorunsuz görünür, raporlar
        // boş kalır. Sessizce veri kaybetmemek için izinler açılan araçtan
        // türetilir.
        //
        // `script-src-elem` bilerek YOK: `strict-dynamic` altında bir host
        // listesi yazmak, nonce'un sağladığı güveni host güvenine indirger.
        $directives = $this->withAnalyticsSources($directives, $analytics, [
            'connect-src',
            'img-src',
            'font-src',
            'frame-src',
        ]);

        // Vite geliştirme sunucusu kendi HMR bağlantısını açar; bu izin
        // yalnız yerel geliştirmede verilir ve üretime asla sızmaz.
        if (app()->environment('local') && Vite::isRunningHot()) {
            $directives[] = 'script-src-elem '.Vite::asset('').' \'self\' \'nonce-'.$nonce.'\'';
            $directives[] = "connect-src 'self' ws: wss: http://localhost:* http://127.0.0.1:*";
        }

        return implode('; ', $directives);
    }

    /**
     * Var olan bir yönergeye adres ekler; yönerge tanımlı değilse ekler.
     *
     * `frame-src` bugün CSP'de yoktur ve `default-src 'self'`e düşer; Metrica
     * Webvisor açıkken bir iframe gerekir, dolayısıyla o durumda yönerge
     * yaratılır. Aksi halde eklenmez ve varsayılan sıkılık korunur.
     *
     * @param  list<string>  $directives
     * @param  list<string>  $names
     * @return list<string>
     */
    private function withAnalyticsSources(
        array $directives,
        AnalyticsConfiguration $analytics,
        array $names,
    ): array {
        foreach ($names as $name) {
            $sources = $analytics->cspSourcesFor($name);

            if ($sources === []) {
                continue;
            }

            $appended = false;

            foreach ($directives as $index => $directive) {
                if (str_starts_with($directive, $name.' ')) {
                    $directives[$index] = $directive.' '.implode(' ', $sources);
                    $appended = true;

                    break;
                }
            }

            if (! $appended) {
                $directives[] = $name." 'self' ".implode(' ', $sources);
            }
        }

        return array_values($directives);
    }
}
