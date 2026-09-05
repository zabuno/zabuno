<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;

/**
 * Bir sayfanın, TEK BİR DİLDEKİ editoryal içeriği — FF-191.
 *
 * Sayfa KİMLİĞİ burada değil kütükte yaşar (`content_pages`): adres, ebeveyn,
 * yayın durumu ve robots kararı oranın işidir. Burada yalnız o kimliğe
 * giydirilen metin var. Ayrım `docs/105` §2.2(2)'nin kararıdır — arayüz metni
 * PO/MO'da, editoryal sayfa içeriği ayrı bir katmanda — ve `docs/118` E4'ün
 * verdiği sözü tutan şey de bu: kurumsal sitenin ilk içerik dili değişirse
 * YALNIZ bu katman değişir, iskelet yerinde kalır.
 */
final class PageContent
{
    /** @param  list<ContentBlock>  $blocks */
    public function __construct(
        public readonly string $pageKey,
        public readonly string $locale,
        public readonly PageMetadata $metadata,
        public readonly array $blocks,
    ) {
        $this->assertTemplateContract();
    }

    public function block(BlockType $type): ?ContentBlock
    {
        foreach ($this->blocks as $block) {
            if ($block->type === $type) {
                return $block;
            }
        }

        return null;
    }

    private function assertTemplateContract(): void
    {
        $seen = [];
        $previousRank = 0;

        foreach ($this->blocks as $block) {
            if (isset($seen[$block->type->value])) {
                // İki "yetenekler" bölümü, sayfanın tek niyetini ikiye böler
                // (§13.2: tek sayfa, tek ana niyet).
                throw new PageContentException(
                    "{$this->pageKey}: aynı blok iki kez — {$block->type->value}.",
                );
            }

            if ($block->type->rank() < $previousRank) {
                /*
                    Sıra bir üslup tercihi değil. Cevap sistemleri sayfanın
                    BAŞINDAN okur (§13.3); sonda duran bir "kısa cevap" cevap
                    değildir. Sırayı elle dizmeye bırakmak, beşinci sayfada
                    birinin onu bozması demekti.
                */
                throw new PageContentException(
                    "{$this->pageKey}: şablon sırası bozuldu — {$block->type->value}.",
                );
            }

            $seen[$block->type->value] = true;
            $previousRank = $block->type->rank();
        }

        foreach (BlockType::requiredForProductPage() as $required) {
            if (! isset($seen[$required->value])) {
                throw new PageContentException(
                    "{$this->pageKey}: zorunlu blok eksik — {$required->value}.",
                );
            }
        }
    }
}
