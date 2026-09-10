<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SiteLanguagePreferenceTest extends TestCase
{
    use RefreshDatabase;

    /** Kurumsal kütükte tek bir satır — dil değiştiricinin karşılık aradığı yer. */
    private function corporateRow(
        string $locale,
        string $path,
        PagePublicationStatus $status = PagePublicationStatus::Published,
        string $pageKey = 'urun.qr-menu',
    ): ContentPage {
        return ContentPage::query()->create([
            'page_key' => $pageKey,
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'QR',
            'priority' => 'P0',
            'publication_status' => $status->value,
            'was_ever_published' => $status === PagePublicationStatus::Published,
        ]);
    }

    public function test_actual_configuration_ships_english_then_turkish_with_english_default(): void
    {
        self::assertSame(['en', 'tr'], config('i18n.shipped_locales'));
        self::assertSame('en', config('i18n.source_locale'));
        self::assertSame('en', config('app.locale'));
        self::assertSame('en', config('app.fallback_locale'));
        $this->get('/')->assertOk()->assertSee('<html lang="en"', false);
        $this->withUnencryptedCookie('zbn_language', 'unsupported')->withHeader('Accept-Language', 'xx')
            ->get('/')->assertOk()->assertSee('<html lang="en"', false);
    }

    public function test_english_is_default_and_unshipped_turkish_cannot_be_selected(): void
    {
        config(['i18n.shipped_locales' => ['en']]);
        $this->get('/')->assertOk()->assertSee('<html lang="en"', false);
        $this->post('/language', ['language' => 'tr', 'return_to' => '/pricing'])
            ->assertSessionHasErrors('language')->assertCookieMissing('zbn_language');
    }

    public function test_native_form_offers_only_shipped_english_and_turkish(): void
    {
        config(['i18n.shipped_locales' => ['en', 'tr', 'de']]);
        $response = $this->get('/pricing?view=plans');
        $response->assertSee('name="return_to" value="/pricing?view=plans"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('action="'.route('public.language').'"', false)
            ->assertSee('name="language" value="en"', false)
            ->assertSee('name="language" value="tr"', false)
            ->assertDontSee('name="language" value="de"', false);
    }

    public function test_header_and_menu_share_an_id_free_language_form(): void
    {
        config(['i18n.shipped_locales' => ['en', 'tr']]);
        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        foreach (['desktop', 'menu'] as $presentation) {
            $forms = $xpath->query('//*[@data-language-presentation="'.$presentation.'"]//form');
            self::assertSame(1, $forms->length);
            self::assertSame(2, $xpath->query('.//button[@name="language"]', $forms->item(0))->length);
            self::assertSame(0, $xpath->query('.//*[@id]', $forms->item(0))->length);
        }
    }

    public function test_choice_persists_across_pages_and_reload_and_can_return_to_english(): void
    {
        $response = $this->post('/language', ['language' => 'tr', 'return_to' => '/pricing']);
        $response->assertRedirect('/pricing')->assertPlainCookie('zbn_language', 'tr');
        $cookie = $response->getCookie('zbn_language', false);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
        self::assertGreaterThan(time() + 86400, $cookie->getExpiresTime());
        foreach (['/pricing', '/contact', '/pricing'] as $path) {
            $this->withUnencryptedCookie('zbn_language', $cookie->getValue())->withHeader('Accept-Language', 'en')
                ->get($path)->assertOk()->assertSee('<html lang="tr"', false)->assertSee('İletişim');
        }
        $this->withUnencryptedCookie('zbn_language', $cookie->getValue())->post('/language', ['language' => 'en', 'return_to' => '/'])
            ->assertRedirect('/')->assertPlainCookie('zbn_language', 'en');
        $this->withUnencryptedCookie('zbn_language', 'en')->withHeader('Accept-Language', 'tr')
            ->get('/')->assertSee('<html lang="en"', false);
    }

    public function test_untrusted_or_unshipped_cookie_cannot_select_another_language(): void
    {
        config(['i18n.shipped_locales' => ['en', 'tr']]);
        foreach (['de', 'not-a-language', '//example.com'] as $value) {
            $this->withUnencryptedCookie('zbn_language', $value)->withHeader('Accept-Language', 'en')
                ->get('/')->assertOk()->assertSee('<html lang="en"', false);
        }
        config(['i18n.shipped_locales' => ['en']]);
        $this->withUnencryptedCookie('zbn_language', 'tr')->get('/')
            ->assertOk()->assertSee('<html lang="en"', false);
    }

    public function test_unsafe_return_destinations_fall_back_to_home(): void
    {
        foreach (['https://example.com', '//example.com', '/\\example.com', '/%2fexample.com', '/%5cexample.com', "/\nexample.com"] as $destination) {
            $this->post('/language', ['language' => 'en', 'return_to' => $destination])->assertRedirect('/');
        }
        $this->post('/language', ['language' => 'en', 'return_to' => '/pricing?view=plans#pricing'])
            ->assertRedirect('/pricing?view=plans#pricing');
    }

    /**
     * DİL DEĞİŞTİRMEK, SAYFANIN ÖTEKİ DİLDEKİ HÂLİNE GİDER.
     *
     * Kurumsal kütük sayfalarında dil ADRESİN İÇİNDE yaşar: metin kaydın
     * `locale` alanından okunur. Ziyaretçiyi aynı yola geri göndermek,
     * seçimini sessizce yok saymaktı — Türkçe bir kabuğun içinde İngilizce
     * metin okumaya devam ediyordu.
     *
     * Karşılık ANAHTARDAN bulunur, adresten değil: `/tr/urun/qr-menu/` ile
     * `/en/product/qr-menu/` arasında mekanik bir bağ yoktur ve slug
     * çevrilebilir bir alandır (`docs/119` §10.4).
     */
    public function test_switching_language_on_a_corporate_page_lands_on_its_counterpart(): void
    {
        $this->corporateRow('en', '/en/product/qr-menu/');
        $this->corporateRow('tr', '/tr/urun/qr-menu/');

        $this->post('/language', ['language' => 'tr', 'return_to' => '/en/product/qr-menu'])
            ->assertRedirect('/tr/urun/qr-menu')
            ->assertPlainCookie('zbn_language', 'tr');

        // Ve GERİ. Tek yönlü çalışan bir değiştirici, ziyaretçiyi öteki dile
        // hapsetmek olurdu.
        $this->post('/language', ['language' => 'en', 'return_to' => '/tr/urun/qr-menu'])
            ->assertRedirect('/en/product/qr-menu')
            ->assertPlainCookie('zbn_language', 'en');
    }

    public function test_the_canonical_form_of_the_address_also_finds_its_counterpart(): void
    {
        /*
            Kütük yolları sondaki eğik çizgiyle saklanır, sunucu ise o çizgiyi
            301 ile atar. Ziyaretçinin adres çubuğunda çizgisiz duran adresle
            kütükteki çizgili adresin AYNI satırı bulması gerekir; yoksa
            değiştirici gerçek hayatta hiç çalışmazdı.
        */
        $this->corporateRow('en', '/en/product/qr-menu/');
        $this->corporateRow('tr', '/tr/urun/qr-menu/');

        $this->post('/language', ['language' => 'tr', 'return_to' => '/en/product/qr-menu/'])
            ->assertRedirect('/tr/urun/qr-menu');
    }

    public function test_a_page_without_a_published_counterpart_keeps_the_visitor_where_they_are(): void
    {
        /*
            Karşılığı olmayan bir dile geçmek ziyaretçiyi 404'e göndermez ve
            ana sayfaya da atmaz: ikisi de dil değiştirmeyi bir cezaya
            çevirirdi. Tercih yine yazılır, okuduğu sayfa yine yerinde kalır.
        */
        $this->corporateRow('en', '/en/product/qr-menu/');

        $this->post('/language', ['language' => 'tr', 'return_to' => '/en/product/qr-menu'])
            ->assertRedirect('/en/product/qr-menu')
            ->assertPlainCookie('zbn_language', 'tr');
    }

    public function test_a_draft_counterpart_is_not_a_counterpart(): void
    {
        /*
            TASLAK SIZMAZ. Kütükte bir Türkçe satır olması yetmez; o adres
            ziyaretçiye 404 dönüyorsa bir karşılık değildir. Karar,
            ziyaretçinin alacağı HTTP kodunu üreten kararın ta kendisinden
            okunur (`ResolvePageDelivery`).
        */
        $this->corporateRow('en', '/en/product/qr-menu/');
        $this->corporateRow('tr', '/tr/urun/qr-menu/', PagePublicationStatus::ContentDraft);

        $this->post('/language', ['language' => 'tr', 'return_to' => '/en/product/qr-menu'])
            ->assertRedirect('/en/product/qr-menu');
    }

    public function test_a_living_route_is_never_rewritten_by_the_counterpart_lookup(): void
    {
        /*
            `/pricing` kütükte DEĞİLDİR ve dilini çerezden okur. Onu bir
            karşılığa çevirmeye kalkmak, tek adreste yaşayan bütün yaşayan
            rotaları kırardı. Sorgu ve çapa da olduğu gibi korunur.
        */
        $this->corporateRow('en', '/en/product/qr-menu/');
        $this->corporateRow('tr', '/tr/urun/qr-menu/');

        $this->post('/language', ['language' => 'tr', 'return_to' => '/pricing?view=plans#pricing'])
            ->assertRedirect('/pricing?view=plans#pricing');

        $this->post('/language', ['language' => 'tr', 'return_to' => '/help'])
            ->assertRedirect('/help');
    }

    public function test_an_unsafe_destination_is_still_refused_before_any_lookup(): void
    {
        /*
            GÜVENLİK SIRASI KORUNUR. Karşılık araması yalnız güvenli
            bulunmuş yerel yol üzerinde çalışır; dışarıdan gelen bir metin
            hiçbir hâlde yönlendirmenin hedefi olamaz.
        */
        $this->corporateRow('en', '/en/product/qr-menu/');
        $this->corporateRow('tr', '/tr/urun/qr-menu/');

        foreach (['https://example.com/en/product/qr-menu', '//example.com', '/%2fexample.com'] as $destination) {
            $this->post('/language', ['language' => 'tr', 'return_to' => $destination])
                ->assertRedirect('/');
        }
    }
}
