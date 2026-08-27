<?php

declare(strict_types=1);

namespace Tests\Feature\Url;

use App\Domain\Url\UrlPolicy;
use App\Http\Middleware\CanonicalUrl;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Host güveni ve QR çözümleyici hız sınırı.
 *
 * Requirement ID'leri: URL-HOST-TRUST-24, URL-HOST-DEFAULT-25,
 * URL-QR-THROTTLE-26.
 */
final class HostAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    // --- URL-HOST-TRUST-24 -------------------------------------------------

    public function test_the_application_declares_which_hosts_it_answers_to(): void
    {
        // Host başlığı İSTEMCİDEN gelir. Ona güvenmek, ürettiğimiz kanonik ve
        // imzalı adreslerin saldırganın alan adına kaymasına izin verir —
        // doğrulama e-postasındaki bağlantı oraya giderse kullanıcı kimlik
        // bilgisini saldırgana yazar.
        //
        // Burada çerçevenin middleware'ini ÇAĞIRMIYORUZ: `TrustHosts`
        // süreç genelinde global durum kurar ve tek bir test bütün süiti
        // kırabilir (bu paketi yazarken tam olarak bu oldu — 16 test birden
        // 400 döndü). Bunun yerine kararı veren kendi saf işlevimizi
        // ölçüyoruz.
        $policy = $this->app->make(UrlPolicy::class);

        self::assertSame(
            ['menu.example.test'],
            $policy->resolvedTrustedHosts('https://menu.example.test'),
            'URL-HOST-TRUST-24: hiçbir host beyan edilmezse üretimde her Host başlığı kabul edilir.'
        );
    }

    // --- URL-HOST-DEFAULT-25 -----------------------------------------------

    public function test_an_explicit_list_wins_over_the_application_url(): void
    {
        $policy = new UrlPolicy(['trusted_hosts' => ['menu.zabuno.com', 'q.zabuno.com']]);

        self::assertSame(
            ['menu.zabuno.com', 'q.zabuno.com'],
            $policy->resolvedTrustedHosts('https://baska.example'),
        );
    }

    public function test_a_missing_application_url_declares_nothing_rather_than_guessing(): void
    {
        // Tahmin edilmiş bir host, yanlış host'a güvenmekten farksızdır.
        self::assertSame([], (new UrlPolicy([]))->resolvedTrustedHosts(''));
        self::assertSame([], (new UrlPolicy([]))->resolvedTrustedHosts(null));
    }

    public function test_an_untrusted_host_is_refused_outside_local_and_testing(): void
    {
        // Denetim yerel ve testte kapalıdır (her geliştirici makinesi ayrı
        // yapılandırma istemesin diye). Bu yüzden middleware'i doğrudan,
        // üretim gibi davranan bir ortamda çalıştırıyoruz.
        $this->app['env'] = 'production';

        try {
            config(['app.url' => 'https://menu.zabuno.test']);

            $request = Request::create('https://saldirgan.example/terms', 'GET');
            $response = $this->app->make(CanonicalUrl::class)->handle($request, static fn (): Response => new Response('ok'));

            self::assertSame(400, $response->getStatusCode(), 'URL-HOST-TRUST-24: yabancı Host kabul edildi.');

            $trusted = Request::create('https://menu.zabuno.test/terms', 'GET');
            $ok = $this->app->make(CanonicalUrl::class)->handle($trusted, static fn (): Response => new Response('ok'));

            self::assertSame(200, $ok->getStatusCode());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    // --- URL-QR-THROTTLE-26 ------------------------------------------------

    public function test_the_qr_resolver_is_rate_limited_against_token_scanning(): void
    {
        RateLimiter::clear('qr-resolve');

        $unknown = str_repeat('z', 43);
        $kernel = $this->app->make(Kernel::class);
        $statuses = [];

        // Sınır cömerttir: bir masadaki misafirlerin arka arkaya taraması
        // engellenmemeli. Ama bir tarayıcı için değersiz olmalı.
        for ($attempt = 0; $attempt < 70; $attempt++) {
            $request = Request::create('/q/'.$unknown, 'GET');
            $request->server->set('REMOTE_ADDR', '203.0.113.7');
            $statuses[] = $kernel->handle($request)->getStatusCode();
        }

        self::assertContains(429, $statuses, 'URL-QR-THROTTLE-26: QR çözümleyici sınırsız taranabiliyor.');
        self::assertSame(404, $statuses[0], 'URL-QR-THROTTLE-26: ilk istekler normal cevabını vermeli.');
    }
}
