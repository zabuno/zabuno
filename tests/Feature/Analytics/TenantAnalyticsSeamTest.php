<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Support\Analytics\AnalyticsConfiguration;
use App\Support\Analytics\MeasurementConsent;
use Tests\TestCase;

/**
 * ANALYTICS-TENANT-SEAM — sahibin kilit kuralının testi.
 *
 * Kural: Google Analytics, Yandex Metrica, Google Tag Manager, dataLayer,
 * Hotjar ve Metabase ile her şey TENANT BAZINDA analiz edilebilmeli.
 *
 * Bu testler kuralın üç sessiz kırılma yolunu kapatır. Üçü de "sayfa
 * çalışıyor" göründüğü hâlde veriyi kaybettirir; hiçbiri tarayıcıda hata
 * üretmez, dolayısıyla elle bakarak fark edilmezler:
 *
 *   1. Ölçüm KAPALIYKEN sıkı CSP gevşerse, saldırı yüzeyi hiçbir karşılık
 *      almadan büyür.
 *   2. Ölçüm AÇIKKEN CSP gevşemezse, GTM yüklenir ama ölçüm sunucuya hiç
 *      ulaşamaz: raporlar sessizce boş kalır.
 *   3. Tenant alanı olmadan olay akarsa, veri toplanır ama "hangi restoran"
 *      sorusu cevapsız kalır — yani kuralın kendisi çiğnenir.
 */
final class TenantAnalyticsSeamTest extends TestCase
{
    /**
     * ONAY ARTIK ŞART (FF-173).
     *
     * Bu testler bir zamanlar "GTM kimliği varsa konteyner yüklenir"
     * davranışını donduruyordu ve o davranış DEĞİŞTİ: konteyner artık açık
     * bir kabul olmadan sayfaya hiç girmez. Testler kabulü çerezle veriyor —
     * yani ölçülen sözleşme aynı (nonce, sıra, dönüşüm olayları), yalnız
     * ölçümün ön koşulu eklendi.
     */
    private function withMeasurementConsent(): self
    {
        return $this->withCookie(
            MeasurementConsent::COOKIE,
            'granted',
        );
    }
    // --- Kapalıyken hiçbir bedel ödenmez ----------------------------------

    public function test_no_measurement_script_and_no_relaxed_policy_when_no_container_is_configured(): void
    {
        config(['analytics.gtm_container_id' => '']);

        $response = $this->withMeasurementConsent()->get('/');

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
        $response->assertDontSee('dataLayer', false);

        $policy = (string) $response->headers->get('Content-Security-Policy');

        self::assertStringContainsString("connect-src 'self'", $policy);
        self::assertStringNotContainsString('google-analytics.com', $policy);
        self::assertStringNotContainsString('mc.yandex.ru', $policy);
        self::assertStringNotContainsString('hotjar', $policy);
    }

    // --- Açıkken kapı yalnız açılan araç kadar açılır ----------------------

    public function test_only_the_enabled_destinations_are_allowed_through_the_policy(): void
    {
        config([
            'analytics.gtm_container_id' => 'GTM-TEST123',
            'analytics.destinations.ga4' => true,
            'analytics.destinations.yandex_metrica' => false,
            'analytics.destinations.hotjar' => false,
        ]);

        $policy = (string) $this->withMeasurementConsent()->get('/')->headers->get('Content-Security-Policy');

        self::assertStringContainsString('www.googletagmanager.com', $policy);
        self::assertStringContainsString('www.google-analytics.com', $policy);

        // Kapalı araçlar kapalı kalır: açık kaynak bir üründe her fazladan
        // izin, bir gün kimsenin hatırlamadığı bir izindir.
        self::assertStringNotContainsString('mc.yandex.ru', $policy);
        self::assertStringNotContainsString('hotjar', $policy);
    }

    public function test_enabling_metrica_opens_the_frame_directive_it_needs(): void
    {
        config([
            'analytics.gtm_container_id' => 'GTM-TEST123',
            'analytics.destinations.yandex_metrica' => true,
        ]);

        $policy = (string) $this->withMeasurementConsent()->get('/')->headers->get('Content-Security-Policy');

        // Webvisor kayıt için bir iframe açar; `frame-src` yönergesi bugün
        // CSP'de hiç yok ve `default-src 'self'`e düşerdi.
        self::assertMatchesRegularExpression('/frame-src[^;]*mc\.yandex\.ru/', $policy);
    }

    // --- Script hep nonce taşır -------------------------------------------

    public function test_the_measurement_script_carries_the_nonce_and_never_relaxes_inline_execution(): void
    {
        config(['analytics.gtm_container_id' => 'GTM-TEST123']);

        $response = $this->withMeasurementConsent()->get('/');
        $html = $response->getContent();
        $policy = (string) $response->headers->get('Content-Security-Policy');

        self::assertNotFalse($html);
        self::assertStringContainsString('googletagmanager.com/gtm.js', $html);
        self::assertMatchesRegularExpression('/<script nonce="[A-Za-z0-9]{24}"/', $html);

        // Ölçüm eklemek, CSP'nin XSS'e karşı koruduğu tek şeyi geri vermenin
        // gerekçesi DEĞİLDİR.
        self::assertStringNotContainsString("'unsafe-inline'", explode('style-src-attr', $policy)[0]);
        self::assertStringNotContainsString("'unsafe-eval'", $policy);
    }

    // --- Tenant alanı her zaman vardır ------------------------------------

    public function test_the_payload_always_carries_the_tenant_fields_even_when_the_surface_knows_nothing(): void
    {
        config(['analytics.gtm_container_id' => 'GTM-TEST123']);

        $payload = AnalyticsConfiguration::fromConfig()->dataLayerPayload([], 'en');

        // Alanın YOK olması ile BOŞ olması aynı şey değildir: olmayan alan
        // GTM'de "tanımsız" olur ve o olay tenant kırılımının dışına düşer.
        self::assertArrayHasKey('zabuno_tenant_id', $payload);
        self::assertArrayHasKey('zabuno_tenant_slug', $payload);
        self::assertSame('en', $payload['zabuno_locale']);
    }

    public function test_a_surface_context_overrides_the_empty_defaults(): void
    {
        config(['analytics.gtm_container_id' => 'GTM-TEST123']);

        $payload = AnalyticsConfiguration::fromConfig()->dataLayerPayload([
            'zabuno_surface' => 'menu',
            'zabuno_tenant_id' => '42',
        ], 'tr');

        self::assertSame('menu', $payload['zabuno_surface']);
        self::assertSame('42', $payload['zabuno_tenant_id']);
        self::assertSame('', $payload['zabuno_tenant_slug']);
    }

    // --- Tenant bağlamı GTM'den ÖNCE basılır ------------------------------

    public function test_the_tenant_context_is_pushed_before_the_container_loads(): void
    {
        config(['analytics.gtm_container_id' => 'GTM-TEST123']);

        $html = (string) $this->withMeasurementConsent()->get('/')->getContent();

        $contextAt = strpos($html, 'zabuno_tenant_id');
        $containerAt = strpos($html, 'googletagmanager.com/gtm.js');

        self::assertNotFalse($contextAt);
        self::assertNotFalse($containerAt);

        // Ters sırada, GTM'in gördüğü İLK olayın tenant alanı boş olurdu —
        // ve o olay her zaman aynıdır: ziyaretçinin gördüğü ilk sayfa.
        self::assertLessThan(
            $containerAt,
            $contextAt,
            'Tenant bağlamı konteynerden önce basılmalı.'
        );
    }
}
