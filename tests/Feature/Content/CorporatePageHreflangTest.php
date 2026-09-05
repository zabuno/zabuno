<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Application\Content\Port\ContentLibraryPort;
use App\Domain\Content\PageContent;
use App\Domain\Content\PagePublicationStatus;
use App\Infrastructure\Content\Pages\QrMenuPage;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CORP-HREFLANG-01 — `docs/119` §10.4, `docs/120` §5.
 *
 * hreflang bir NEZAKET etiketi değil, bir İDDİADIR: "bu sayfanın şu dildeki
 * karşılığı şuradadır." Yarım çevrilmiş bir sayfayı alternatif olarak ilan
 * etmek, arama motoruna var olmayan bir karşılık göstermektir ve `docs/119`
 * §10.4 bunu açıkça yasaklıyor.
 *
 * Bu yüzden alternatif listesi ayrı bir bayraktan değil, kütüğün ZATEN TEK
 * KARAR NOKTASI olan `PageGate`'inden türer: bir dilde gerçekten açılan bir
 * sayfa varsa alternatiftir, yoksa yoktur.
 */
final class CorporatePageHreflangTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $locale, string $path, PagePublicationStatus $status): void
    {
        ContentPage::query()->create([
            'page_key' => 'urun.qr-menu',
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'QR menu',
            'priority' => 'P0',
            'publication_status' => $status->value,
            'was_ever_published' => $status->isPublished(),
        ]);
    }

    /**
     * İçeriği VERİLEN dillerde döndüren sahte kütüphane.
     *
     * ÇEVİRİ DEĞİLDİR ve bir tane bile üretilmedi: metin İngilizce
     * `QrMenuPage`'in ta kendisi, yalnız başka bir dil koduyla etiketlendi.
     * Ölçülen şey metnin kendisi değil, kütükte O DİLDE bir içerik VARKEN
     * hreflang'in ne yaptığı. Port zaten bunun için var.
     *
     * @param  list<string>  $locales
     */
    private function libraryServing(array $locales): void
    {
        $source = QrMenuPage::content();

        $contents = array_map(
            static fn (string $locale): PageContent => new PageContent(
                pageKey: $source->pageKey,
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

    public function test_two_finished_languages_declare_each_other_and_a_default(): void
    {
        $this->libraryServing(['en', 'tr']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Published);
        $this->row('tr', '/tr/urun/qr-menu/', PagePublicationStatus::Published);

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        self::assertStringContainsString('hreflang="en"', $html);
        self::assertStringContainsString('hreflang="tr"', $html);
        /*
            `x-default` KAYNAK DİLİ gösterir. `docs/105` §4.1 onu Türkçe
            canonical'a bağlamıştı; o madde `docs/118` E4 ile ezildi — kaynak
            dil artık İngilizce ve `x-default`, dili bilinmeyen ziyaretçinin
            düşeceği yerdir.
        */
        self::assertStringContainsString('hreflang="x-default"', $html);
    }

    public function test_a_half_finished_language_is_never_declared_as_an_alternate(): void
    {
        /*
            `docs/119` §10.4'ün kalbi. Türkçe kayıt kütükte VAR ve hatta
            "yayında" işaretli, ama o dilde gösterilecek bir içerik yok —
            sayfa 404 kalıyor. Onu hreflang'de ilan etmek, arama motoruna
            çalışmayan bir adres vermektir.
        */
        $this->libraryServing(['en']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Published);
        $this->row('tr', '/tr/urun/qr-menu/', PagePublicationStatus::Published);

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        self::assertStringNotContainsString('hreflang="tr"', $html);
        // Tek dil kaldıysa iddia da yoktur: kendine hreflang veren yalnız bir
        // sayfa, hiçbir soruyu yanıtlamayan gürültüdür.
        self::assertStringNotContainsString('hreflang=', $html);
    }

    public function test_the_declared_alternates_never_point_at_a_redirect(): void
    {
        /*
            SONDAKİ EĞİK ÇİZGİ ÇELİŞKİSİ. Kütük yolları belgenin yazımıyla,
            sondaki çizgiyle duruyor (`docs/105` §4.1 gösterimi); gerçek sunucu
            ise `config/url-policy.php` gereği o çizgiyi 301 ile atıyor.
            Alternatif adresi ham yoldan yazmak, her hreflang iddiasını bir
            YÖNLENDİRMEYE bağlamak olurdu.
        */
        $this->libraryServing(['en', 'tr']);
        $this->row('en', '/en/product/qr-menu/', PagePublicationStatus::Published);
        $this->row('tr', '/tr/urun/qr-menu/', PagePublicationStatus::Published);

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        self::assertMatchesRegularExpression('#hreflang="tr" href="https?://[^"]+/tr/urun/qr-menu"#', $html);
        self::assertDoesNotMatchRegularExpression('#hreflang="[^"]+" href="[^"]+/"#', $html);
    }

    public function test_a_right_to_left_page_turns_the_whole_document_around(): void
    {
        /*
            `docs/120` §5 madde 9: yön BELGEYE uygulanır, şablona değil. Dokuz
            dilin ikisi sağdan sola (`ar`, `fa`) ve `<html dir>` bugüne kadar
            SAYFANIN değil UYGULAMANIN dilinden türüyordu — yani Arapça bir
            kurumsal sayfa soldan sağa çizilirdi.
        */
        $this->libraryServing(['ar']);
        $this->row('ar', '/ar/product/qr-menu/', PagePublicationStatus::Published);

        $html = (string) $this->get('/ar/product/qr-menu/')->getContent();

        self::assertStringContainsString('lang="ar"', $html);
        self::assertStringContainsString('dir="rtl"', $html);
    }
}
