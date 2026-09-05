<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PAGE-GATE-HTTP-01 — FF-117, yönerge §7 ve §22.
 *
 * Kütükteki 414 yolun tamamı tek bir kapıdan geçer. Bu test kapının HTTP
 * davranışını ölçer: kararın kendisi `PageGateTest`'te saf olarak ölçülüyor,
 * burada ölçülen şey o kararın gerçekten uygulanıp uygulanmadığı.
 */
final class CorporatePageGateTest extends TestCase
{
    use RefreshDatabase;

    private function page(
        string $path,
        PagePublicationStatus $status,
        bool $wasPublished = false,
        string $locale = 'tr',
    ): ContentPage {
        return ContentPage::query()->create([
            'page_key' => 'urun.qr-menu',
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'QR Menü',
            'priority' => 'P0',
            'publication_status' => $status->value,
            'was_ever_published' => $wasPublished,
        ]);
    }

    public function test_an_unplanned_address_is_an_ordinary_404(): void
    {
        // Kütükte olmayan bir yol için hazırlanıyor ekranı göstermek, olmayan
        // bir sayfayı yapıyormuş gibi göstermek olurdu.
        $this->get('/tr/boyle-bir-sayfa-yok/')->assertStatus(404);
    }

    public function test_a_planned_page_answers_404_with_a_page_that_says_what_is_happening(): void
    {
        $this->page('/tr/urun/qr-menu/', PagePublicationStatus::Planned);

        $response = $this->get('/tr/urun/qr-menu/');

        /*
            404 — ve bu, kapının en önemli kararı. Yayınlanmamış 414 URL'ye
            `200` ile aynı metni vermek soft-404'tür ve alan adının kalitesini
            topluca düşürür. Hazırlanıyor ekranı 404'ün GÖVDESİDİR.
        */
        $response->assertStatus(404);
        $html = (string) $response->getContent();

        self::assertStringContainsString('Bu sayfa henüz servise çıkmadı', $html);
        // Ziyaretçi neyin hazırlandığını bilmeli: hem tarif hem ADRES.
        self::assertStringContainsString('QR Menü', $html);
        self::assertStringContainsString('/tr/urun/qr-menu/', $html);
        // Teknik durum adı DEĞİL, ziyaretçinin okuyabileceği bir cümle.
        self::assertStringNotContainsString('content_draft', $html);
        self::assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
    }

    public function test_the_visitor_is_never_left_without_a_way_out(): void
    {
        $this->page('/tr/urun/qr-menu/', PagePublicationStatus::ContentDraft);

        $html = (string) $this->get('/tr/urun/qr-menu/')->getContent();

        // Çıkmaz sokak yok: çalışan sayfalara dönüş var.
        self::assertStringContainsString('href="/"', $html);
    }

    public function test_a_published_page_is_served_and_indexable(): void
    {
        /*
            FF-191: "yayınlandı" artık gerçekten bir İÇERİK sayfası çiziyor ve
            içerik kütüğü dile göre aranıyor. Bu yüzden test İngilizce kaydı
            kullanıyor: Türkçe yuva `docs/118` E4 gereği bilerek boş ve içeriği
            olmayan bir sayfa — kütükte ne yazarsa yazsın — 404 kalır.
            Ölçülen şey değişmedi: yayınlanmış sayfa 200 döner ve indekslenir.
        */
        $this->page('/en/product/qr-menu/', PagePublicationStatus::Published, true, 'en');

        $response = $this->get('/en/product/qr-menu/');

        $response->assertStatus(200);
        self::assertStringNotContainsString(
            'noindex',
            (string) $response->headers->get('X-Robots-Tag'),
        );
    }

    public function test_a_page_in_maintenance_that_really_worked_before_answers_503(): void
    {
        $this->page('/tr/urun/qr-menu/', PagePublicationStatus::Maintenance, true);

        $response = $this->get('/tr/urun/qr-menu/');

        $response->assertStatus(503);
        // `Retry-After` gerçekçi olmalı; uydurma bir tarih verilmez.
        self::assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_a_page_that_never_worked_cannot_be_in_maintenance(): void
    {
        // 503 "bu sayfa vardı, kısa süreliğine yok" demektir. Hiç
        // yayınlanmamış bir sayfada kullanmak, var olmayan bir şeyin geri
        // geleceğini söylemektir.
        $this->page('/tr/urun/qr-menu/', PagePublicationStatus::Maintenance, false);

        $this->get('/tr/urun/qr-menu/')->assertStatus(404);
    }

    public function test_a_template_pattern_is_never_served_as_a_page(): void
    {
        // `/tr/blog/{slug}/` bir DESENDİR; süslü parantezli bir adres
        // ziyaretçiye gösterilemez.
        ContentPage::query()->create([
            'page_key' => 'blog.slug',
            'locale' => 'tr',
            'canonical_path' => '/tr/blog/{slug}/',
            'content_type' => 'blog',
            'template_key' => 'blog',
            'title' => 'Blog yazısı',
            'priority' => 'P0',
            'publication_status' => PagePublicationStatus::Published->value,
            'is_template' => true,
            'was_ever_published' => true,
        ]);

        $this->get('/tr/blog/%7Bslug%7D/')->assertStatus(404);
    }

    public function test_the_registry_never_swallows_the_existing_live_pages(): void
    {
        // Kütük `/tr/` ve `/en/` altında yaşar; bugün yayında olan adresler
        // ona dokunmaz.
        $this->get('/pricing')->assertStatus(200);
        $this->get('/')->assertStatus(200);
    }

    /**
     * KURUMSAL SAYFANIN DİLİ ADRESTEN GELİR — TARAYICIDAN DEĞİL.
     *
     * `docs/106`/`docs/105`: kurumsal sitenin kaynak dili Türkçedir ve her
     * locale kendi dizininde yaşar. Ürün arayüzü ise 2026-09-05'ten beri
     * yalnız İngilizce sunuluyor (`i18n.shipped_locales`) ve `NegotiateLocale`
     * o listeyi okuyor.
     *
     * İki kural aynı anda doğru ama AYNI YERDEN gelmiyor: ürün arayüzü
     * tarayıcıyla pazarlık eder, kurumsal sayfa etmez. Bu ayrım bugün
     * kazayla doğru: şablon `lang`'i `$page->locale`'den türetiyor. Kazayla
     * doğru olan bir şey, bir gün kazayla yanlış olur.
     *
     * Test o ayrımı kilitliyor: İngilizce isteyen bir tarayıcı `/tr/` altında
     * TÜRKÇE bir belge alır. Aksi hâlde Türkçe yazılmış bir sayfa
     * `lang="en"` ilan ederdi — ekran okuyucu yanlış telaffuz eder, arama
     * motoru yanlış dilde indeksler ve `hreflang` zinciri kendi içinde
     * çelişirdi.
     *
     * Requirement IDs: CORP-LOCALE-FROM-PATH-01.
     */
    public function test_a_corporate_page_declares_its_own_locale_not_the_browsers(): void
    {
        $this->page('/tr/urun/qr-menu/', PagePublicationStatus::Planned);

        $html = (string) $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/tr/urun/qr-menu/')
            ->getContent();

        self::assertStringContainsString(
            'lang="tr"',
            $html,
            'CORP-LOCALE-FROM-PATH-01: kurumsal sayfanın dili adresindedir; tarayıcı onu değiştiremez.'
        );
    }
}
