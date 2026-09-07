<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Application\Content\Port\ContentLibraryPort;
use App\Domain\Content\PageContent;
use App\Domain\Content\PagePublicationStatus;
use App\Infrastructure\Content\Pages\QrMenuPage;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SITEMAP-REGISTRY — FF-214, `docs/129`.
 *
 * Ölçülen kusur: `sitemap.xml` sayfa kütüğünü HİÇ okumuyordu. Dört adres
 * sabit yazılıydı ve üzerine yalnız yayınlanmış menüler ekleniyordu; yani
 * sahip bir kurumsal sayfayı yayına aldığında sitemap'e girmeyecekti ve kimse
 * fark etmeyecekti, çünkü sitemap her zaman geçerli bir XML döndürüyordu
 * (`docs/128` §6.4).
 *
 * Bu testin kalbi tek bir cümledir: **sitemap'te duran her adres 200
 * dönmelidir.** Geri kalan her test, o cümlenin tek tek unutulan hâlleridir —
 * taslak, planlanan, emekli, bakımda, şablon, dış bağlantı, metni yazılmamış.
 */
final class SitemapRegistryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * İçeriği VERİLEN dillerde döndüren sahte kütüphane.
     *
     * ÇEVİRİ DEĞİLDİR ve bir tane bile üretilmedi: metin İngilizce
     * `QrMenuPage`'in ta kendisi, yalnız başka bir dil koduyla etiketlendi.
     * Ölçülen şey metnin kendisi değil, kütükte O DİLDE bir içerik varken (ve
     * yokken) sitemap'in ne yaptığı.
     *
     * @param  list<string>  $locales
     */
    private function libraryServing(array $locales, string $pageKey = 'urun.qr-menu'): void
    {
        $source = QrMenuPage::content();

        $contents = array_map(
            static fn (string $locale): PageContent => new PageContent(
                pageKey: $pageKey,
                locale: $locale,
                metadata: $source->metadata,
                blocks: $source->blocks,
            ),
            $locales,
        );

        $this->instance(ContentLibraryPort::class, new class($contents) implements ContentLibraryPort
        {
            /** @param  list<PageContent>  $contents */
            public function __construct(private readonly array $contents) {}

            public function find(string $pageKey, string $locale): ?PageContent
            {
                foreach ($this->contents as $content) {
                    if ($content->pageKey === $pageKey && $content->locale === $locale) {
                        return $content;
                    }
                }

                return null;
            }

            /** @return list<PageContent> */
            public function all(): array
            {
                return $this->contents;
            }
        });
    }

    /** @param array<string, mixed> $overrides */
    private function row(string $locale, string $path, PagePublicationStatus $status, array $overrides = []): ContentPage
    {
        return ContentPage::query()->create($overrides + [
            'page_key' => 'urun.qr-menu',
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'QR menu',
            'priority' => 'P0',
            'publication_status' => $status->value,
            'is_template' => false,
            'is_external' => false,
            'was_ever_published' => $status->isPublished(),
        ]);
    }

    private function sitemap(): string
    {
        return (string) $this->get('/sitemap.xml')->getContent();
    }

    /**
     * Beklenen tam adres.
     *
     * Host KODA GÖMÜLMEZ: sitemap adresi isteğin kendi host'undan üretiliyor
     * ve o host yapılandırmadan geliyor. Sabit `localhost` yazmak, testi
     * yalnız bir `.env` değerinde yeşil tutardı.
     */
    private function url(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }

    // --- Kütükten türeme -------------------------------------------------

    public function test_a_published_page_with_content_reaches_the_sitemap(): void
    {
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Published);

        // Kanonik yazımın AYNISI: kütük sondaki eğik çizgiyle duruyor, sunucu
        // onu 301 ile atıyor. Sitemap'in yönlendirme ilan etmesi, arama
        // motoruna verilebilecek en kafa karıştırıcı sinyallerden biri olurdu.
        self::assertStringContainsString('<loc>'.$this->url('/en/product/qr-menu').'</loc>', $this->sitemap());
    }

    public function test_a_planned_page_never_reaches_the_sitemap(): void
    {
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Planned);

        self::assertStringNotContainsString('/en/product/qr-menu', $this->sitemap());
    }

    public function test_a_draft_page_never_reaches_the_sitemap(): void
    {
        // `content_draft` bugün deponun GERÇEK durumu: `site:sync-content-status`
        // içeriği yazılmış on altı sayfayı buraya taşıyor ve orada bırakıyor.
        // Kalite kapısı insanların işidir; bir betiğin atlayabildiği kapı,
        // kapı değildir.
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::ContentDraft);

        self::assertStringNotContainsString('/en/product/qr-menu', $this->sitemap());
    }

    public function test_an_approved_page_is_still_not_published(): void
    {
        // `approved` YAYIN DEĞİLDİR. Aradaki farkı silmek, kapıyı atlamanın en
        // kolay yolu olurdu.
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Approved);

        self::assertStringNotContainsString('/en/product/qr-menu', $this->sitemap());
    }

    public function test_a_retired_page_leaves_the_sitemap(): void
    {
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Retired, ['was_ever_published' => true]);

        self::assertStringNotContainsString('/en/product/qr-menu', $this->sitemap());
    }

    public function test_a_page_under_maintenance_leaves_the_sitemap(): void
    {
        // 503 "bu sayfa VARDI, kısa süreliğine yok" demektir. İndekste kalması
        // istenir ama sitemap'te durması, tarayıcıyı çalışmayan bir adrese
        // davet etmek olurdu.
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Maintenance, ['was_ever_published' => true]);

        self::assertStringNotContainsString('/en/product/qr-menu', $this->sitemap());
    }

    public function test_a_template_pattern_is_not_an_address(): void
    {
        // `/tr/blog/{slug}/` bir DESENDİR, bir sayfa değil. Kütükte 73 böyle
        // satır var (ölçüldü) ve hepsi ziyaretçiye 404 döner.
        $this->libraryServing(['en']);
        $this->row('en', '/en/blog/{slug}/', PagePublicationStatus::Published, ['is_template' => true]);

        self::assertStringNotContainsString('/en/blog/', $this->sitemap());
    }

    public function test_an_external_link_is_not_a_page_on_this_site(): void
    {
        // Kütükte 3 dış bağlantı satırı var (ölçüldü). Başkasının sitesini
        // kendi sitemap'inde ilan etmek, sahibi olmadığın bir adres hakkında
        // arama motoruna beyanda bulunmaktır.
        $this->libraryServing(['en']);
        $this->row('en', '/en/external/', PagePublicationStatus::Published, ['is_external' => true]);

        self::assertStringNotContainsString('/en/external', $this->sitemap());
    }

    public function test_a_published_page_without_text_is_not_announced(): void
    {
        /*
            Kütükteki durum elle ileri sürülebilir; kalite kapısı bir süreçtir,
            bir kilit değil. Metni olmayan "yayında" bir sayfa 404 döner —
            dolayısıyla sitemap'e de giremez. Bu, deponun BUGÜNKÜ hâli:
            Türkçe içerik yuvası bilerek boş (`docs/118` E4).
        */
        $this->libraryServing(['en']);
        $this->row('tr', '/tr/urun/qr-menu/', PagePublicationStatus::Published);

        self::assertStringNotContainsString('/tr/urun/qr-menu', $this->sitemap());
    }

    // --- Tek kaynak ------------------------------------------------------

    public function test_every_address_in_the_sitemap_actually_opens(): void
    {
        /*
            BU PAKETİN ASIL KAPISI. Sitemap üyeliği ile HTTP kodu aynı
            `PageRenderDecision` nesnesinden okunuyor; bu test o iddiayı
            uçtan uca ölçer. İki ayrı yerde iki ayrı kural bir gün ayrışır ve
            sitemap olmayan sayfaları ilan eder — arama motoruna yalan
            söylemektir bu.
        */
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Published);
        $this->row('tr', '/tr/urun/qr-menu/', PagePublicationStatus::Published);
        $this->row('en', '/en/blog/{slug}/', PagePublicationStatus::Published, ['page_key' => 'blog.yazi', 'is_template' => true]);
        $this->row('en', '/en/partner/', PagePublicationStatus::Published, ['page_key' => 'ortak', 'is_external' => true]);
        $this->row('en', '/en/product/analytics/', PagePublicationStatus::Approved, ['page_key' => 'urun.analitik']);

        self::assertSame(
            1,
            preg_match_all('#<loc>([^<]+)</loc>#', $this->sitemap(), $matches) > 0 ? 1 : 0,
            'Sitemap hiç adres taşımıyor.',
        );

        foreach ($matches[1] as $loc) {
            $path = (string) parse_url(html_entity_decode($loc), PHP_URL_PATH);

            $this->get($path)->assertStatus(
                200,
                "Sitemap'te duran {$path} açılmıyor.",
            );
        }
    }

    // --- Çok dillilik ----------------------------------------------------

    public function test_two_published_languages_declare_each_other_in_the_sitemap(): void
    {
        $this->libraryServing(['en', 'tr']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Published);
        $this->row('tr', '/tr/urun/qr-menu/', PagePublicationStatus::Published);

        $xml = $this->sitemap();

        self::assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
        self::assertStringContainsString('<xhtml:link rel="alternate" hreflang="en" href="'.$this->url('/en/product/qr-menu').'"/>', $xml);
        self::assertStringContainsString('<xhtml:link rel="alternate" hreflang="tr" href="'.$this->url('/tr/urun/qr-menu').'"/>', $xml);
        // `x-default` KAYNAK DİLİ gösterir (`docs/118` E4).
        self::assertStringContainsString('<xhtml:link rel="alternate" hreflang="x-default" href="'.$this->url('/en/product/qr-menu').'"/>', $xml);
    }

    public function test_a_language_that_does_not_exist_is_never_announced(): void
    {
        /*
            BU DEPONUN EN SOMUT RİSKİ. 386 Türkçe satırın yalnız 16'sının
            kaynak dil karşılığı yazılmış (`config/site-source-paths.php`);
            geri kalan 370 sayfanın `/en/...` adresi YOKTUR. Onu türetip
            sitemap'e yazmak, arama motoruna 404 vaat etmektir.
        */
        $this->libraryServing(['tr']);
        $this->row('tr', '/tr/urun/qr-menu/', PagePublicationStatus::Published);

        $xml = $this->sitemap();

        self::assertStringContainsString('<loc>'.$this->url('/tr/urun/qr-menu').'</loc>', $xml);
        self::assertStringNotContainsString('/en/product/qr-menu', $xml);
        // Tek dil kaldıysa iddia da yoktur: kendine hreflang veren yalnız bir
        // sayfa hiçbir soruyu yanıtlamaz.
        self::assertStringNotContainsString('hreflang=', $xml);
    }

    public function test_a_half_finished_language_is_never_declared_as_an_alternate(): void
    {
        // Türkçe kayıt kütükte VAR ve "yayında" işaretli, ama o dilde
        // gösterilecek metin yok — sayfa 404 kalıyor.
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Published);
        $this->row('tr', '/tr/urun/qr-menu/', PagePublicationStatus::Published);

        $xml = $this->sitemap();

        self::assertStringContainsString('<loc>'.$this->url('/en/product/qr-menu').'</loc>', $xml);
        self::assertStringNotContainsString('hreflang="tr"', $xml);
    }

    // --- Sabit yollar ve tekrar ------------------------------------------

    public function test_the_living_addresses_are_still_listed(): void
    {
        /*
            Ölçüldü (`docs/129` §4): bu dokuz yolun hiçbiri kütükte yok ve
            olamaz — hiçbiri kurumsal kapıdan geçmiyor. Ana sayfa ve sekiz
            yasal belge kendi rotalarıdır ve bugün 200 dönüyorlar.
        */
        $xml = $this->sitemap();

        foreach (['/terms', '/privacy', '/kvkk', '/distance-sales', '/pre-information', '/refund-policy', '/cookies', '/marketing-consent'] as $path) {
            self::assertStringContainsString('<loc>'.$this->url($path).'</loc>', $xml);
        }

        self::assertStringContainsString('<loc>'.$this->url('/').'</loc>', $xml);
    }

    public function test_the_same_address_is_never_listed_twice(): void
    {
        /*
            Bugün çakışma yok; bu süzgeç, bir yol kütüğe TAŞINDIĞI gün için
            var. Çakışma sessizce oluşur ve tekrar eden bir girdi arama
            motoruna aynı sayfayı iki kez ilan eder.
        */
        $this->libraryServing(['en']);
        $this->row('en', '/terms', PagePublicationStatus::Published, ['page_key' => 'yasal.terms']);

        preg_match_all('#<loc>([^<]+)</loc>#', $this->sitemap(), $matches);

        self::assertSame(
            array_values(array_unique($matches[1])),
            $matches[1],
            'Aynı adres sitemap\'te iki kez listelendi.',
        );
    }

    // --- Biçim -----------------------------------------------------------

    public function test_the_sitemap_stays_well_formed_xml(): void
    {
        $this->libraryServing(['en', 'tr']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Published);
        $this->row('tr', '/tr/urun/qr-menu/', PagePublicationStatus::Published);

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($this->sitemap());
        libxml_use_internal_errors($previous);

        self::assertNotFalse($document, 'sitemap.xml geçerli XML değil.');
    }

    public function test_a_date_is_never_invented(): void
    {
        /*
            Uydurulmuş bir tarih, tarih olmamasından kötüdür: arama motoruna
            değişmemiş bir sayfayı yeniden tarat demektir. Kütükte yayın damgası
            bugün HİÇ doldurulmuyor (`site:import-map` doldurmaz), dolayısıyla
            varsayılan cevap "tarih yok"tur.
        */
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Published);

        self::assertStringNotContainsString('<lastmod>', $this->sitemap());

        ContentPage::query()->where('locale', 'en')->update(['published_at' => '2026-09-01 10:00:00']);

        self::assertStringContainsString('<lastmod>2026-09-01</lastmod>', $this->sitemap());
    }
}
