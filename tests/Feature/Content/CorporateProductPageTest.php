<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domain\Content\PagePublicationStatus;
use App\Domain\Money\MoneyFormatter;
use App\Models\ContentPage;
use Database\Seeders\PlanCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CONTENT-RENDER-01 — yönerge §12, §13.1, §13.3, §14 ve §20.
 *
 * Kapının "yayınlandı" dalı bugüne kadar yalnız sayfanın başlığını yazıyordu
 * ve bu dürüst bir yer tutucuydu. Bu test o dalın artık gerçek bir içerik
 * sayfası çizdiğini ölçüyor — ve daha önemlisi, çizerken hangi kuralları
 * çiğnemediğini.
 *
 * Ölçümün SUNUCU çıktısında yapılması şart: §13.1 ana içeriğin ilk HTML
 * yanıtında bulunmasını istiyor. JavaScript çalıştırmayan bir bot (ki cevap
 * sistemlerinin çoğu öyle) yalnız bunu görür.
 */
final class CorporateProductPageTest extends TestCase
{
    use RefreshDatabase;

    private function page(
        string $pageKey,
        string $path,
        PagePublicationStatus $status,
        ?string $parentKey = null,
        string $locale = 'en',
        string $title = 'Placeholder title',
    ): ContentPage {
        return ContentPage::query()->create([
            'page_key' => $pageKey,
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'parent_key' => $parentKey,
            'title' => $title,
            'priority' => 'P0',
            'publication_status' => $status->value,
            'was_ever_published' => $status->isPublished(),
        ]);
    }

    private function publishedQrMenu(): void
    {
        $this->page('urun', '/en/product/', PagePublicationStatus::Planned, null, 'en', 'Product overview');
        $this->page('urun.qr-menu', '/en/product/qr-menu/', PagePublicationStatus::Published, 'urun');
    }

    public function test_a_published_product_page_serves_its_content_in_the_first_html_response(): void
    {
        $this->publishedQrMenu();

        $response = $this->get('/en/product/qr-menu/');

        $response->assertStatus(200);
        $html = (string) $response->getContent();

        // Kısa doğrudan cevap — §13.3'ün ilk gereksinimi.
        self::assertStringContainsString('opens in the browser they already have', $html);
        // Adım listesi, yetenek, sınırlama ve SSS de sunucudan gelir.
        self::assertStringContainsString('Publish a version', $html);
        self::assertStringContainsString('No sizes, portions or extras', $html);
        self::assertStringContainsString('Does the guest need to install an app?', $html);
    }

    public function test_the_page_carries_exactly_one_h1(): void
    {
        $this->publishedQrMenu();

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString('>QR menu</h1>', $html);
    }

    public function test_the_direct_answer_is_read_before_anything_else_on_the_page(): void
    {
        $this->publishedQrMenu();

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        $answer = strpos($html, 'opens in the browser they already have');
        $problem = strpos($html, 'The problem with a paper menu');

        self::assertIsInt($answer);
        self::assertIsInt($problem);
        self::assertLessThan($problem, $answer);
    }

    public function test_the_page_declares_its_own_canonical_address_and_no_keywords(): void
    {
        $this->publishedQrMenu();

        $response = $this->get('/en/product/qr-menu/');
        $html = (string) $response->getContent();

        self::assertStringContainsString('rel="canonical"', $html);
        /*
            Kanonik adres, sunucunun YÖNLENDİRMEDEN sunduğu adrestir.

            Kütük yolu `/en/product/qr-menu/` biçiminde duruyor ama
            `config/url-policy.php` sondaki bölü çizgisini 301 ile atıyor.
            Ham yolu kanonik yapmak, sayfanın kendi kanonik adresinin bir
            yönlendirmeyi göstermesi olurdu. Test istemcisi bunu gizler —
            yolu isteği kurmadan normalleştirir — bu yüzden ölçüm gerçek bir
            sunucuda yapıldı ve kural buraya yazıldı.
        */
        self::assertMatchesRegularExpression('#rel="canonical" href="https?://[^"]+/en/product/qr-menu"#', $html);
        self::assertDoesNotMatchRegularExpression('#href="https?://[^"]+/en/product/qr-menu/"#', $html);
        // Yönerge §12: meta keywords alanı OLUŞTURULMAZ.
        self::assertStringNotContainsString('name="keywords"', $html);
        $response->assertHeader('X-Robots-Tag', 'index, follow');
    }

    public function test_the_title_and_description_come_from_the_content_layer(): void
    {
        $this->publishedQrMenu();

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        self::assertStringContainsString('QR menu for restaurants', $html);
        self::assertStringContainsString('Guests scan the code on the table', $html);
    }

    public function test_the_structured_data_is_in_the_server_rendered_html_and_invents_nothing(): void
    {
        $this->publishedQrMenu();

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        self::assertStringContainsString('application/ld+json', $html);
        self::assertStringContainsString('SoftwareApplication', $html);
        self::assertStringContainsString('BreadcrumbList', $html);
        self::assertStringContainsString('FAQPage', $html);
        self::assertStringNotContainsString('aggregateRating', $html);
    }

