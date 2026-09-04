<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use Tests\TestCase;

/**
 * FF-99 — kamu sayfalarının ölçümü (`docs/100` Faz 3, L3).
 *
 * ÜRÜN SORUNU. Bütün kamu trafiği tek bir "marketing" yüzeyi olarak akıyordu:
 * hangi sayfanın okunduğu raporda yoktu ve "fiyatı okuyanların kaçı iletişime
 * geçti" sorusu cevaplanamıyordu. Ölçülemeyen bir huni, huni değildir.
 *
 * Requirement ID'leri: PUB-MEASURE-PAGE-01, PUB-MEASURE-EVENTS-02,
 * PUB-MEASURE-NO-PII-03.
 */
final class PublicMeasurementTest extends TestCase
{
    private function withAnalytics(): void
    {
        // Ölçüm, kap kimliği VARSA açıktır: ayrı bir "enabled" bayrağı yok
        // ve olmaması doğru — kimliksiz bir kap zaten hiçbir şey gönderemez.
        config(['analytics.gtm_container_id' => 'GTM-TEST123']);
    }

    // --- PUB-MEASURE-PAGE-01 ----------------------------------------------

    public function test_every_public_page_declares_which_page_it_is(): void
    {
        $this->withAnalytics();

        $expected = [
            '/' => 'home',
            '/pricing' => 'pricing',
            '/terms' => 'legal_terms',
            '/privacy' => 'legal_privacy',
            '/kvkk' => 'legal_kvkk',
            '/contact' => 'contact',
        ];

        foreach ($expected as $path => $pageKey) {
            $response = $this->get($path);

            $response->assertStatus(200);
            $response->assertSee('"zabuno_page":"'.$pageKey.'"', false);
        }
    }

    // --- PUB-MEASURE-EVENTS-02 --------------------------------------------

    public function test_the_pricing_page_reports_that_the_price_was_read(): void
    {
        $this->withAnalytics();

        $this->get('/pricing')
            ->assertStatus(200)
            // Olay SAYFA AÇILIŞINDA: fiyatı okumak bir tıklama gerektirmez.
            ->assertSee("send('pricing_viewed')", false);
    }

    public function test_the_register_and_contact_conversions_are_wired(): void
    {
        $this->withAnalytics();

        $home = $this->get('/');
        $home->assertStatus(200);
        $home->assertSee("send('register_started')", false);

        $contact = $this->get('/contact');
        $contact->assertStatus(200);
        $contact->assertSee("send('contact_sent')", false);
        // Form gönderimi dinlenir; "sayfayı gördü" ile "yazdı" aynı şey değildir.
        $contact->assertSee('form[action="/contact"]', false);
    }

    // --- PUB-MEASURE-NO-PII-03 --------------------------------------------

    public function test_the_measurement_seam_never_carries_personal_data(): void
    {
        $this->withAnalytics();

        $seam = (string) file_get_contents(
            resource_path('views/public/partials/measurement.blade.php')
        );

        /*
            `dataLayer` içeriği GTM üzerinden üçüncü taraflara akar ve oraya
            giren veri geri alınamaz (`docs/46` §4). Bu yüzden ölçüm dikişi
            form alanlarına HİÇ dokunmamalıdır — bir gün "hangi e-posta yazdı"
            eklemek isteyen biri bu testi kırar ve kararı bilerek verir.
        */
        foreach (['value', 'email', 'input', 'FormData'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $seam,
                "PUB-MEASURE-NO-PII-03: ölçüm dikişi \"{$forbidden}\" okuyor; kişisel veri dataLayer'a giremez."
            );
        }
    }

    public function test_measurement_stays_out_when_analytics_is_disabled(): void
    {
        config(['analytics.gtm_container_id' => '']);

        /*
            Ölçüm kapalıyken TEK BİR SATIR bile gönderilmez. Kapalı bir aracın
            yine de olay biriktirmesi, kapatma kararını anlamsız kılardı.
        */
        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee("send('register_started')", false);
    }
}
