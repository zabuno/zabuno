<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Support\Analytics\MeasurementConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CONSENT-COOKIE-01…06 — çerez tercihi artık VERİLEBİLİYOR (FF-198).
 *
 * ÖLÇÜLDÜ: `MeasurementConsent::granted()/denied()` vardı ama çerezi YAZAN
 * hiçbir uç yoktu. Kapı doğru kuruluydu ve hep kapalıydı — ziyaretçiye
 * hiç sorulmadığı için ölçüm fiilen hiç açılmıyordu. Bu paket soruyu
 * soran şeridi ve cevabı yazan ucu ekler; JavaScript'siz çalışır, çünkü
 * kurumsal sitenin kabuğu betiksiz çalışmak ZORUNDA (SHELL-SINGLE-SOURCE-04).
 */
final class MeasurementConsentEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function withAnalytics(): void
    {
        config(['analytics.gtm_container_id' => 'GTM-TEST123']);
    }

    // --- CONSENT-COOKIE-01: kabul çerezi yazar --------------------------------

    public function test_accepting_writes_a_one_year_granted_cookie_and_returns_to_the_page(): void
    {
        $response = $this->post('/consent/measurement', ['decision' => 'accept', 'return_to' => '/pricing']);

        $response->assertRedirect('/pricing');
        $response->assertCookie(MeasurementConsent::COOKIE, 'granted');

        $cookie = $response->getCookie(MeasurementConsent::COOKIE);

        self::assertNotNull($cookie);
        self::assertEqualsWithDelta(
            time() + MeasurementConsent::LIFETIME_DAYS * 24 * 60 * 60,
            $cookie->getExpiresTime(),
            120,
            'CONSENT-COOKIE-01: kararın ömrü bir yıl olmalı — sonsuz bir onay bir imzadır.'
        );
    }

    public function test_declining_writes_a_denied_cookie(): void
    {
        $this->post('/consent/measurement', ['decision' => 'decline'])
            ->assertRedirect('/')
            ->assertCookie(MeasurementConsent::COOKIE, 'denied');
    }

    // --- CONSENT-COOKIE-02: geçersiz karar ve dış adres reddedilir -----------

    public function test_an_unknown_decision_is_rejected_without_writing_a_cookie(): void
    {
        $response = $this->from('/cookies')->post('/consent/measurement', ['decision' => 'maybe']);

        $response->assertSessionHasErrors('decision');
        $response->assertCookieMissing(MeasurementConsent::COOKIE);
    }

    public function test_the_return_address_must_be_a_local_path(): void
    {
        $this->post('/consent/measurement', ['decision' => 'accept', 'return_to' => 'https://evil.example/phish'])
            ->assertRedirect('/');

        $this->post('/consent/measurement', ['decision' => 'accept', 'return_to' => '//evil.example'])
            ->assertRedirect('/');
    }

    // --- CONSENT-COOKIE-03: şerit yalnız karar YOKKEN ve ölçüm VARKEN ---------

    public function test_the_banner_is_shown_only_while_no_decision_exists_and_measurement_is_configured(): void
    {
        $this->withAnalytics();

        $undecided = (string) $this->get('/pricing')->assertOk()->getContent();
        self::assertStringContainsString('data-consent-banner', $undecided, 'CONSENT-COOKIE-03: karar yokken şerit görünmeli.');
        self::assertStringContainsString('action="/consent/measurement"', $undecided);
        self::assertStringContainsString('name="_token"', $undecided, 'CONSENT-COOKIE-03: form CSRF taşımalı.');

        $granted = (string) $this->withCookie(MeasurementConsent::COOKIE, 'granted')->get('/pricing')->getContent();
        self::assertStringNotContainsString('data-consent-banner', $granted, 'CONSENT-COOKIE-03: kabul sonrası şerit kalkmalı.');

        $denied = (string) $this->withCookie(MeasurementConsent::COOKIE, 'denied')->get('/pricing')->getContent();
        self::assertStringNotContainsString('data-consent-banner', $denied, 'CONSENT-COOKIE-03: ret sonrası şerit kalkmalı.');
    }

    public function test_the_banner_never_asks_for_a_measurement_that_is_not_configured(): void
    {
        config(['analytics.gtm_container_id' => '']);

        $this->get('/pricing')->assertOk()->assertDontSee('data-consent-banner', false);
    }

    // --- CONSENT-COOKIE-04: karar konteyneri yükler / yüklemez -----------------

    public function test_a_granted_decision_loads_the_container_and_a_denied_one_does_not(): void
    {
        $this->withAnalytics();

        $this->withCookie(MeasurementConsent::COOKIE, 'granted')->get('/')
            ->assertOk()
            ->assertSee('googletagmanager.com/gtm.js', false);

        $this->withCookie(MeasurementConsent::COOKIE, 'denied')->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager.com/gtm.js', false);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager.com/gtm.js', false);
    }

    // --- CONSENT-COOKIE-05: /cookies sayfası tercihi gösterir ve yeniden sorar --

    public function test_the_cookie_policy_page_shows_the_current_choice_and_lets_it_be_changed(): void
    {
        $undecided = (string) $this->get('/cookies')->assertOk()->getContent();
        self::assertStringContainsString('data-consent-preference="undecided"', $undecided);
        self::assertStringContainsString('action="/consent/measurement"', $undecided, 'CONSENT-COOKIE-05: tercih formu yok.');
        self::assertStringContainsString('value="accept"', $undecided);
        self::assertStringContainsString('value="decline"', $undecided);

        $granted = (string) $this->withCookie(MeasurementConsent::COOKIE, 'granted')->get('/cookies')->getContent();
        self::assertStringContainsString('data-consent-preference="granted"', $granted);

        $denied = (string) $this->withCookie(MeasurementConsent::COOKIE, 'denied')->get('/cookies')->getContent();
        self::assertStringContainsString('data-consent-preference="denied"', $denied);
    }

    // --- CONSENT-COOKIE-06: şerit ikinci bir kabuk tanımı DEĞİLDİR ------------

    public function test_the_banner_is_not_a_second_header_or_footer(): void
    {
        $this->withAnalytics();

        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        self::assertSame(1, preg_match_all('#<header\b#', $html));
        self::assertSame(1, preg_match_all('#<footer\b#', $html));
        // Şerit ana içerikten SONRA gelir: ilk ekranı içerikten önce doldurmaz.
        self::assertGreaterThan(strpos($html, '<main'), strpos($html, 'data-consent-banner'));
    }
}
