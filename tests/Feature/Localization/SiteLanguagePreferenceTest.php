<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SiteLanguagePreferenceTest extends TestCase
{
    use RefreshDatabase;

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
        config(['i18n.shipped_locales' => ['en', 'tr']]);
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
}
