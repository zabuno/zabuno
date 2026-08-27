<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SAAS-DOMAIN — aynı yazılım, birden çok alan adı ve sunucu.
 *
 * Bu bir SaaS: zabuno.com ve e-menum.net aynı kurulumdan servis edilecek,
 * ve yarın başka bir müşteri başka bir alan adında, başka bir sunucuda
 * çalıştırabilmeli. Yazılımın hiçbir yerinde alan adı SABİT OLMAMALI.
 *
 * Buradaki testler o iddiayı kanıtlar. İddianın kolayca yanlış olabileceği
 * iki nokta var ve ikisi de gerçek:
 *
 * 1. Mutlak adresler `APP_URL`'den üretilirse, e-menum.net'ten gelen
 *    ziyaretçi zabuno.com bağlantıları görür.
 * 2. Ters vekile güvenilmezse uygulama isteğin HTTPS olduğunu bilemez;
 *    ürettiği adresler `http://` çıkar ve şema zorlaması açıkken sonsuz
 *    yönlendirme oluşur. Vekil güveni ilk kurulumda YAPILANDIRILMAMIŞTI.
 */
final class MultiDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // İki alan adı da güvenilir; hiçbiri kanonik değil.
        config([
            'url-policy.trusted_hosts' => ['zabuno.com', 'e-menum.net'],
            'url-policy.canonical_host' => null,
            'url-policy.enforce_host' => false,
            'app.url' => 'https://zabuno.com',
        ]);
    }

    public function test_each_domain_is_served_on_its_own_address(): void
    {
        foreach (['zabuno.com', 'e-menum.net'] as $host) {
            $response = $this->get('http://'.$host.'/');

            $response->assertOk();
        }
    }

    public function test_the_sitemap_advertises_the_host_it_was_asked_on(): void
    {
        // `APP_URL` zabuno.com; ama e-menum.net'ten sorulduğunda kendi
        // adreslerini vermeli. Aksi hâlde ikinci alan adı, birincinin
        // adreslerini yayan bir kabuk olur ve arama motoru öyle indeksler.
        $sitemap = $this->get('http://e-menum.net/sitemap.xml');

        $sitemap->assertOk();
        $body = $sitemap->getContent();

        self::assertStringNotContainsString(
            'zabuno.com',
            (string) $body,
            'SAAS-DOMAIN: sitemap başka bir alan adının adreslerini yayıyor.'
        );
    }

    public function test_robots_points_at_the_sitemap_on_the_same_host(): void
    {
        $robots = $this->get('http://e-menum.net/robots.txt');

        $robots->assertOk();
        self::assertStringNotContainsString('zabuno.com', (string) $robots->getContent());
    }

    public function test_a_forwarded_https_request_is_not_treated_as_insecure(): void
    {
        // Vekil TLS'i sonlandırıyor; uygulamaya düz HTTP olarak geliyor.
        // Başlığa güvenilmezse `isSecure()` yanlış döner ve üretilen her
        // adres `http://` olur.
        $response = $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'e-menum.net',
            'REMOTE_ADDR' => '172.18.0.5',
        ])->get('http://e-menum.net/sitemap.xml');

        $response->assertOk();

        self::assertStringContainsString(
            'https://e-menum.net',
            (string) $response->getContent(),
            'SAAS-DOMAIN: vekilin ilettiği şema yok sayılıyor; adresler http olarak üretiliyor.'
        );
    }

    public function test_no_domain_is_hardcoded_anywhere_in_the_application(): void
    {
        $roots = ['app', 'config', 'routes', 'resources/views', 'docker'];
        $found = [];

        foreach ($roots as $root) {
            $path = base_path($root);

            if (! is_dir($path)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $files */
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                // Yorumlar hariç: gerekçe yazarken alan adı anmak serbest.
                $withoutComments = (string) preg_replace('~^\s*(//|#|\*|/\*).*$~m', '', $contents);

                if (preg_match('/\b(zabuno|e-menum)\.(com|net)\b/', $withoutComments) === 1) {
                    $found[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        self::assertSame(
            [],
            $found,
            'SAAS-DOMAIN: alan adı koda gömülmüş. Bu yazılım başka alan adlarında da çalışmalı.'
        );
    }
}