    public function test_an_unpublished_ancestor_appears_in_the_breadcrumb_without_a_link(): void
    {
        /*
            `docs/105` §2.2(3): yayınlanmamış sayfa hiçbir yerden iç bağlantı
            almaz. Hiyerarşi yine gösterilir — basamağı silmek ziyaretçiye
            yanlış bir ağaç göstermek olurdu — ama tıklanmaz.
        */
        $this->publishedQrMenu();

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        self::assertStringContainsString('Product overview', $html);
        self::assertStringNotContainsString('href="/en/product/"', $html);
    }

    public function test_a_related_page_is_not_linked_until_it_is_published(): void
    {
        $this->publishedQrMenu();

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        // Kütükte hiç yok: bağlantı da yok, satır da yok.
        self::assertStringNotContainsString('/en/product/menu-management/', $html);
    }

    public function test_a_related_page_is_linked_once_it_is_published(): void
    {
        $this->publishedQrMenu();
        $this->page('urun.menu-yonetimi', '/en/product/menu-management/', PagePublicationStatus::Published, 'urun');

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        // Yönlendirmeye değil, sunucunun doğrudan sunduğu adrese.
        self::assertStringContainsString('href="/en/product/menu-management"', $html);
        self::assertStringNotContainsString('href="/en/product/menu-management/"', $html);
    }

    public function test_a_page_marked_published_without_content_is_a_404_not_an_empty_page(): void
    {
        /*
            Yayınlanmış ama içeriği olmayan bir sayfa, kapının en baştan
            engellemek için var olduğu şeydir: 200 dönen ince bir sayfa. Doğru
            cevap "burada henüz bir şey yok"tur — yani 404 — ve bu, kütükteki
            durumun elle ileri sürülmesine karşı son emniyet kemeridir.
        */
        $this->page('urun.tablet-menu', '/en/product/tablet-menu/', PagePublicationStatus::Published, null);

        $this->get('/en/product/tablet-menu/')->assertStatus(404);
    }

    public function test_the_turkish_address_still_answers_404_because_its_content_slot_is_empty(): void
    {
        /*
            `docs/118` E4 — kurumsal sitenin ilk içerik dili sahibin kararını
            bekliyor. Türkçe yuva boş; kütükteki Türkçe kayıt YAYINA ALINSA
            BİLE ortada gösterilecek bir içerik yok ve sayfa 404 kalır.
            Yazılmamış bir sayfayı 200 ile sunmak, yönergenin baştan yasakladığı
            soft-404'tür.
        */
        $this->page('urun.qr-menu', '/tr/urun/qr-menu/', PagePublicationStatus::Published, null, 'tr', 'QR menü');

        $this->get('/tr/urun/qr-menu/')->assertStatus(404);
    }

    public function test_a_page_that_is_not_a_product_page_renders_from_the_same_template(): void
    {
        /*
            FF-192: kütüphane ürün sayfasına ÖZEL değildir. Fiyatlandırma
            `urun` türünde değil ve aynı tek şablondan çiziliyor — yönergenin
            §7'sindeki "414 yol için 414 layout üretme" kuralı ancak böyle
            tutulur.

            Şema da türe göre karar veriyor: `SoftwareApplication` yalnız ürün
            sayfalarında üretilir (`CorporatePageStructuredData`). Fiyat
            sayfasında görünen bir fiyat var ama `Offer` işaretlemesi
            YAZILMADI; üretilmeyen de bir karardır ve uydurulmuş bir teklif
            işaretlemesinden iyidir.
        */
        ContentPage::query()->create([
            'page_key' => 'fiyatlandirma',
            'locale' => 'en',
            'canonical_path' => '/en/pricing/',
            'content_type' => 'fiyatlandirma',
            'template_key' => 'fiyatlandirma',
            'parent_key' => null,
            'title' => 'Pricing',
            'priority' => 'P0',
            'publication_status' => PagePublicationStatus::Published->value,
            'was_ever_published' => true,
        ]);

        $response = $this->get('/en/pricing/');

        $response->assertStatus(200);
        $html = (string) $response->getContent();

        self::assertStringContainsString('>Plans and prices</h1>', $html);
        // Rakam KATALOGDAN geliyor; sayfada elle yazılmış bir fiyat yok. Test
        // de aynı kaynağı okur, yoksa fiyat burada ikinci kez yazılmış olurdu.
        $restaurant = PlanCatalogueSeeder::catalogue()['restaurant'];
        self::assertStringContainsString(
            MoneyFormatter::format($restaurant['amount_minor'], 'TRY', 'en'),
            $html,
        );
        self::assertStringContainsString('FAQPage', $html);
        self::assertStringNotContainsString('SoftwareApplication', $html);
    }

    public function test_the_corporate_page_draws_no_icons(): void
    {
        // `docs/118` E6: ikon yasağı KURUMSAL SİTE için korunuyor. Yerine
        // metin, tipografi, boşluk ve çizgi.
        $this->publishedQrMenu();

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();
        $body = substr($html, (int) strpos($html, '<main'));

        self::assertStringNotContainsString('<svg', $body);
        self::assertStringNotContainsString('<i class', $body);
    }
}
