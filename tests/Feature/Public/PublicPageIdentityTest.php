<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FF-31 RED — her genel sayfanın bir adı ve tek bir başlığı vardır
 * (`docs/89`).
 *
 * `docs/88` fiyat ve iletişim sayfalarını açtı; metni kataloğa taşırken
 * `@section('title')` düştü. Sonuç canlıda görüldü: sekmede ve paylaşım
 * önizlemesinde **"— Zabuno"** yazıyordu. Bu sayfalar tam olarak
 * PAYLAŞILMAK için var — fiyatı biri arkadaşına gönderir.
 *
 * Ayrıca `/pricing` sayfasında "Pricing" İKİ KEZ görünüyordu: sayfanın
 * `<h1>`'i ve bölümün `<h2>`'si. Aynı kelimeyi üst üste iki başlıkta
 * göstermek, ekran okuyucuda da iki ayrı bölüm varmış gibi okunur.
 *
 * Requirement IDs: PUBLIC-PAGE-TITLE-01, PUBLIC-PAGE-SINGLE-H1-01,
 * PUBLIC-PAGE-DESCRIPTION-01.
 */
final class PublicPageIdentityTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{0:string}> */
    public static function publicPaths(): array
    {
        return [['/'], ['/pricing'], ['/contact'], ['/terms'], ['/privacy'], ['/kvkk']];
    }

    // --- PUBLIC-PAGE-TITLE-01 ---------------------------------------------

    #[DataProvider('publicPaths')]
    public function test_every_public_page_has_a_name_of_its_own(string $path): void
    {
        $html = $this->get($path)->getContent();

        preg_match('#<title>(.*?)</title>#s', $html, $title);

        self::assertNotEmpty($title[1] ?? '', "[{$path}] başlıksız.");

        // "— Zabuno" tek başına bir ad DEĞİLDİR: paylaşılan bağlantı
        // hangi sayfa olduğunu söylemez.
        self::assertNotSame(
            '— Zabuno',
            trim($title[1]),
            "PUBLIC-PAGE-TITLE-01: [{$path}] sekmede yalnız marka adını gösteriyor."
        );

        self::assertMatchesRegularExpression(
            '#<meta property="og:title" content="[^"]+">#',
            $html,
            "PUBLIC-PAGE-DESCRIPTION-01: [{$path}] paylaşım başlığı boş."
        );
    }

    #[DataProvider('publicPaths')]
    public function test_every_public_page_describes_itself(string $path): void
    {
        $html = $this->get($path)->getContent();

        preg_match('#<meta name="description" content="([^"]*)">#', $html, $description);

        self::assertNotEmpty(
            trim($description[1] ?? ''),
            "PUBLIC-PAGE-DESCRIPTION-01: [{$path}] açıklamasız — arama sonucunda ve paylaşımda boş görünür."
        );
    }

    // --- PUBLIC-PAGE-SINGLE-H1-01 -----------------------------------------

    #[DataProvider('publicPaths')]
    public function test_a_page_has_exactly_one_top_level_heading(string $path): void
    {
        $html = $this->get($path)->getContent();

        self::assertSame(
            1,
            preg_match_all('#<h1\b#', $html),
            "PUBLIC-PAGE-SINGLE-H1-01: [{$path}] birden çok (ya da hiç) `h1` taşıyor."
        );
    }

    public function test_the_pricing_page_does_not_say_pricing_twice(): void
    {
        $html = $this->get('/pricing')->getContent();

        // Aynı kelimeyi üst üste iki başlıkta göstermek, ekran okuyucuda
        // iki ayrı bölüm varmış gibi okunur.
        self::assertSame(
            1,
            preg_match_all('#<h[12][^>]*>\s*Pricing\s*<#u', $html),
            'PUBLIC-PAGE-SINGLE-H1-01: "Pricing" başlığı bir kez görünmeli.'
        );
    }

    public function test_a_page_introduces_itself_after_its_title_not_before(): void
    {
        $html = $this->get('/pricing')->getContent();

        /*
            Giriş cümlesi BAŞLIĞIN ALTINDA durur.

            Sayfa başlığı bölüme devredilince cümle yukarıda kalmıştı:
            okuyucu neyin açıklamasını okuduğunu ancak sonraki satırda
            öğreniyordu (`docs/91`).
        */
        // İddia GÖVDEYE bakar: aynı cümle `<head>` içindeki `meta`
        // açıklamasında da geçiyor ve ham konum araması onu bulur.
        preg_match('#<main\b.*?</main>#s', $html, $body);

        self::assertNotEmpty($body, 'Sayfanın bir gövdesi olmalı.');

        $heading = strpos($body[0], '<h1');
        $lead = strpos($body[0], 'What a restaurant pays');

        self::assertIsInt($heading);
        self::assertIsInt($lead);
        self::assertLessThan($lead, $heading, 'Başlık, açıklamasından önce gelmeli.');
    }
}
