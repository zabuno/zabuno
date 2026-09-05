<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Domain\Content\Block\BlockType;
use App\Domain\Money\MoneyFormatter;
use App\Infrastructure\Content\Pages\PricingPage;
use App\Infrastructure\Content\ProductPageLibrary;
use Database\Seeders\PlanCatalogueSeeder;
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

    /**
     * DALGA 2 — FF-192.
     *
     * İlk beş sayfa ürünün ÇEKİRDEĞİNİ anlatıyordu. Bu beşi, ziyaretçinin
     * satın alma kararını verdiği yerlerdir: geriye kalan üç P0 ürün başlığı,
     * çözümler girişi ve fiyatlandırma. İkisi `urun` türünde değil — şablon
     * dilden olduğu gibi TÜRDEN de bağımsız kalmak zorundaydı ve bu paket onu
     * ölçüyor.
     */
    private const SECOND_WAVE = [
        'urun.gorsel-ve-medya',
        'urun.coklu-dil-ve-para-birimi',
        'urun.coklu-sube',
        'cozumler',
        'fiyatlandirma',
    ];

    /** @return list<string> */
    private static function everyPage(): array
    {
        return array_merge(self::FIRST_FIVE, self::SECOND_WAVE);
    }

    private ProductPageLibrary $library;

    protected function setUp(): void
    {
        parent::setUp();
        $this->library = new ProductPageLibrary;
    }

    public function test_every_written_corporate_page_has_english_content(): void
    {
        foreach (self::everyPage() as $pageKey) {
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
        foreach (self::everyPage() as $pageKey) {
            self::assertNull($this->library->find($pageKey, 'tr'));
        }
    }

    public function test_the_library_holds_nothing_beyond_the_pages_this_repository_declares(): void
    {
        /*
            Listeyi tek yönlü ölçmek yetmez. Yalnız "beklenen sayfa var mı"
            diye sorsaydık, kütüphaneye eklenmiş ama hiçbir yerde ilan
            edilmemiş bir sayfa — adresi olmayan, kırıntısı olmayan, kimsenin
            gözden geçirmediği bir sayfa — sessizce yayına girebilirdi.
        */
        $written = array_map(
            static fn ($content): string => $content->pageKey,
            $this->library->all(),
        );

        sort($written);
        $expected = self::everyPage();
        sort($expected);

        self::assertSame($expected, $written);
    }

    public function test_every_written_page_has_a_source_language_address(): void
    {
        /*
            İçeriği yazılmış ama adresi olmayan bir sayfa, DALGA 1'de
            gerçekten oldu: beş sayfa depoda duruyordu ve kütükte kaynak dil
            satırı olmadığı için hiçbir yerden açılamıyordu
            (`config/site-source-paths.php` gerekçesi).

            Adres MAKİNEYLE TÜRETİLMEZ — yarım çevrilmiş bir adres üretirdi —
            ama yazılmış olması ölçülebilir ve ölçülmelidir.
        */
        /** @var array<string, string> $sourcePaths */
        $sourcePaths = (array) config('site-source-paths');
        $sourceLocale = (string) config('i18n.source_locale');

        foreach ($this->library->all() as $content) {
            self::assertArrayHasKey(
                $content->pageKey,
                $sourcePaths,
                "İçeriği yazılmış ama kaynak dil adresi yok: {$content->pageKey}",
            );

            $path = $sourcePaths[$content->pageKey];

            self::assertStringStartsWith("/{$sourceLocale}/", $path, $content->pageKey);
            self::assertStringEndsWith('/', $path, $content->pageKey);
            // Adres, sayfanın KENDİ İngilizce başlığından inen bir slug'dır:
            // ASCII, küçük harf, tire. Türkçe bir segment burada yarım
            // çevrilmiş bir adres demek olurdu.
            self::assertMatchesRegularExpression('#^/[a-z]{2}(?:/[a-z0-9-]+)+/$#', $path, $content->pageKey);
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
        self::assertGreaterThan(90, $checked);
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

    public function test_the_pricing_page_writes_down_exactly_the_plans_in_the_catalogue(): void
    {
        /*
            Fiyat sayfası, bir sayfanın yalan söylemesinin EN PAHALI olduğu
            yerdir: ziyaretçi burada okuduğu rakama göre karar verir ve o
            rakam kasadakinden farklıysa geri kalan her doğru cümle de değerini
            kaybeder.

            Bu yüzden sayfa rakamı YAZMAZ, KATALOĞU OKUR. Katalogda bir plan
            eklenir, çıkarılır ya da fiyatı değişirse sayfa aynı gün değişir;
            değişmezse bu test kırılır.
        */
        $catalogue = PlanCatalogueSeeder::catalogue();

        $content = $this->library->find('fiyatlandirma', 'en');
        self::assertNotNull($content);

        $plans = $content->block(BlockType::Capabilities);
        self::assertNotNull($plans);

        $named = array_values(array_filter(array_map(
            static fn ($entry): ?string => $entry->term,
            $plans->entries,
        )));

        // Ne eksik ne fazla: katalogda olmayan bir plan da sayfada duramaz.
        self::assertSame(
            array_values(array_map(static fn (array $plan): string => $plan['name'], $catalogue)),
            $named,
        );

        $text = $this->flatten('fiyatlandirma');

        foreach ($catalogue as $plan) {
            self::assertStringContainsString(
                MoneyFormatter::format($plan['amount_minor'], 'TRY', 'en'),
                $text,
                "Katalogdaki fiyat sayfada yazmıyor: {$plan['name']}",
            );
        }
    }

    public function test_the_pricing_page_invents_no_figure_of_its_own(): void
    {
        /*
            "Kataloğu oku" bir uygulama tercihidir; kimse birinin YANINA elle
            bir rakam yazmasını engellemez — "yaklaşık 300 restoran", "ilk ay
            %50 indirim", "24 saatte kurulum". Bu yüzden ölçüm rakamın
            KENDİSİNE bakar: sayfadaki her rakam dizisi, kataloğun ürettiği
            metinlerden biri olmak zorundadır.

            Sayı yazmak yasak değil; KAYNAKSIZ sayı yazmak yasak.
        */
        $allowed = [];

        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            $formatted = MoneyFormatter::format($plan['amount_minor'], 'TRY', 'en');

            foreach (self::figuresIn($formatted) as $figure) {
                $allowed[$figure] = true;
            }
        }

        foreach (self::figuresIn($this->flatten('fiyatlandirma')) as $figure) {
            self::assertArrayHasKey(
                $figure,
                $allowed,
                "Fiyat sayfasında kataloğa dayanmayan bir rakam var: {$figure}",
            );
        }
    }

    public function test_a_plan_right_is_either_announced_in_english_or_deliberately_withheld(): void
    {
        /*
            Katalogda beliren bir hak, fiyat sayfasında iki şeyden biri olmak
            zorundadır: ANLATILAN bir satır ya da SEBEBİYLE SUSULAN bir satır.
            Üçüncü ihtimal — kimsenin fark etmediği bir hak — tam olarak
            "çalışan bir yetenek satılamıyor" durumunu üretir (`docs/122` Y1).
        */
        foreach (PlanCatalogueSeeder::catalogue() as $code => $plan) {
            foreach ($plan['entitlements'] as $key) {
                self::assertTrue(
                    isset(PricingPage::ANNOUNCED[$key]) || isset(PricingPage::WITHHELD[$key]),
                    "`{$code}` planındaki `{$key}` hakkı ne anlatılıyor ne de sebebiyle susuluyor.",
                );

                self::assertFalse(
                    isset(PricingPage::ANNOUNCED[$key]) && isset(PricingPage::WITHHELD[$key]),
                    "`{$key}` hem anlatılıyor hem susuluyor.",
                );
            }
        }
    }

    public function test_the_withheld_rights_are_nowhere_on_the_pricing_page(): void
    {
        /*
            `menu.rich-media` bilerek duyurulmuyor: hakkın misafir yüzeyi yok,
            yani parası alınırsa masadaki misafirin gördüğü hiçbir şey
            değişmez. Duyurulmayan bir hakkın adı sayfaya "yanlışlıkla" da
            girmemelidir — girerse satılmış sayılır.
        */
        /*
            Karar ADIYLA duruyor. Hak henüz katalogda olmadığı için kapsama
            testi ona bugün değmiyor; kararı yalnız o teste bırakmak, hak
            kademeye bağlandığı gün birinin sessizce ANNOUNCED'a yazmasına
            açık kapı bırakırdı. Susmanın sebebi WITHHELD'in kendisinde yazılı
            ve silinirse bu satır kırılır.
        */
        self::assertArrayHasKey('menu.rich-media', PricingPage::WITHHELD);

        $text = mb_strtolower($this->flatten('fiyatlandirma'));

        foreach (PricingPage::WITHHELD as $key => $reason) {
            self::assertNotSame('', trim($reason), "Sebepsiz susmak bir karar değildir: {$key}");
            self::assertStringNotContainsString(mb_strtolower($key), $text);
        }

        self::assertStringNotContainsString('rich media', $text);
    }

    /**
     * Metindeki rakam dizileri — ayırıcılar ve para birimi işaretleri dahil.
     *
     * @return list<string>
     */
    private static function figuresIn(string $text): array
    {
        preg_match_all('/\d[\d.,\x{00A0}\s]*\d|\d/u', $text, $matches);

        return array_map(
            static fn (string $figure): string => trim($figure),
            $matches[0],
        );
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
