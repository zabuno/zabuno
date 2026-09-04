<?php

declare(strict_types=1);

namespace App\Domain\Media;

use DOMAttr;
use DOMDocument;
use DOMElement;

/**
 * SVG TEMİZLEYİCİSİ — sahibin 2026-09-05 kararının güvenlik yarısı.
 *
 * NEDEN VAR. Sahip "SVG'yi şimdi aç" dedi (`docs/108` §6.2). Depo o güne
 * kadar SVG'yi reddediyordu ve gerekçe hâlâ geçerlidir: **SVG bir görsel
 * değil, bir BELGEDİR.** Bir JPEG'in içinden kod çalışmaz; bir SVG'nin
 * içinden çalışır. `<script>`, `onload=`, `javascript:` bağlantısı,
 * `<foreignObject>` içinde tam bir HTML sayfası, harici `<use>` ile
 * başkasının belgesini içeri çekme, `@import` ile dış stil dosyası. Menü
 * sayfaları HERKESE AÇIK olduğundan, temizlenmemiş bir SVG'yi kabul etmek
 * misafirin telefonunda çalışacak kodu depoya koymaktır (stored XSS).
 *
 * Kararı geciktirmemenin yolu, kabulü temizleyiciyle AYNI pakette açmaktı.
 *
 * NEDEN ALLOWLIST. Denylist ("şunları sil") her yeni saldırı biçiminde
 * geriden gelir; bir gün duymadığımız bir öge çıkar ve listede olmadığı
 * için geçer. Allowlist ters yönde yanılır: tanımadığı şeyi atar. Bir
 * logonun kaybolması, bir menüde çalışan betikten iyidir.
 *
 * NEDEN SAF. Ne çerçeve, ne dosya sistemi, ne ağ. Girdi bir dize, çıktı
 * bir karar (ADR-L02). Böylece bu sınıf HTTP olmadan, veritabanı olmadan,
 * saldırı gövdeleriyle doğrudan sınanabilir.
 *
 * ÇIKTI YALNIZ DARALIR. Temizleyici asla içerik EKLEMEZ. Ekleyen bir
 * temizleyicinin çıktısında neyin nereden geldiği artık bilinemez ve
 * denetlenemez.
 */
final class SvgSanitizer
{
    /**
     * Düğüm tavanı — piksel tavanının (`media-slots.limits.max_megapixels`)
     * SVG karşılığı. Birkaç kilobayt metin milyonlarca düğüm tarif
     * edebilir; temizleyicinin kendisi bir hizmet-dışı bırakma aracına
     * dönüşmemeli.
     */
    public const MAX_ELEMENTS = 20000;

    private const SVG_NS = 'http://www.w3.org/2000/svg';

    private const XHTML_NS = 'http://www.w3.org/1999/xhtml';

    /**
     * ÇALIŞABİLİR ya da DIŞARI ÇIKAN ögeler. Bulunmaları bir kaza değil,
     * bir saldırı imzasıdır: hiçbir çizim programı logoya `<script>`
     * koymaz.
     */
    private const HOSTILE_ELEMENTS = [
        'script', 'foreignobject', 'iframe', 'embed', 'object', 'handler',
        // Animasyon ögeleri öznitelik DEĞİŞTİRİR ve olay tetikler; bir
        // `<animate attributeName="href" values="javascript:…">` tam olarak
        // budur.
        'animate', 'animatetransform', 'animatemotion', 'animatecolor', 'set',
        'audio', 'video', 'canvas', 'link', 'meta', 'base',
    ];

