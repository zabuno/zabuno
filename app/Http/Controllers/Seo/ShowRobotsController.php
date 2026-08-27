<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Domain\Url\UrlPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * `robots.txt` politikadan üretilir, elle yazılmaz.
 *
 * Statik bir dosya olarak durduğunda kaçınılmaz olarak gerçekle ayrışır: yeni
 * bir yönetim yolu eklenir, dosyaya yazılmaz ve o yol taranmaya açık kalır.
 * Burada liste `config/url-policy.php`'den gelir, yani tek kaynak.
 *
 * Kritik ayrım: bu dosya TARAMAYI yönetir, indekslemeyi değil. Bu yüzden
 * `Disallow` listesi `noindex` listesinin aynısı DEĞİLDİR:
 *
 * - Kimlik korumalı yüzeyler `Disallow` edilir; bot zaten içeriği göremez,
 *   taramak yalnız bütçe harcar.
 * - QR çözümleyici `Disallow` EDİLMEZ; edilseydi bot sayfayı çekemez ve
 *   `X-Robots-Tag: noindex` başlığını hiç okuyamazdı. Taranmasına izin verip
 *   "gösterme" demek, hiç taratmamaktan daha güvenilirdir (`docs/38` §11).
 */
final class ShowRobotsController extends Controller
{
    public function __construct(private readonly UrlPolicy $policy) {}

    public function __invoke(): Response
    {
        $lines = [
            '# Bu dosya üretilir: config/url-policy.php + docs/38-URL-POLICY.md',
            'User-agent: *',
            '',
            '# Kimlik korumalı yüzeyler. Bot zaten içeriği göremez; taramak',
            '# yalnız bütçe harcar.',
        ];

        foreach ($this->policy->disallowPrefixes() as $prefix) {
            $lines[] = 'Disallow: /'.$prefix.'/';
        }

        $lines[] = '';
        $lines[] = '# Menüler buradan keşfedilir; iç bağlantıları yoktur.';
        $lines[] = 'Sitemap: '.url('/sitemap.xml');
        $lines[] = '';
        $lines[] = '# QR çözümleyici bilerek taranabilir bırakılır: engellenirse';
        $lines[] = '# bot "noindex" başlığını hiç okuyamaz ve adres yine de';
        $lines[] = '# indekslenebilir.';
        $lines[] = 'Allow: /q/';
        $lines[] = '';
        $lines[] = '# Yayınlanan menüler taranabilir.';
        $lines[] = 'Allow: /menu/';

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
