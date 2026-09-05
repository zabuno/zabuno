<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Domain\Content\Block\BlockType;
use App\Infrastructure\Content\ProductPageLibrary;
use Tests\TestCase;

/**
 * CONTENT-TRUTH-01 — `docs/118` §2 ve yönerge §1 madde 18.
 *
 * *"Ürünün gerçekten desteklemediği özellik veya entegrasyon yayınlanmaz."*
 *
 * Bu cümle bir niyet beyanı olarak kaldığı sürece bir gün tutulmaz: pazarlama
 * metni yazan kişi (ya da model) ürünün ne yaptığını hatırlamak zorunda kalır.
 * Bu yüzden her yetenek, her adım, her gereksinim ve her sınırlama satırı
 * DEPODA BİR YOL taşır ve test o yolun gerçekten var olduğunu ölçer. Kanıtı
 * silinen bir iddia, testi kırar.
 *
 * Yol var olmak zorundadır; ne söylediğini test okuyamaz — onu insan okur.
 * Ama "kanıt göster" şartı, kanıtsız bir cümlenin sessizce eklenmesini
 * imkânsız kılar.
 */
final class ProductPageLibraryTest extends TestCase
{
    private const FIRST_FIVE = [
        'urun.qr-menu',
        'urun.menu-yonetimi',
        'urun.masa-ve-qr-yonetimi',
        'urun.analitik',
        'urun.zabuno-ai',
    ];

    private ProductPageLibrary $library;

    protected function setUp(): void
    {
        parent::setUp();
        $this->library = new ProductPageLibrary;
    }

    public function test_the_first_five_p0_product_pages_have_english_content(): void
    {
        foreach (self::FIRST_FIVE as $pageKey) {
            self::assertNotNull(
                $this->library->find($pageKey, 'en'),
                "İngilizce içerik eksik: {$pageKey}",
            );
        }
    }

    public function test_the_turkish_slots_are_deliberately_empty(): void
    {
        /*
            `docs/118` E4: kurumsal sitenin İLK içerik dili sahibin AÇIK
            kararını bekliyor. Türkçe yuvayı tahminle doldurmak, o kararı
            sessizce vermek olurdu — ve karar tersine dönerse atılacak emek
            üretirdi. Yuva boş duruyor; karar geldiği gün YALNIZ bu katman
            değişir, iskelet değişmez.

            Bu aynı zamanda çeviri kilidinin kendisiyle de tutarlıdır: burada
            hiçbir çeviri üretilmedi, hiçbir çeviri işi kuyruklanmadı.
        */
        foreach (self::FIRST_FIVE as $pageKey) {
            self::assertNull($this->library->find($pageKey, 'tr'));
        }
    }

    public function test_every_claim_points_at_a_file_that_actually_exists_in_this_repository(): void
    {
        $checked = 0;

        foreach ($this->library->all() as $content) {
            foreach ($content->blocks as $block) {
                foreach ($block->entries as $entry) {
                    if ($entry->source === null) {
                        continue;
                    }

                    $checked++;
                    self::assertFileExists(
                        base_path($entry->source),
                        "{$content->pageKey}: kanıt gösterilen yol depoda yok — {$entry->source}",
                    );
                }
            }
        }

        // Kanıtın kendisi de ölçülür: hiç kanıt taşımayan bir kütük, bu
        // kapıyı sessizce boş geçerdi.
        self::assertGreaterThan(40, $checked);
    }

    public function test_every_capability_step_requirement_and_limitation_carries_its_evidence(): void
    {
        $evidenceBearing = [
            BlockType::HowItWorks,
            BlockType::Capabilities,
            BlockType::Requirements,
            BlockType::Limitations,
        ];

        foreach ($this->library->all() as $content) {
            foreach ($content->blocks as $block) {
                if (! in_array($block->type, $evidenceBearing, true)) {
                    continue;
                }

                foreach ($block->entries as $entry) {
                    self::assertNotNull(
                        $entry->source,
                        "{$content->pageKey} / {$block->type->value}: kanıtsız iddia — \"{$entry->text}\"",
                    );
                }
            }
        }
    }

