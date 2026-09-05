<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageContentException;
use App\Domain\Content\PageMetadata;
use PHPUnit\Framework\TestCase;

/**
 * CONTENT-TEMPLATE-01 — yönerge §15, ürün sayfası şablonu.
 *
 * Şablon bir TAVSİYE DEĞİL bir SÖZLEŞMEDİR. Beş sayfa için beş ayrı bileşen
 * ağacı üretmek, yönergenin §13.4'te yasakladığı şeyin kapıdan içeri girmesi
 * olurdu: aynı işi yapan, zamanla birbirinden ayrışan kopya sayfalar. Bu
 * yüzden sıra ve zorunlu bloklar burada ölçülür — her sayfa AYNI iskeleti
 * FARKLI içerikle doldurur.
 */
final class PageContentTemplateTest extends TestCase
{
    /** @param list<ContentBlock> $blocks */
    private function content(array $blocks): PageContent
    {
        return new PageContent(
            pageKey: 'urun.qr-menu',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'QR menu',
                metaDescription: 'A description that is long enough to be a real description.',
                h1: 'QR menu',
                breadcrumbTitle: 'QR menu',
            ),
            blocks: $blocks,
        );
    }

    private function block(BlockType $type, ?string $heading = 'Heading'): ContentBlock
    {
        return new ContentBlock($type, $heading, [new BlockEntry(text: 'A sentence with content.')]);
    }

    /** @return list<ContentBlock> */
    private function completeBlocks(): array
    {
        $blocks = [];

        foreach (BlockType::requiredForProductPage() as $type) {
            $blocks[] = $this->block($type, $type->needsHeading() ? 'Heading' : null);
        }

        return $blocks;
    }

    public function test_a_complete_product_page_is_accepted(): void
    {
        $content = $this->content($this->completeBlocks());

        self::assertSame('urun.qr-menu', $content->pageKey);
        self::assertNotSame([], $content->blocks);
    }

    public function test_the_direct_answer_comes_first_because_answer_engines_read_the_top_of_the_page(): void
    {
        // Yönerge §13.3'ün ilk gereksinimi: "sayfanın başında kısa ve doğrudan
        // cevap". Sonda duran bir cevap, cevap değildir.
        $content = $this->content($this->completeBlocks());

        self::assertSame(BlockType::DirectAnswer, $content->blocks[0]->type);
    }

    public function test_blocks_out_of_template_order_are_refused(): void
    {
        $blocks = $this->completeBlocks();
        [$blocks[0], $blocks[1]] = [$blocks[1], $blocks[0]];

        $this->expectException(PageContentException::class);
        $this->content($blocks);
    }

    public function test_a_missing_required_block_is_refused(): void
    {
        $blocks = $this->completeBlocks();
        array_pop($blocks);

        $this->expectException(PageContentException::class);
        $this->content($blocks);
    }

    public function test_the_same_block_type_cannot_appear_twice(): void
    {
        // İki "yetenekler" bölümü, sayfanın tek niyetini ikiye böler
        // (yönerge §13.2: "tek sayfa, tek ana niyet").
        $blocks = $this->completeBlocks();
        $blocks[] = $this->block(BlockType::Capabilities);

        $this->expectException(PageContentException::class);
        $this->content($blocks);
    }

    public function test_an_empty_block_is_refused_because_a_heading_with_no_content_is_thin_content(): void
    {
        $this->expectException(PageContentException::class);
        new ContentBlock(BlockType::Capabilities, 'Capabilities', []);
    }

    public function test_a_block_that_needs_a_heading_cannot_be_headless(): void
    {
        // Başlıksız bir bölüm, başlık hiyerarşisinde bir delik açar; ekran
        // okuyucu kullanıcısı sayfayı başlıklarla gezer.
        $this->expectException(PageContentException::class);
        new ContentBlock(BlockType::Capabilities, null, [new BlockEntry(text: 'Something.')]);
    }

    public function test_the_direct_answer_carries_no_heading_because_it_is_the_lede_under_the_single_h1(): void
    {
        $this->expectException(PageContentException::class);
        new ContentBlock(BlockType::DirectAnswer, 'Heading', [new BlockEntry(text: 'Something.')]);
    }

    public function test_metadata_cannot_be_blank(): void
    {
        $this->expectException(PageContentException::class);
        new PageMetadata(seoTitle: '', metaDescription: 'x', h1: 'y', breadcrumbTitle: 'z');
    }
}
