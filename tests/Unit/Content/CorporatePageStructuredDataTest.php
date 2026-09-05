<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Breadcrumb;
use App\Infrastructure\Content\ProductPageLibrary;
use App\Support\Seo\CorporatePageStructuredData;
use Tests\TestCase;

/**
 * SCHEMA-CENTRAL-01 — yönerge §14.
 *
 * Şema üretimi MERKEZÎDİR ve sayfa türüne göre yalnız GEÇERLİ şemayı üretir.
 * Şablon başına elle yazılmış JSON-LD, beşinci sayfada birinin `aggregateRating`
 * eklemesiyle biterdi — ve sahte bir puan, yönergenin §13.8'de yasakladığı
 * yanıltıcı structured data'nın ta kendisidir.
 *
 * İkinci kural: **görünmeyen bilgi şemaya yazılmaz.** Test bunu ölçüyor —
 * şemadaki her yetenek adı sayfada görünen bir yetenek adıdır.
 */
final class CorporatePageStructuredDataTest extends TestCase
{
    /** @return array<string, mixed> */
    private function graphFor(string $pageKey, bool $withTrail = true): array
    {
        $content = (new ProductPageLibrary)->find($pageKey, 'en');
        self::assertNotNull($content);

        $trail = $withTrail
            ? [new Breadcrumb('Product', '/en/product/'), new Breadcrumb($content->metadata->breadcrumbTitle, null)]
            : [];

        return CorporatePageStructuredData::forPage(
            contentType: 'urun',
            content: $content,
            canonicalUrl: 'https://zabuno.test/en/product/qr-menu/',
            siteUrl: 'https://zabuno.test',
            trail: $trail,
        );
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return list<array<string, mixed>>
     */
    private function nodes(array $graph, string $type): array
    {
        $found = [];

        /** @var list<array<string, mixed>> $nodes */
        $nodes = $graph['@graph'] ?? [];

        foreach ($nodes as $node) {
            if (($node['@type'] ?? null) === $type) {
                $found[] = $node;
            }
        }

        return $found;
    }

    public function test_a_product_page_declares_the_application_the_breadcrumb_and_the_visible_faq(): void
    {
        $graph = $this->graphFor('urun.qr-menu');

        self::assertSame('https://schema.org', $graph['@context']);
        self::assertCount(1, $this->nodes($graph, 'BreadcrumbList'));
        self::assertCount(1, $this->nodes($graph, 'SoftwareApplication'));
        self::assertCount(1, $this->nodes($graph, 'FAQPage'));
    }

    public function test_no_page_type_receives_a_schema_that_does_not_belong_to_it(): void
    {
        // Yönerge §14: uygun şema tablosu. Bir ürün sayfası bir `Article`,
        // bir `Recipe` ya da bir `JobPosting` değildir.
        $graph = $this->graphFor('urun.analitik');

        foreach (['Article', 'BlogPosting', 'Product', 'Recipe', 'JobPosting', 'Event', 'LocalBusiness'] as $wrong) {
            self::assertSame([], $this->nodes($graph, $wrong), $wrong);
        }
    }

    public function test_a_rating_or_a_review_is_never_produced(): void
    {
        /*
            Yönerge §14: "sahte rating veya review üretilemez". Bugün tek bir
            değerlendirme yok; şemaya bir puan yazmak, arama sonucunda yıldız
            göstermek için gerçeği uydurmak olurdu.
        */
        $encoded = json_encode($this->graphFor('urun.zabuno-ai'), JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('aggregateRating', $encoded);
        self::assertStringNotContainsString('ratingValue', $encoded);
        self::assertStringNotContainsString('"review"', $encoded);
        // Fiyat sayfada GÖRÜNMÜYOR; şemada da bulunmaz.
        self::assertStringNotContainsString('"offers"', $encoded);
    }

    public function test_the_declared_features_are_exactly_the_ones_a_visitor_can_read(): void
    {
        $content = (new ProductPageLibrary)->find('urun.masa-ve-qr-yonetimi', 'en');
        self::assertNotNull($content);

        $visible = [];
        $capabilities = $content->block(BlockType::Capabilities);
        self::assertNotNull($capabilities);

        foreach ($capabilities->entries as $entry) {
            $visible[] = (string) $entry->term;
        }

        $application = $this->nodes($this->graphFor('urun.masa-ve-qr-yonetimi'), 'SoftwareApplication')[0];

        self::assertSame($visible, $application['featureList']);
    }

    public function test_the_faq_entries_are_the_visible_questions_and_answers(): void
    {
        $content = (new ProductPageLibrary)->find('urun.menu-yonetimi', 'en');
        self::assertNotNull($content);

        $faq = $content->block(BlockType::Faq);
        self::assertNotNull($faq);

        $node = $this->nodes($this->graphFor('urun.menu-yonetimi'), 'FAQPage')[0];

        self::assertCount(count($faq->entries), $node['mainEntity']);
        self::assertSame($faq->entries[0]->term, $node['mainEntity'][0]['name']);
        self::assertSame($faq->entries[0]->text, $node['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_a_single_step_trail_produces_no_breadcrumb_list(): void
    {
        // Tek basamaklı bir "hiyerarşi" hiyerarşi değildir; onu işaretlemek
        // arama motoruna var olmayan bir yapı bildirmek olurdu.
        self::assertSame([], $this->nodes($this->graphFor('urun.qr-menu', withTrail: false), 'BreadcrumbList'));
    }

    public function test_an_unknown_page_type_gets_the_breadcrumb_and_nothing_invented(): void
    {
        $content = (new ProductPageLibrary)->find('urun.qr-menu', 'en');
        self::assertNotNull($content);

        $graph = CorporatePageStructuredData::forPage(
            contentType: 'kaynaklar',
            content: $content,
            canonicalUrl: 'https://zabuno.test/en/resources/x/',
            siteUrl: 'https://zabuno.test',
            trail: [new Breadcrumb('Resources', null), new Breadcrumb('X', null)],
        );

        self::assertCount(1, $this->nodes($graph, 'BreadcrumbList'));
        self::assertSame([], $this->nodes($graph, 'SoftwareApplication'));
    }
}