    public function test_titles_h1s_and_descriptions_are_unique_across_the_site(): void
    {
        // Yönerge §12: "her sayfanın benzersiz title, H1 ve meta description'ı
        // olmalıdır". Aynı başlığı taşıyan iki sayfa, aynı sorgu için birbiriyle
        // yarışır (§13.2 cannibalization).
        $titles = [];
        $descriptions = [];
        $headings = [];

        foreach ($this->library->all() as $content) {
            $titles[] = $content->metadata->seoTitle;
            $descriptions[] = $content->metadata->metaDescription;
            $headings[] = $content->metadata->h1;
        }

        self::assertSame($titles, array_values(array_unique($titles)));
        self::assertSame($descriptions, array_values(array_unique($descriptions)));
        self::assertSame($headings, array_values(array_unique($headings)));
    }

    public function test_a_meta_description_stays_inside_what_a_result_page_actually_shows(): void
    {
        foreach ($this->library->all() as $content) {
            $length = mb_strlen($content->metadata->metaDescription);

            self::assertGreaterThanOrEqual(70, $length, $content->pageKey);
            self::assertLessThanOrEqual(165, $length, $content->pageKey);
        }
    }

    public function test_the_direct_answer_is_short_enough_to_be_quoted_whole(): void
    {
        /*
            Yönerge §13.3: cevap sistemleri sayfanın başındaki cevabı ALINTILAR.
            Üç paragraflık bir "kısa cevap" alıntılanamaz; alıntılanamayan cevap
            o sistemlerde yoktur.
        */
        foreach ($this->library->all() as $content) {
            $answer = $content->blocks[0];

            self::assertSame(BlockType::DirectAnswer, $answer->type);
            self::assertCount(1, $answer->entries);
            self::assertLessThanOrEqual(320, mb_strlen($answer->entries[0]->text), $content->pageKey);
        }
    }

    public function test_every_page_answers_real_questions(): void
    {
        foreach ($this->library->all() as $content) {
            $faq = $content->block(BlockType::Faq);

            self::assertNotNull($faq, $content->pageKey);
            self::assertGreaterThanOrEqual(3, count($faq->entries), $content->pageKey);

            foreach ($faq->entries as $entry) {
                self::assertNotNull($entry->term, $content->pageKey);
                self::assertStringEndsWith('?', $entry->term, $content->pageKey);
            }
        }
    }

    public function test_no_page_promises_something_that_is_not_here_yet(): void
    {
        /*
            Yönerge §1 madde 18 ve `docs/118` §2: desteklenmeyen bir şey sayfada
            "yakında" diye bile geçmez. Bir yol haritası cümlesi, okuyanın
            kafasında bugün var olan bir özelliğe dönüşür ve satın alma kararı
            onun üzerine kurulur.
        */
        $forbidden = ['coming soon', 'roadmap', 'will soon', 'in the coming', 'planned for', 'later this year'];

        foreach ($this->library->all() as $content) {
            $haystack = mb_strtolower($this->flatten($content->pageKey));

            foreach ($forbidden as $phrase) {
                self::assertStringNotContainsString($phrase, $haystack, $content->pageKey);
            }
        }
    }

    private function flatten(string $pageKey): string
    {
        $content = $this->library->find($pageKey, 'en');

        if ($content === null) {
            return '';
        }

        $parts = [
            $content->metadata->seoTitle,
            $content->metadata->metaDescription,
            $content->metadata->h1,
        ];

        foreach ($content->blocks as $block) {
            $parts[] = (string) $block->heading;

            foreach ($block->entries as $entry) {
                $parts[] = (string) $entry->term;
                $parts[] = $entry->text;
            }
        }

        return implode(' ', $parts);
    }
}
