<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Tests\TestCase;

/**
 * YAZI TİPİ KENDİ KAYNAĞIMIZDAN GELİR — sayfanın kendisi üzerinden ölçülür.
 *
 * `resources/js/design-system/font-hosting.test.ts` kaynak dosyaları ölçer:
 * `@font-face` var mı, dosya diskte mi, kaç bayt. Bu test ise ZİYARETÇİNİN
 * ALDIĞI BELGEYİ ölçer — çünkü ikisi ayrışabilir ve ayrıştığında kaynak
 * tarafındaki testler hâlâ yeşil kalır.
 *
 * Ölçülen iki şey var:
 *
 * 1. Sayfa, ilk boyamada gereken yazı tipini `preload` ediyor mu ve YALNIZ
 *    onu mu? Her şeyi preload etmek, tarayıcıya "hepsi en önemli" demektir;
 *    o da "hiçbiri" demekle aynı kapıya çıkar.
 * 2. Sayfa hiçbir yazı tipini üçüncü taraftan istiyor mu? Üretimdeki CSP
 *    `font-src 'self' data:` diyor: böyle bir istek zaten ENGELLENİR, yani
 *    sayfa açılır, yazı tipi gelmez ve bu SESSİZCE olur.
 *
 * Requirement ID'leri: PERF-FONT-PRELOAD-01, PERF-FONT-FIRST-PARTY-02.
 */
final class FontDeliveryTest extends TestCase
{
    /**
     * Ölçülen yüzeyler: `app.css` yükleyen, kimlik doğrulaması istemeyen
     * sayfalar. Panelin kendisi de aynı kabuğu kullanır.
     *
     * @return list<string>
     */
    private function surfaces(): array
    {
        return ['/', '/login'];
    }

    // --- PERF-FONT-PRELOAD-01 ---------------------------------------------

    public function test_each_surface_preloads_exactly_one_font_file(): void
    {
        foreach ($this->surfaces() as $path) {
            $html = (string) $this->get($path)->getContent();

            preg_match_all('/<link[^>]*rel="preload"[^>]*as="font"[^>]*>/i', $html, $matches);

            self::assertCount(
                1,
                $matches[0],
                "PERF-FONT-PRELOAD-01: {$path} adresinde tam olarak BİR yazı tipi preload ".
                'edilmeli. Sıfır ise ilk boyama yedek yazı tipiyle çizilir ve metin yeniden '.
                'akar; birden fazla ise preload sırayı bildirmeyi bırakır.'
            );
        }
    }

    public function test_the_preloaded_font_is_a_woff2_and_carries_crossorigin(): void
    {
        $html = (string) $this->get('/')->getContent();

        preg_match('/<link[^>]*rel="preload"[^>]*as="font"[^>]*>/i', $html, $match);

        self::assertNotEmpty($match, 'PERF-FONT-PRELOAD-01: preload bağlantısı yok.');

        $link = $match[0];

        self::assertMatchesRegularExpression(
            '/href="[^"]+\.woff2[^"]*"/i',
            $link,
            'PERF-FONT-PRELOAD-01: woff2 dışındaki bir biçim, aynı harfler için iki kat '.
            'bayt demektir.'
        );

        // `crossorigin` OLMADAN tarayıcı yazı tipini İKİ KEZ indirir: preload
        // isteği anonim değildir, gerçek `@font-face` isteği ise her zaman
        // anonimdir ve ikisi ayrı önbellek girdisine düşer. Yani eksik bir
        // öznitelik, preload'u bir hızlandırmadan bir yavaşlatmaya çevirir.
        self::assertStringContainsString(
            'crossorigin',
            $link,
            'PERF-FONT-PRELOAD-01: crossorigin taşımayan bir yazı tipi preload\'u, dosyayı '.
            'iki kez indirtir.'
        );
    }

    // --- PERF-FONT-FIRST-PARTY-02 -----------------------------------------

    public function test_no_surface_asks_a_third_party_for_a_font(): void
    {
        foreach ($this->surfaces() as $path) {
            $html = (string) $this->get($path)->getContent();

            foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'use.typekit.net'] as $host) {
                self::assertStringNotContainsString(
                    $host,
                    $html,
                    "PERF-FONT-FIRST-PARTY-02: {$path} {$host} adresine istek çıkarıyor. ".
                    'CSP bunu engeller (yazı tipi hiç gelmez) ve engellemeseydi bile '.
                    'ziyaretçinin IP adresini, ona sormadan, başka bir şirkete verirdi.'
                );
            }
        }
    }
}