    /**
     * Çizim için gereken SVG alt kümesi. Burada olmayan her öge SESSİZCE
     * atılır (`stripped`) — editör metadata'sı, RDF, bilinmeyen uzantılar.
     *
     * `<image>` bilerek YOK: dış kaynağa (ya da `data:`ya) bağlanır ve
     * bu temizleyicinin bağlantı kuralı yalnız `#` iç referansına izin
     * verir. Rasterli bir SVG kabul edilmez; sahip rasterini PNG olarak
     * yükler — ki zaten doğru olan da odur.
     */
    private const ALLOWED_ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'a', 'title', 'desc', 'metadata', 'style', 'switch',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textpath',
        'marker', 'pattern', 'mask', 'clippath',
        'lineargradient', 'radialgradient', 'stop',
        'filter', 'fegaussianblur', 'feoffset', 'feblend', 'fecolormatrix', 'fecomposite',
        'feflood', 'femerge', 'femergenode', 'femorphology', 'fetile', 'feturbulence',
        'fedropshadow', 'fecomponenttransfer', 'fefuncr', 'fefuncg', 'fefuncb', 'fefunca',
    ];

    /**
     * İzin verilen öznitelikler: geometri, sunum ve erişilebilirlik.
     * Listede olmayan her öznitelik atılır.
     */
    private const ALLOWED_ATTRIBUTES = [
        'id', 'class', 'style', 'transform', 'version', 'viewbox', 'preserveaspectratio',
        'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'fx', 'fy', 'd', 'points', 'dx', 'dy', 'rotate', 'textlength', 'lengthadjust',
        'href', 'xlink:href', 'xlink:title',
        'gradientunits', 'gradienttransform', 'spreadmethod', 'offset',
        'patternunits', 'patterncontentunits', 'patterntransform',
        'maskunits', 'maskcontentunits', 'clippathunits',
        'markerwidth', 'markerheight', 'markerunits', 'refx', 'refy', 'orient',
        'filterunits', 'primitiveunits', 'result', 'in', 'in2', 'stddeviation', 'values',
        'type', 'mode', 'operator', 'k1', 'k2', 'k3', 'k4', 'scale', 'radius',
        'basefrequency', 'numoctaves', 'seed', 'tablevalues', 'slope', 'intercept',
        'amplitude', 'exponent', 'flood-color', 'flood-opacity', 'lighting-color',
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity',
        'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset',
        'stroke-miterlimit', 'opacity', 'color', 'display', 'visibility', 'overflow',
        'clip-path', 'clip-rule', 'mask', 'filter', 'enable-background',
        'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant',
        'font-stretch', 'letter-spacing', 'word-spacing', 'text-anchor', 'text-decoration',
        'dominant-baseline', 'alignment-baseline', 'baseline-shift', 'writing-mode',
        'direction', 'unicode-bidi', 'shape-rendering', 'image-rendering', 'text-rendering',
        'color-interpolation', 'color-interpolation-filters', 'stop-color', 'stop-opacity',
        'vector-effect', 'paint-order', 'marker-start', 'marker-mid', 'marker-end',
        'systemlanguage', 'requiredfeatures', 'requiredextensions',
        'xml:space', 'xml:lang', 'lang', 'role', 'focusable',
        'aria-label', 'aria-labelledby', 'aria-hidden', 'aria-describedby',
    ];

    public function sanitize(string $body): SvgSanitizationResult
    {
        $source = trim($this->withoutByteOrderMark($body));

        if ($source === '') {
            return SvgSanitizationResult::unreadable('SVG dosyası boş görünüyor. Lütfen yeniden yükleyin.');
        }

        /*
            ENTITY, AYRIŞTIRMADAN ÖNCE DURDURULUR.

            İki saldırı buradan gelir ve ikisi de dosya açılmadan önce
            karara bağlanmalıdır:

              - XXE: `<!ENTITY xxe SYSTEM "file:///etc/passwd">` sunucunun
                kendi dosyasını gövdeye yazdırır.
              - Milyar gülüş: iç içe varlıklar birkaç kilobaytı gigabaytlara
                açar; ayrıştırıcı çalışmaya başladığında iş işten geçmiştir.

            Ayrıştırıcı ayarına güvenmek yetmez: `LIBXML_NOENT`in yokluğu
            sürüme ve derlemeye bağlı bir garantidir. Metinde `<!ENTITY`
            görmek ise sürümden bağımsızdır.
        */
        if (preg_match('/<!ENTITY/i', $source) === 1) {
            return SvgSanitizationResult::hostile(
                null,
                ['doctype-entity'],
                'Bu SVG, dosya sistemine ya da dış kaynağa uzanabilen bir XML varlığı (ENTITY) tanımlıyor '
                .'ve bu yüzden kabul edilmedi. Dosyayı çizim programınızdan "düz SVG" olarak yeniden verin.',
            );
        }

        // Varlık tanımı olmayan bir DOCTYPE zararsızdır ama gereksizdir
        // (eski Illustrator çıktıları taşır). Atılır, reddedilmez.
        $stripped = [];
        $withoutDoctype = (string) preg_replace('/<!DOCTYPE[^>]*>/i', '', $source, 1, $doctypeCount);

        if ($doctypeCount > 0) {
            $stripped[] = 'doctype';
        }

        $document = $this->parse($withoutDoctype);

        if ($document === null) {
            return SvgSanitizationResult::unreadable(
                'Bu dosya geçerli bir SVG olarak okunamadı. Çizim programınızdan yeniden dışa aktarıp deneyin.',
            );
        }

        $root = $document->documentElement;

        if ($root === null || strtolower($root->localName) !== 'svg' || $root->namespaceURI !== self::SVG_NS) {
            // Ad alanı da aranır: `<svg>` adını taşıyan ama SVG olmayan bir
            // belge, tarayıcıda SVG gibi davranmaz — ve biz onu SVG diye
            // servis edersek ne olacağını bilemeyiz. Fail-closed.
            return SvgSanitizationResult::unreadable(
                'Bu dosya bir SVG değil. Menü görselleri için SVG, PNG, JPEG veya WebP yükleyebilirsiniz.',
            );
        }

        if ($document->getElementsByTagName('*')->length > self::MAX_ELEMENTS) {
            return SvgSanitizationResult::unreadable(
                'Bu SVG aşırı karmaşık ('.self::MAX_ELEMENTS.' ögeden fazla) ve güvenle işlenemiyor. '
                .'Çizimi sadeleştirip yeniden deneyin.',
            );
        }

        $threats = [];

        $this->scrub($root, $threats, $stripped);

        $sanitized = (string) $document->saveXML($root);

        if ($threats !== []) {
            return SvgSanitizationResult::hostile(
                $sanitized,
                array_values(array_unique($threats)),
                $this->hostileReason(array_values(array_unique($threats))),
                array_values(array_unique($stripped)),
            );
        }

        return SvgSanitizationResult::safe($sanitized, array_values(array_unique($stripped)));
    }

    private function parse(string $xml): ?DOMDocument
    {
        $previous = libxml_use_internal_errors(true);

        $document = new DOMDocument;
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        // `LIBXML_NONET`: ayrıştırıcı hiçbir koşulda ağa çıkmaz.
        // `LIBXML_NOENT` BİLEREK YOK: varlık genişletmesi kapalı kalır.
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded === false ? null : $document;
    }

    /**
     * @param  list<string>  $threats
     * @param  list<string>  $stripped
     */
    private function scrub(DOMElement $element, array &$threats, array &$stripped): void
    {
        $this->scrubAttributes($element, $threats, $stripped);

        // Çocuklar önce diziye alınır: gezinirken düğüm silmek canlı
        // NodeList'i kaydırır ve bazı düğümler ATLANIR — tam da atlanan
        // düğümün saldırı olduğu durumda.
        $children = [];

        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $this->scrubChildElement($element, $child, $threats, $stripped);

                continue;
            }

            // Yorum ve işlem talimatı. Bir `xml-stylesheet` işlem talimatı
            // dış stil yükler; yorum ise yalnız gürültüdür. İkisi de
            // çizime katkı vermez, ikisi de gider.
            if ($child->nodeType === XML_COMMENT_NODE) {
                $stripped[] = 'comment';
                $element->removeChild($child);
            } elseif ($child->nodeType === XML_PI_NODE) {
                $threats[] = 'processing-instruction';
                $element->removeChild($child);
            }
        }
    }

    /**
     * @param  list<string>  $threats
     * @param  list<string>  $stripped
     */
    private function scrubChildElement(DOMElement $parent, DOMElement $child, array &$threats, array &$stripped): void
    {
        $name = strtolower($child->localName);

        if (in_array($name, self::HOSTILE_ELEMENTS, true)) {
            $threats[] = $name;
            $parent->removeChild($child);

            return;
        }

        // SVG ad alanı dışındaki her şey. XHTML özel olarak işaretlenir:
        // `<foreignObject>` kapatılsa bile SVG'nin içine HTML sokmanın
        // başka yolları olabilir ve bu bir kaza değildir.
        if ($child->namespaceURI !== null && $child->namespaceURI !== self::SVG_NS) {
            if ($child->namespaceURI === self::XHTML_NS) {
                $threats[] = 'foreign-markup';
            } else {
                $stripped[] = 'foreign-namespace';
            }

            $parent->removeChild($child);

            return;
        }

        if (! in_array($name, self::ALLOWED_ELEMENTS, true)) {
            $stripped[] = $name;
            $parent->removeChild($child);

            return;
        }

        if ($name === 'style') {
            if ($this->cssReachesOutside((string) $child->textContent)) {
                $threats[] = 'external-style';
                $parent->removeChild($child);

                return;
            }
        }

        $this->scrub($child, $threats, $stripped);
    }

    /**
     * @param  list<string>  $threats
     * @param  list<string>  $stripped
     */
    private function scrubAttributes(DOMElement $element, array &$threats, array &$stripped): void
    {
        $attributes = [];

        foreach ($element->attributes ?? [] as $attribute) {
            $attributes[] = $attribute;
        }

        foreach ($attributes as $attribute) {
            if (! $attribute instanceof DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->nodeName);
            $value = (string) $attribute->value;

            // Ad alanı bildirimleri korunur: onları atmak belgeyi SVG
            // olmaktan çıkarır ve tarayıcı çizimi hiç göstermez.
            if (str_starts_with($name, 'xmlns')) {
                continue;
            }

            // `on…` — betiği etiket olmadan çalıştırmanın yolu. Tek bir
            // `onload` bütün temizliği anlamsız kılar.
            if (str_starts_with($name, 'on')) {
                $threats[] = 'event-attribute';
                $this->removeAttribute($element, $attribute);

                continue;
            }

            if ($name === 'href' || $name === 'xlink:href') {
                // YALNIZ İÇ REFERANS. `#icon` belgenin kendi içini gösterir
                // ve dışarı çıkmaz. `javascript:` yürütür, `data:` gövdeyi
                // gizler, `http(s):` başkasının denetimindeki içeriği bizim
                // adresimizden gösterir.
                if (! str_starts_with(trim($value), '#') || trim($value) === '#') {
                    $threats[] = 'external-reference';
                    $this->removeAttribute($element, $attribute);
                }

                continue;
            }

            if (! in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                $stripped[] = 'attribute:'.$name;
                $this->removeAttribute($element, $attribute);

                continue;
            }

            // `style="fill:url(https://…)"` ya da `fill="url(https://…)"`:
            // sunum özniteliği de bir dış kaynak kanalıdır.
            if ($this->cssReachesOutside($value)) {
                $threats[] = 'external-style';
                $this->removeAttribute($element, $attribute);
            }
        }
    }

    private function removeAttribute(DOMElement $element, DOMAttr $attribute): void
    {
        if ($attribute->namespaceURI !== null) {
            $element->removeAttributeNS($attribute->namespaceURI, $attribute->localName);

            return;
        }

        $element->removeAttribute($attribute->nodeName);
    }

    /**
     * Bu CSS (ya da sunum özniteliği) belgenin dışına uzanıyor mu?
     *
     * `url(#gradient)` belgenin kendi tanımını gösterir ve kalır — onu da
     * kesmek, gradyanlı her logoyu bozardı. Diğer her `url(…)`, `@import`
     * ve `javascript:` gider.
     */
    private function cssReachesOutside(string $css): bool
    {
        $lower = strtolower($css);

        foreach (['@import', 'javascript:', 'expression(', '-moz-binding'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        if (preg_match_all('/url\(\s*[\'"]?([^\'")]*)/i', $css, $matches) === false) {
            return false;
        }

        foreach ($matches[1] as $target) {
            if (! str_starts_with(trim((string) $target), '#')) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $threats */
    private function hostileReason(array $threats): string
    {
        // Sahip teknik değildir: "external-reference" ona hiçbir şey
        // anlatmaz. Cümle NE OLDUĞUNU ve NE YAPACAĞINI söyler.
        $human = [
            'script' => 'çalıştırılabilir betik',
            'event-attribute' => 'olay kodu (onload/onclick)',
            'external-reference' => 'dış bağlantı',
            'foreignobject' => 'gömülü HTML',
            'foreign-markup' => 'gömülü HTML',
            'iframe' => 'gömülü çerçeve',
            'embed' => 'gömülü çerçeve',
            'object' => 'gömülü çerçeve',
            'external-style' => 'dış stil dosyası',
            'processing-instruction' => 'dış stil talimatı',
            'doctype-entity' => 'XML varlık tanımı',
        ];

        $found = [];

        foreach ($threats as $threat) {
            $found[] = $human[$threat] ?? 'animasyon/olay ögesi';
        }

        $found = array_values(array_unique($found));

        return 'Bu SVG dosyası '.implode(', ', $found).' içeriyor ve bu yüzden kabul edilmedi. '
            .'Menü sayfalarınız herkese açık olduğu için, içinden kod çalışabilen bir dosya yayına alınmaz. '
            .'Dosyayı çizim programınızdan "düz SVG" olarak yeniden verin ya da PNG yükleyin.';
    }

    private function withoutByteOrderMark(string $body): string
    {
        return str_starts_with($body, "\xEF\xBB\xBF") ? substr($body, 3) : $body;
    }
}
