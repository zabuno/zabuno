<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `docs/100` §2 — masterpage sözleşmesi (MP-01…MP-06).
 *
 * Kullanıcı yolculuğu: Adana'dan bir kebapçı telefonunda `zabuno.com`'u
 * açar; üstte Fiyat ve Yardım'ı Türkçe görür, altta İletişim'i bulur; hangi
 * sayfaya giderse gitsin aynı üst ve alt çubuk; hiçbir bağlantı ölü değil;
 * hiçbir yerde "16/16 modules registered" gibi anlamadığı bir satır yok.
 */
final class PublicMasterpageContractTest extends TestCase
{
    /** @return list<array{0:string}> */
    public static function publicPaths(): array
    {
        return [['/'], ['/pricing'], ['/help'], ['/contact'], ['/terms'], ['/privacy'], ['/kvkk']];
    }

    private function html(string $path, array $headers = []): string
    {
        return (string) $this->withHeaders($headers)->get($path)->assertOk()->getContent();
    }

    private function extract(string $html, string $tag): string
    {
        preg_match("#<{$tag}\b.*?</{$tag}>#s", $html, $m);

        return $m[0] ?? '';
    }

    // --- MP-01 / MP-02 ---------------------------------------------------------

    #[DataProvider('publicPaths')]
    public function test_every_public_page_carries_exactly_one_header_and_one_footer(string $path): void
    {
        $html = $this->html($path);

        self::assertSame(1, preg_match_all('#<header\b#', $html), "MP-01: [{$path}] tek header olmalı.");
        self::assertSame(1, preg_match_all('#<footer\b#', $html), "MP-01: [{$path}] tek footer olmalı.");
        self::assertStringContainsString('aria-label="Primary"', $this->extract($html, 'header'));
        self::assertStringContainsString('aria-label="Legal"', $this->extract($html, 'footer'));
        self::assertStringContainsString('aria-label="Product"', $this->extract($html, 'footer'));
    }

    public function test_header_and_footer_are_the_same_on_every_page(): void
    {
        $normalise = static fn (string $fragment): string => preg_replace('#href="/?\#[a-z-]+"#', 'href="ANCHOR"', $fragment);

        $reference = null;

        foreach (self::publicPaths() as [$path]) {
            $html = $this->html($path);
            $chrome = $normalise($this->extract($html, 'header')).$normalise($this->extract($html, 'footer'));

            if ($reference === null) {
                $reference = $chrome;

                continue;
            }

            self::assertSame($reference, $chrome, "MP-02: [{$path}] header/footer ana sayfadan farklı — masterpage tek kaynak olmalı.");
        }
    }

    // --- MP-03 -------------------------------------------------------------------

    public function test_navigation_labels_come_from_the_catalogue_so_a_turkish_visitor_reads_turkish(): void
    {
        $english = $this->extract($this->html('/pricing'), 'header');
        $turkish = $this->extract($this->html('/pricing', ['Accept-Language' => 'tr']), 'header');

        self::assertStringContainsString('>Help<', $english);
        self::assertStringContainsString('>Yardım<', $turkish, 'MP-03: Türkçe tarayıcı gezintiyi Türkçe okumalı.');
        self::assertStringContainsString('>Fiyat<', $turkish);
    }

    // --- MP-04 -------------------------------------------------------------------

    #[DataProvider('publicPaths')]
    public function test_the_engineering_line_is_not_visible_to_a_visitor(string $path): void
    {
        $html = $this->html($path);

        self::assertStringContainsString('<meta name="zabuno-build" content="', $html, 'Kayıt sözleşmesi meta olarak kalır.');
        self::assertDoesNotMatchRegularExpression(
            '#>[^<]*modules registered[^<]*<#',
            $html,
            "MP-04: [{$path}] mühendislik satırı ziyaretçiye görünür metin olmamalı."
        );
    }

    // --- MP-05 -------------------------------------------------------------------

    #[DataProvider('publicPaths')]
    public function test_no_public_page_is_breakpoint_gated(string $path): void
    {
        preg_match_all('/class="([^"]*)"/', $this->html($path), $matches);

        foreach ($matches[1] as $classList) {
            self::assertDoesNotMatchRegularExpression('/(^|\s)(sm|md|lg|xl|2xl):/', $classList, "MP-05: [{$path}] kırılma noktası: {$classList}");
        }
    }

    // --- MP-06 -------------------------------------------------------------------

    public function test_every_link_in_header_and_footer_resolves(): void
    {
        $html = $this->html('/pricing');
        preg_match_all('#href="(/[a-z/-]*)"#', $this->extract($html, 'header').$this->extract($html, 'footer'), $m);
        $paths = array_values(array_unique($m[1]));

        self::assertContains('/pricing', $paths);
        self::assertContains('/help', $paths);
        self::assertContains('/contact', $paths);

        foreach ($paths as $target) {
            $status = $this->get($target)->getStatusCode();
            self::assertContains($status, [200, 302], "MP-06: gezintideki [{$target}] {$status} döndü — ölü bağlantı yasak (`docs/64` §4).");
        }
    }
}
