<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Support\Analytics\MeasurementConsent;
use Tests\TestCase;

/**
 * ONAY ALINMADAN ÖLÇÜM ÇALIŞMAZ — ve bu bir ekran kuralı değil, sunucu
 * kuralıdır.
 *
 * `modules/analytics-consent-tagging.md` modülün amacını "consent-gated
 * üçüncü taraf tag'leri yönetmek" diye yazıyordu ve `docs/46` §6 madde 9
 * bunu yapılmamış işaretliyordu. Modül envanteri (FF-162) ölçtü: kodda
 * **hiç** onay kapısı yoktu. Kural yazılıydı, uygulaması yoktu — bu deponun
 * tekrar eden kusur ailesinin (`docs/109` §8.7) hukuki sonucu olan biçimi.
 *
 * ═══ NEDEN "ÖNCE YÜKLE, SONRA KIS" YETMEZ ═══
 *
 * Consent Mode konteyneri yükleyip etiketleri sinyale göre kısar. Bu meşru
 * bir yaklaşımdır ama script yüklendiği anda üçüncü tarafa bir istek gitmiş
 * ve IP görülmüş olur. "Onay alınmadan ölçüm çalışmaz" cümlesini gerçekten
 * tutmanın tek yolu, karar verilene kadar konteyneri HİÇ yüklememektir.
 *
 * Bu test o cümleyi ölçer: sayfada konteyner script'inin adresi geçmemeli.
 * Bir gün biri kapıyı gevşetip "nasıl olsa Consent Mode kısıyor" derse,
 * burası kırılır.
 *
 * Requirement IDs: ANALYTICS-CONSENT-GATE-01.
 */
final class MeasurementConsentGateTest extends TestCase
{
    private const CONTAINER = 'GTM-TESTONLY';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.gtm_container_id', self::CONTAINER);
    }

    // --- ANALYTICS-CONSENT-GATE-01 ----------------------------------------

    public function test_without_a_decision_the_container_is_never_loaded(): void
    {
        $html = $this->get('/')->getContent();

        self::assertStringNotContainsString(
            'googletagmanager.com',
            (string) $html,
            'ANALYTICS-CONSENT-GATE-01: karar verilmeden üçüncü taraf script adresi sayfaya girmemeli — '
            .'script yüklendiği anda istek gitmiş ve IP görülmüş olur.'
        );

        self::assertStringNotContainsString(
            self::CONTAINER,
            (string) $html,
            'ANALYTICS-CONSENT-GATE-01: konteyner kimliği de sızmamalı.'
        );
    }

    /**
     * RET DE BİR KARARDIR VE KONTEYNERİ AÇMAZ.
     *
     * "Karar verildi" ile "kabul edildi" ayrı iki şeydir. İkisini
     * karıştırmak, reddeden kullanıcıyı ölçmek olurdu — yani onay
     * mekanizmasının varlığını onun aleyhine çevirmek.
     */
    public function test_an_explicit_refusal_keeps_the_container_out(): void
    {
        $html = $this->withCookie(MeasurementConsent::COOKIE, 'denied')
            ->get('/')
            ->getContent();

        self::assertStringNotContainsString('googletagmanager.com', (string) $html);
    }

    public function test_an_explicit_grant_loads_the_container(): void
    {
        $html = (string) $this->withCookie(MeasurementConsent::COOKIE, 'granted')
            ->get('/')
            ->getContent();

        self::assertStringContainsString(
            'googletagmanager.com',
            $html,
            'ANALYTICS-CONSENT-GATE-01: kabul edildiğinde ölçüm gerçekten çalışmalı — '
            .'kapı bir engel değil, bir karardır.'
        );
    }

    /**
     * SİNYAL, KONTEYNERDEN ÖNCE BASILIR.
     *
     * Konteyner yalnız kabulle yükleniyorsa bu satır neden gerekli? Çünkü
     * sahip yarın konteyner arayüzünden yeni bir etiket ekleyecek ve o
     * etiket bu depoyu hiç görmeyecek. Sinyal olmadan, eklenen her yeni
     * etiket bu kapının DIŞINDA kalırdı ve kapının bütün değeri o gün
     * kaybolurdu.
     */
    public function test_the_consent_signal_is_pushed_before_the_container(): void
    {
        $html = (string) $this->withCookie(MeasurementConsent::COOKIE, 'granted')
            ->get('/')
            ->getContent();

        $signal = strpos($html, "'consent', 'default'");
        $container = strpos($html, 'googletagmanager.com');

        self::assertNotFalse($signal, 'ANALYTICS-CONSENT-GATE-01: Consent Mode varsayılanı basılmalı.');
        self::assertNotFalse($container);
        self::assertLessThan(
            $container,
            $signal,
            'ANALYTICS-CONSENT-GATE-01: sinyal konteynerden SONRA basılırsa, aradaki etiketler sinyalsiz koşar.'
        );
    }

    /**
     * BOZUK BİR ÇEREZ DEĞERİ "SORULMADI" SAYILIR.
     *
     * Tanınmayan bir değere anlam yüklemek, kullanıcının vermediği bir
     * kararı uydurmaktır — ve bu uydurma her zaman ölçüm lehine olurdu.
     */
    public function test_an_unrecognised_cookie_value_is_treated_as_undecided(): void
    {
        $html = (string) $this->withCookie(MeasurementConsent::COOKIE, 'maybe')
            ->get('/')
            ->getContent();

        self::assertStringNotContainsString('googletagmanager.com', $html);
    }

    /**
     * GÜVENLİK EKSENİ ÖLÇÜM DEĞİLDİR.
     *
     * `security_storage` kötüye kullanım tespiti içindir; onu ölçüm
     * onayına bağlamak, reddeden kullanıcıyı savunmasız bırakırdı.
     */
    public function test_the_security_axis_is_not_gated_by_measurement_consent(): void
    {
        $defaults = MeasurementConsent::denied()->consentModeDefaults();

        self::assertSame('granted', $defaults['security_storage']);
        self::assertSame('denied', $defaults['analytics_storage']);
        self::assertSame('denied', $defaults['ad_storage']);
    }
}
