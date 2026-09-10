<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Domain\Content\Block\BlockType;
use App\Domain\Money\MoneyFormatter;
use App\Infrastructure\Content\Pages\PricingPage;
use App\Infrastructure\Content\Pages\Tr\PricingPage as TurkishPricingPage;
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

    /**
     * DALGA 3 — FF-203.
     *
     * Ölçüm 2026-09-06: kütükte P0 olup kütüphanede karşılığı olmayan 79
     * sayfa var. `docs/119` §21 sırasında yazılabilir olan ilk altısı
     * bunlar: ürün genel bakışı (sekiz ürün sayfasının kırıntı atası ve
     * hiçbirinden ulaşılamayan yuva), §21'in yazılmamış tek üst başlığı
     * (tasarım ve marka) ve menü yönetiminin üründe GERÇEKTEN var olan dört
     * alt sayfası. Varyantlar ve ekstralar da P0 ama ürün bunları yapmıyor;
     * yapmadığı bir şeyin sayfası yazılmaz.
     *
     * Bu dalga ilk kez ÜÇ kademeli anahtar taşıyor (`urun.menu-yonetimi.
     * kategoriler`): alt sayfa, ebeveyninin bir kopyası değil, ebeveynin
     * bir adımda geçtiği şeyin kendi sorusudur.
     */
    private const THIRD_WAVE = [
        'urun',
        'urun.tasarim-ve-marka',
        'urun.menu-yonetimi.kategoriler',
        'urun.menu-yonetimi.urunler',
        'urun.menu-yonetimi.urun-fiyatlari',
        'urun.menu-yonetimi.stok-durumu',
    ];

    /**
     * DALGA 4 — FF-229.
     *
     * Ölçüm 2026-09-08 (`docs/137` §5): iki yetenek para karşılığı satılıyor
     * ya da satın alma kararını taşıyor, ve ikisinin de sitede tek bir satırı
     * yok.
     *
     * - **Masadan sipariş.** Uçtan uca çalışıyor (misafir gönderir, garson
     *   onaylar, mutfak görür) ve `ordering.basic` hakkı bir kademede
     *   SATILIYOR. Kütükte de, yazılmış içerikte de karşılığı yoktu: parası
     *   alınan bir yetenek hiçbir yerde anlatılmıyordu.
     * - **Yayın, sürümler ve geri alma.** Çalışıyor — önizleme, zamanlama,
     *   sürüm geçmişi, geri alma — ama yalnız `/help` içinde bir paragraf
     *   olarak geçiyordu. Oysa "yanlış listeyi yayınlarsam ne olur" bir
     *   satın alma sorusudur.
     *
     * SİPARİŞ TEK SAYFA VE ANAHTARI `urun.siparis`. Kütükte bir de
     * `urun.siparis.masaya-siparis` satırı var; alt sayfayı yazmak, ürünün
     * yapmadığı gel-al/paket servis kardeşlerine bakan BOŞ bir ata hub'ı da
     * yazmayı gerektirirdi — yani ebeveyni çocuğunun kopyası olan iki sayfa.
     * Ürün masadan siparişten başkasını yapmıyor; o yüzden hub'ın kendisi
     * masadan siparişi anlatır ve ötekilerin YOKLUĞUNU yazar.
     */
    private const FOURTH_WAVE = [
        'urun.siparis',
        'urun.menu-yonetimi.menu-versiyonlari',
    ];

    /**
     * DALGA 5 — PUBLIC-LOCALE-PARITY-01 (2026-09-10).
     *
     * Sahibin açık kararı: *ana dil İngilizce eksiksiz bitecek, Türkçe ikinci
     * dil olacak ve çevirilerin eksiği kalmayacak.* Bu dalga yeni bir SAYFA
     * eklemedi; var olan on sekiz sayfanın İKİNCİ DİLİNİ ekledi.
     *
     * Bu yüzden aşağıdaki ölçümlerin neredeyse hepsi `all()` üzerinde çalışır
     * ve iki dili ayırt etmez: bir kural yalnız kaynak dilde ölçülseydi,
     * ikinci dil sessizce kuralsız kalırdı — ve tam olarak orada, kimsenin
     * okumadığı yerde bozulurdu.
     *
     * @var list<string>
     */
    private const LOCALES = ['en', 'tr'];

    /** @return list<string> */
    private static function everyPage(): array
    {
        return array_merge(self::FIRST_FIVE, self::SECOND_WAVE, self::THIRD_WAVE, self::FOURTH_WAVE);
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

    public function test_every_written_corporate_page_has_turkish_content(): void
    {
        /*
            BU TEST BİR KARARIN TERSİNE DÖNMÜŞ HÂLİDİR.

            Burada `test_the_turkish_slots_are_deliberately_empty` duruyordu
            ve gerekçesi doğruydu: kurumsal sitenin İLK içerik dili sahibin
            AÇIK kararını bekliyordu (`docs/118` E4), ve boş yuvayı tahminle
            doldurmak o kararı sessizce vermek olurdu.

            Karar 2026-09-10'da GELDİ ve açıktır: *ana dil İngilizce eksiksiz
            bitecek, Türkçe ikinci dil olacak ve çevirilerin eksiği
            kalmayacak.* Ölçüm de onunla birlikte tersine döndü — eski test
            silinmedi, YERİNE geçti: aynı yuvaya bakıyor ve artık dolu
            olmasını istiyor.

            Ölçümün yönü değişti ama sertliği değişmedi. Eskiden bir Türkçe
            sayfanın VAR OLMASI kırılmaydı; şimdi YOK OLMASI kırılma. İkisi
            arasında "bazıları var" diye bir hâl yok, çünkü yarım bir ikinci
            dil, dil değiştiricide bir çıkmaz sokak demektir.
        */
        foreach (self::everyPage() as $pageKey) {
            self::assertNotNull(
                $this->library->find($pageKey, 'tr'),
                "Türkçe içerik eksik: {$pageKey}",
            );
        }
    }

    public function test_the_two_languages_offer_exactly_the_same_pages(): void
    {
        /*
            Tek yönlü ölçmek yetmez ve sebebi hreflang'dir: bir dilde var olup
            ötekinde olmayan bir sayfa, dil değiştiricide "karşılığı yok"
            demek ve karşılıklı hreflang kümesini bozmaktır (`docs/119`
            §10.4). Eşitlik iki yönlü ölçülür.
        */
        $keysByLocale = [];

        foreach ($this->library->all() as $content) {
            $keysByLocale[$content->locale][] = $content->pageKey;
        }

        self::assertSame(self::LOCALES, array_keys($keysByLocale));

        foreach ($keysByLocale as $locale => $keys) {
            sort($keys);
            $expected = self::everyPage();
            sort($expected);

            self::assertSame($expected, $keys, "`{$locale}` dilinde sayfa kümesi farklı.");
        }
    }

    public function test_the_turkish_page_is_a_counterpart_and_not_a_summary(): void
    {
        /*
            ÇEVİRİ EKSİKLİĞİ BİR DÜRÜSTLÜK EKSİKLİĞİDİR.

            Bir sayfanın Türkçesinde bir SINIRLAMA satırı eksik kalsaydı,
            Türkçe okuyan kişi ürünün YAPMADIĞI bir şeyi yapıyor sanırdı ve
            satın alma kararını onun üzerine kurardı — yönerge §1 madde
            18'in yasakladığı şeyin ikinci dildeki hâli.

            Bu yüzden ölçü "metin var mı" değil: iki dil AYNI blokları AYNI
            sırada ve HER BLOKTA AYNI SAYIDA satırla taşımak zorundadır. Bir
            özet, bir kısaltma ya da "sadece önemli maddeler" bu kapıdan
            geçemez.

            Kanıt yolları da eşleşir ve BİREBİR aynıdır: `source` bir metin
            değil, depodaki bir dosyadır. Çevrilmiş bir kanıt yolu, kanıtın
            kendisini kaybetmek olurdu.
        */
        foreach (self::everyPage() as $pageKey) {
            $source = $this->library->find($pageKey, 'en');
            $turkish = $this->library->find($pageKey, 'tr');

            self::assertNotNull($source, $pageKey);
            self::assertNotNull($turkish, $pageKey);

            self::assertSame(
                array_map(static fn ($block): string => $block->type->value, $source->blocks),
                array_map(static fn ($block): string => $block->type->value, $turkish->blocks),
                "{$pageKey}: iki dil aynı blokları aynı sırada taşımıyor.",
            );

            foreach ($source->blocks as $index => $block) {
                $counterpart = $turkish->blocks[$index];

                self::assertCount(
                    count($block->entries),
                    $counterpart->entries,
                    "{$pageKey} / {$block->type->value}: Türkçe blok farklı sayıda satır taşıyor.",
                );

                foreach ($block->entries as $position => $entry) {
                    $mirrored = $counterpart->entries[$position];

                    self::assertSame(
                        $entry->source,
                        $mirrored->source,
                        "{$pageKey} / {$block->type->value}: kanıt yolu eşleşmiyor.",
                    );
                    self::assertSame(
                        $entry->href,
                        $mirrored->href,
                        "{$pageKey} / {$block->type->value}: CTA hedefi eşleşmiyor.",
                    );
                    self::assertSame(
                        $entry->pageKey,
                        $mirrored->pageKey,
                        "{$pageKey} / {$block->type->value}: ilgili sayfa anahtarı eşleşmiyor.",
                    );
                }
            }
        }
    }

    public function test_no_turkish_page_is_english_wearing_a_turkish_label(): void
    {
        /*
            "Çeviri var" demenin en ucuz yolu, İngilizce metni olduğu gibi
            bırakıp yalnız başlığı değiştirmektir; ve o hâlde bütün öteki
            ölçümler yeşil yanar. Bu yüzden metnin KENDİSİ ölçülür: iki dilin
            görünen metni hiçbir satırda birebir aynı olamaz.

            Ölçü metnin İYİ olduğunu söylemez — onu insan okur — ama
            kopyalanmış bir sayfanın sessizce geçmesini imkânsız kılar.
        */
        foreach (self::everyPage() as $pageKey) {
            $source = $this->library->find($pageKey, 'en');
            $turkish = $this->library->find($pageKey, 'tr');

            self::assertNotNull($source, $pageKey);
            self::assertNotNull($turkish, $pageKey);

            self::assertNotSame($source->metadata->h1, $turkish->metadata->h1, $pageKey);
            self::assertNotSame($source->metadata->seoTitle, $turkish->metadata->seoTitle, $pageKey);
            self::assertNotSame(
                $source->metadata->metaDescription,
                $turkish->metadata->metaDescription,
                $pageKey,
            );

            foreach ($source->blocks as $index => $block) {
                /*
                    "İlgili sayfalar" bu ölçümün DIŞINDADIR ve bu bir boşluk
                    değil bir tür ayrımı: oradaki metin bir cümle değil, bir
                    sayfanın KENDİ ADIDIR ve bir ad iki dilde meşru biçimde
                    aynı olabilir. Bağlantının nereye gittiği zaten
                    `test_the_turkish_page_is_a_counterpart_and_not_a_summary`
                    tarafından anahtar anahtar kilitleniyor.
                */
                if ($block->type === BlockType::Related) {
                    continue;
                }

                $counterpart = $turkish->blocks[$index];

                foreach ($block->entries as $position => $entry) {
                    self::assertNotSame(
                        $entry->text,
                        $counterpart->entries[$position]->text,
                        "{$pageKey} / {$block->type->value}: satır çevrilmemiş, kopyalanmış.",
                    );
                }
            }
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
            static fn ($content): string => $content->locale.'|'.$content->pageKey,
            $this->library->all(),
        );

        sort($written);

        $expected = [];

        foreach (self::LOCALES as $locale) {
            foreach (self::everyPage() as $pageKey) {
                $expected[] = $locale.'|'.$pageKey;
            }
        }

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
            /*
                Bu ölçüm KAYNAK DİLİN adresine bakar; öteki dillerin adresi
                belgeden gelen kütük satırında yaşar (`docs/106`,
                `ImportSiteMapCommand`) ve bu dosyada tekrar edilmez. İkinci
                dili de buraya yazmak, aynı olgunun bir gün ayrışacak iki
                kaydını üretirdi.
            */
            if ($content->locale !== $sourceLocale) {
                continue;
            }

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
        // kapıyı sessizce boş geçerdi. Dalga 3 ile ölçülen sayı 370'i aştı;
        // dalga 5 ikinci dili eklediğinde aynı kanıtlar bir kez daha sayıldı.
        self::assertGreaterThan(600, $checked);
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

    public function test_every_written_page_has_its_parent_hub_written(): void
    {
        /*
            DALGA 3 — bir alt sayfanın atası olmadan var olması, kırıntıda
            tıklanamayan bir basamak ve hiçbir yerden ulaşılamayan bir sayfa
            demektir. Dalga 1 tam olarak bunu yaşadı: sekiz ürün sayfası
            yazılmışken `urun` yuvası boştu ve her kırıntının ilk basamağı
            ölüydü. Kural artık ölçülüyor: anahtarında nokta olan her sayfanın
            ebeveyni de kütüphanede yazılıdır.
        */
        foreach ($this->library->all() as $content) {
            $dot = strrpos($content->pageKey, '.');

            if ($dot === false) {
                continue;
            }

            $parent = substr($content->pageKey, 0, $dot);

            self::assertNotNull(
                $this->library->find($parent, $content->locale),
                "Öksüz alt sayfa: {$content->pageKey} yazılmış ama atası {$parent} yazılmamış.",
            );
        }
    }

    public function test_related_pages_point_only_at_pages_that_are_written(): void
    {
        /*
            "İlgili sayfalar" çizim anında yayın süzgecinden geçer; ama
            KÜTÜPHANEDE bile olmayan bir anahtar o süzgece hiç ulaşmaz ve
            sessizce düşer. Sessiz düşen bir bağlantı, yazım hatasıyla
            yazılmış bir anahtarı sonsuza dek gizler.
        */
        foreach ($this->library->all() as $content) {
            $related = $content->block(BlockType::Related);

            if ($related === null) {
                continue;
            }

            foreach ($related->entries as $entry) {
                self::assertNotNull($entry->pageKey, $content->pageKey);
                self::assertNotNull(
                    $this->library->find($entry->pageKey, $content->locale),
                    "{$content->pageKey}: ilgili sayfa yazılmamış — {$entry->pageKey}",
                );
            }
        }
    }

    public function test_the_product_overview_reaches_every_written_product_page(): void
    {
        /*
            Ürün genel bakışı bir HUB'dır ve hub olmanın ölçüsü budur: yazılmış
            her ürün sayfasına oradan bir bağlantı çıkar. Sekizinci sayfa
            yazıldığında biri onu buraya eklemeyi unutursa, sayfa var ama
            harita onu göstermiyor olur.
        */
        foreach (self::LOCALES as $locale) {
            $overview = $this->library->find('urun', $locale);
            self::assertNotNull($overview, $locale);

            $related = $overview->block(BlockType::Related);
            self::assertNotNull($related, $locale);

            $linked = array_map(static fn ($entry): ?string => $entry->pageKey, $related->entries);

            foreach ($this->library->all() as $content) {
                if ($content->locale !== $locale) {
                    continue;
                }

                if (preg_match('/^urun\.[^.]+$/', $content->pageKey) !== 1) {
                    continue;
                }

                self::assertContains(
                    $content->pageKey,
                    $linked,
                    "`{$locale}` genel bakışı {$content->pageKey} sayfasına ulaşmıyor.",
                );
            }
        }
    }

    public function test_no_two_pages_ask_the_same_question(): void
    {
        /*
            Doorway'in ölçülebilir imzası: aynı soruya iki sayfada cevap
            vermek. Bir alt sayfa, ebeveyninin SSS'sini yeniden sorduğu anda
            ebeveynin kopyasıdır (`docs/119` §13.2 cannibalization, §13.4).
            Ölçü kesindir: aynı soru metni sitede yalnız bir kez sorulur.
        */
        $seen = [];

        foreach ($this->library->all() as $content) {
            $faq = $content->block(BlockType::Faq);
            self::assertNotNull($faq, $content->pageKey);

            foreach ($faq->entries as $entry) {
                $question = mb_strtolower(trim((string) $entry->term));
                $elsewhere = $seen[$question] ?? '';

                self::assertArrayNotHasKey(
                    $question,
                    $seen,
                    "Aynı soru iki sayfada: \"{$entry->term}\" — {$elsewhere} ve {$content->pageKey}",
                );

                $seen[$question] = $content->pageKey;
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
        /*
            LİSTE İKİ DİLLİDİR ve olmak zorundadır. Yalnız İngilizce ifadeleri
            aramak, ikinci dilde "yakında" yazmayı serbest bırakırdı — üstelik
            kural yeşil yanmaya devam ederdi, ki en pahalı hâli budur.
        */
        $forbidden = [
            'coming soon', 'roadmap', 'will soon', 'in the coming', 'planned for', 'later this year',
            'yakında', 'yol haritası', 'yakın zamanda', 'planlanıyor', 'ilerleyen aylarda',
            'bu yıl içinde', 'ileride eklenecek', 'çok yakında',
        ];

        foreach ($this->library->all() as $content) {
            $haystack = mb_strtolower($this->flatten($content->pageKey, $content->locale));

            foreach ($forbidden as $phrase) {
                self::assertStringNotContainsString(
                    $phrase,
                    $haystack,
                    "{$content->locale}|{$content->pageKey}",
                );
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

        /*
            İKİ DİL DE ÖLÇÜLÜR. Fiyat sayfası, bir sayfanın yalan söylemesinin
            en pahalı olduğu yerdir ve o pahalılık ikinci dilde azalmaz —
            aksine, kimsenin bakmadığı yerde artar. Tutar Türkçe sayfada
            Türkçe biçimlendirilir (`₺` ve virgüllü ondalık), dolayısıyla
            beklenen metin de o dilin biçimlendirmesinden üretilir.
        */
        foreach (self::LOCALES as $locale) {
            $content = $this->library->find('fiyatlandirma', $locale);
            self::assertNotNull($content, $locale);

            $plans = $content->block(BlockType::Capabilities);
            self::assertNotNull($plans, $locale);

            $named = array_values(array_filter(array_map(
                static fn ($entry): ?string => $entry->term,
                $plans->entries,
            )));

            // Ne eksik ne fazla: katalogda olmayan bir plan da sayfada duramaz.
            self::assertSame(
                array_values(array_map(static fn (array $plan): string => $plan['name'], $catalogue)),
                $named,
                $locale,
            );

            $text = $this->flatten('fiyatlandirma', $locale);

            foreach ($catalogue as $plan) {
                self::assertStringContainsString(
                    MoneyFormatter::format($plan['amount_minor'], 'TRY', $locale),
                    $text,
                    "`{$locale}` katalogdaki fiyat sayfada yazmıyor: {$plan['name']}",
                );
            }
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
        foreach (self::LOCALES as $locale) {
            $allowed = [];

            foreach (PlanCatalogueSeeder::catalogue() as $plan) {
                $formatted = MoneyFormatter::format($plan['amount_minor'], 'TRY', $locale);

                foreach (self::figuresIn($formatted) as $figure) {
                    $allowed[$figure] = true;
                }
            }

            foreach (self::figuresIn($this->flatten('fiyatlandirma', $locale)) as $figure) {
                self::assertArrayHasKey(
                    $figure,
                    $allowed,
                    "`{$locale}` fiyat sayfasında kataloğa dayanmayan bir rakam var: {$figure}",
                );
            }
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
            Duyurulmayan bir hakkın adı sayfaya "yanlışlıkla" da girmemelidir
            — girerse satılmış sayılır.

            LİSTE BUGÜN BOŞ ve bu bir sonuçtur, bir kural değil. Tek sakini
            `menu.rich-media` idi: hakkın misafir yüzeyi yoktu, yani parası
            alınsa bile masadaki misafirin gördüğü hiçbir şey değişmiyordu.
            `docs/122` Y6 o yüzeyi yazdı (`GuestRichMediaTest`) ve satır
            ANNOUNCED'a taşındı. Mekanizmanın kendisi duruyor ve bir sonraki
            yüzeysiz hakta yine ölçer.
        */
        self::assertArrayNotHasKey(
            'menu.rich-media',
            PricingPage::WITHHELD,
            'Zengin görselin misafir yüzeyi yazıldı; susma gerekçesi düştü.',
        );

        /*
            İKİ DİLİN KARARI AYNI OLMAK ZORUNDA. Bir hakkın bir dilde
            anlatılıp ötekinde susulması, iki farklı ürün satmak olurdu:
            Türkçe okuyan alıcı, İngilizce okuyanın gördüğü bir yeteneği hiç
            görmezdi (ya da tersi). Bu yüzden ölçülen şey metin değil,
            ANAHTAR KÜMESİDİR.
        */
        self::assertSame(
            array_keys(PricingPage::ANNOUNCED),
            array_keys(TurkishPricingPage::ANNOUNCED),
            'Anlatılan haklar iki dilde aynı değil.',
        );
        self::assertSame(
            array_keys(PricingPage::WITHHELD),
            array_keys(TurkishPricingPage::WITHHELD),
            'Susulan haklar iki dilde aynı değil.',
        );

        foreach (self::LOCALES as $locale) {
            $text = mb_strtolower($this->flatten('fiyatlandirma', $locale));

            foreach (PricingPage::WITHHELD as $key => $reason) {
                self::assertNotSame('', trim($reason), "Sebepsiz susmak bir karar değildir: {$key}");
                self::assertStringNotContainsString(mb_strtolower($key), $text, $locale);
            }

            // Geliştirici dili hiçbir hâlde sayfaya sızmaz: anlatılan hak da
            // insanca cümlesiyle yazılır, ham anahtarıyla değil.
            self::assertStringNotContainsString('menu.rich-media', $text, $locale);
        }
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

    private function flatten(string $pageKey, string $locale): string
    {
        $content = $this->library->find($pageKey, $locale);

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
