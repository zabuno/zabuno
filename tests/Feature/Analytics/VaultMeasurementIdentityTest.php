<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Support\Analytics\MeasurementConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FF-220 — ÖLÇÜM KİMLİĞİ KASADAN GİRİLİR (`docs/135`).
 *
 * Sahibin isteği: *"Bir de GTM için alan aç entegrasyonlara."* Bugüne kadar
 * konteyner kimliğini girmenin tek yolu üretim sunucusuna SSH ile girip
 * `.env` düzenlemekti (`docs/126` §1) ve sahip bunu yapamaz.
 *
 * Bu dosya, dikişin sessizce kırılabileceği BEŞ yolu kapatır. Beşi de
 * ekranda hiçbir iz bırakmaz — sayfa açılır, konsolda hata yoktur, yalnız
 * raporlar boş kalır ya da sayfa hiç gelmez:
 *
 *   1. Kasa doldurulunca env'in önüne geçmemesi.
 *   2. Kasa boşken env'in çalışmayı bırakması (çalışan kurulumları kırar).
 *   3. Veritabanı okunamazken sayfanın ÇÖKMESİ.
 *   4. Panelden açılan bir hedefin CSP'ye yansımaması (önbellek bayat kalır).
 *   5. Kapalı uçlu bir alana anlamsız bir değerin sessizce yazılması.
 *
 * SAHİBİN GERÇEK KİMLİKLERİ BU DOSYADA YOKTUR ve olmamalıdır (`docs/126`
 * §1): onlar sahibin verisidir ve yalnız kasaya elle girilir. Buradaki
 * kimlikler bilerek uydurma ve tanınabilir biçimde sahtedir.
 */
final class VaultMeasurementIdentityTest extends TestCase
{
    use RefreshDatabase;

    private const VAULT_CONTAINER = 'GTM-VAULTFAKE';

    private const ENV_CONTAINER = 'GTM-ENVFAKE0';

    private function vault(): PlatformCredentialAdminPort
    {
        return $this->app->make(PlatformCredentialAdminPort::class);
    }

    /**
     * Konteyner onay olmadan sayfaya hiç girmez (FF-173); ölçümü ölçmek
     * için önce o kapının açık olması gerekir.
     */
    private function visit(string $path = '/'): TestResponse
    {
        return $this->withCookie(MeasurementConsent::COOKIE, 'granted')->get($path);
    }

    private function policy(string $path = '/'): string
    {
        return (string) $this->visit($path)->headers->get('Content-Security-Policy');
    }

    // --- 1. Kasa > env -----------------------------------------------------

    #[Test]
    public function the_container_id_entered_in_the_vault_wins_over_the_environment(): void
    {
        config(['analytics.gtm_container_id' => self::ENV_CONTAINER]);

        $this->vault()->put(
            CredentialProvider::GoogleTagManager,
            ['container_id' => self::VAULT_CONTAINER],
            null,
        );

        $html = (string) $this->visit()->getContent();

        self::assertStringContainsString(self::VAULT_CONTAINER, $html);
        // Env'in kimliği artık sayfada OLMAMALI: iki konteyner birden
        // yüklenseydi her olay iki kez sayılırdı ve kimse bunu fark etmezdi.
        self::assertStringNotContainsString(self::ENV_CONTAINER, $html);
    }

    // --- 2. Kasa boşken env çalışmaya DEVAM eder ---------------------------

    #[Test]
    public function the_environment_still_drives_measurement_while_the_vault_is_empty(): void
    {
        config(['analytics.gtm_container_id' => self::ENV_CONTAINER]);

        // Kasada tek bir ölçüm bağlantısı yok — bugünkü her dağıtımın hâli.
        self::assertFalse($this->vault()->status(CredentialProvider::GoogleTagManager)->configured);

        $html = (string) $this->visit()->getContent();

        self::assertStringContainsString(self::ENV_CONTAINER, $html);
        self::assertStringContainsString('googletagmanager.com/gtm.js', $html);
    }

    #[Test]
    public function an_environment_destination_survives_a_vault_that_only_carries_a_container(): void
    {
        /*
            KISMİ BİR KASA KAYDI, AÇIK BİR HEDEFİ KAPATMAZ.

            Sahip kasaya yalnız konteyner kimliğini girdiğinde, sunucunun
            `.env`'inde zaten açık olan GA4 sessizce kapansaydı, ölçüm
            "iyileştirme" yapılan gün DURURDU — ve bunu gösteren hiçbir
            hata olmazdı. Öncelik alan başınadır: kasada YAZILI olan kazanır,
            yazılmayan env'den gelir (`resolveRow()`).
        */
        config([
            'analytics.gtm_container_id' => self::ENV_CONTAINER,
            'analytics.destinations.ga4' => true,
        ]);

        $this->vault()->put(
            CredentialProvider::GoogleTagManager,
            ['container_id' => self::VAULT_CONTAINER],
            null,
        );

        $policy = $this->policy();

        self::assertStringContainsString('www.google-analytics.com', $policy);
    }

    // --- Hedefler CSP'yi açar; kapalı olan kapalı kalır --------------------

    #[Test]
    public function only_the_destination_opened_in_the_vault_is_allowed_through_the_policy(): void
    {
        config([
            'analytics.gtm_container_id' => '',
            'analytics.destinations.ga4' => false,
            'analytics.destinations.yandex_metrica' => false,
            'analytics.destinations.hotjar' => false,
        ]);

        $this->vault()->put(
            CredentialProvider::GoogleTagManager,
            ['container_id' => self::VAULT_CONTAINER, 'ga4' => 'on'],
            null,
        );

        $policy = $this->policy();

        self::assertStringContainsString('www.googletagmanager.com', $policy);
        self::assertStringContainsString('www.google-analytics.com', $policy);
        // Açılmayan araç kapalı KALIR: her fazladan izin, bir gün kimsenin
        // hatırlamadığı bir izindir.
        self::assertStringNotContainsString('mc.yandex.ru', $policy);
        self::assertStringNotContainsString('hotjar', $policy);
    }

    #[Test]
    public function a_container_without_any_destination_keeps_the_policy_as_strict_as_today(): void
    {
        config([
            'analytics.gtm_container_id' => '',
            'analytics.destinations.ga4' => false,
            'analytics.destinations.yandex_metrica' => false,
            'analytics.destinations.hotjar' => false,
        ]);

        $this->vault()->put(
            CredentialProvider::GoogleTagManager,
            ['container_id' => self::VAULT_CONTAINER],
            null,
        );

        $policy = $this->policy();

        self::assertStringNotContainsString('google-analytics.com', $policy);
        self::assertStringNotContainsString('mc.yandex.ru', $policy);
        self::assertStringNotContainsString('hotjar', $policy);
    }

    // --- 4. Önbellek geçersiz kılınır: yeniden başlatma GEREKMEZ -----------

    #[Test]
    public function opening_a_destination_reaches_the_policy_without_restarting_anything(): void
    {
        config([
            'analytics.gtm_container_id' => '',
            'analytics.destinations.ga4' => false,
        ]);

        $this->vault()->put(
            CredentialProvider::GoogleTagManager,
            ['container_id' => self::VAULT_CONTAINER, 'ga4' => 'off'],
            null,
        );

        // İlk istek çözülen değeri ÖNBELLEĞE alır — tuzağın kurulduğu an.
        self::assertStringNotContainsString('google-analytics.com', $this->policy());

        $this->vault()->put(
            CredentialProvider::GoogleTagManager,
            ['container_id' => self::VAULT_CONTAINER, 'ga4' => 'on'],
            null,
        );

        /*
            Bayat bir önbellek burada eski cevabı verirdi ve tek çözüm
            "sunucuyu yeniden başlat" olurdu. Kasadaki her mutasyon bir
            olay doğurur ve olay bu anahtarı düşürür.
        */
        self::assertStringContainsString('google-analytics.com', $this->policy());
    }

    // --- 3. Veritabanı okunamazken sayfa ÇİZİLMEYE DEVAM eder --------------

    #[Test]
    public function the_page_still_renders_and_measurement_stays_silently_off_without_the_vault_table(): void
    {
        /*
            Kurumsal site bilerek veritabanısız çizilebiliyor
            (`site:export-static`). Ölçüm okuması bir sayfa isteğini
            çökertemez: kasa okunamıyorsa ölçüm sessizce KAPALIDIR —
            bugünkü "kimlik yoksa ölçüm yok" davranışının aynısı.
        */
        config(['analytics.gtm_container_id' => '']);

        Schema::drop('platform_credential_connections');

        $response = $this->visit();

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);

        $policy = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("connect-src 'self'", $policy);
        self::assertStringNotContainsString('google-analytics.com', $policy);
    }

    #[Test]
    public function the_environment_keeps_measuring_even_when_the_vault_table_is_gone(): void
    {
        config(['analytics.gtm_container_id' => self::ENV_CONTAINER]);

        Schema::drop('platform_credential_connections');

        $response = $this->visit();

        $response->assertOk();
        self::assertStringContainsString(self::ENV_CONTAINER, (string) $response->getContent());
    }

    // --- 5. Kapalı uçlu alan anlamsız bir değeri KABUL ETMEZ ---------------

    #[Test]
    public function the_vault_refuses_a_destination_value_that_is_not_on_or_off(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->vault()->put(
            CredentialProvider::GoogleTagManager,
            ['container_id' => self::VAULT_CONTAINER, 'ga4' => 'evet'],
            null,
        );
    }

    // --- Onay şeridi de aynı kaynaktan beslenir ---------------------------

    #[Test]
    public function the_consent_strip_appears_when_the_identity_comes_only_from_the_vault(): void
    {
        /*
            ZİNCİRİN EN SESSİZ HALKASI.

            Konteyner açık bir kabul olmadan sayfaya hiç girmez (FF-173) ve o
            kabulü toplayan şerit yalnız "ölçüm yapılandırılmış" ise çıkar.
            Şerit env'e, konteyner kasaya baksaydı, sahip kimliği panelden
            girdiği gün şerit HİÇ çıkmazdı: onay alınamaz, konteyner
            yüklenmez, ölçüm "açıldı" sanılırken kapalı kalırdı — ve bunu
            gösteren tek bir hata olmazdı. İki okuma tek kaynaktan gelmek
            zorunda.
        */
        config(['analytics.gtm_container_id' => '']);

        // Onay çerezi YOK: karar verilmemiş bir ziyaretçi.
        self::assertStringNotContainsString('data-consent-banner', (string) $this->get('/')->getContent());

        $this->vault()->put(
            CredentialProvider::GoogleTagManager,
            ['container_id' => self::VAULT_CONTAINER],
            null,
        );

        self::assertStringContainsString('data-consent-banner', (string) $this->get('/')->getContent());
    }

    // --- Konteyner kimliği SIR DEĞİLDİR -----------------------------------

    #[Test]
    public function the_container_id_comes_back_in_full_because_it_is_not_a_secret(): void
    {
        /*
            GTM kimliği yüklenen script adresinde herkese görünür. Sır gibi
            saklamak, kasanın "önizleme gösterilemez" davranışını hiçbir şeyi
            korumadan uygular ve sahibin girdiğini gözle doğrulamasını
            engellerdi.
        */
        $this->vault()->put(
            CredentialProvider::GoogleTagManager,
            ['container_id' => self::VAULT_CONTAINER],
            null,
        );

        $status = $this->vault()->status(CredentialProvider::GoogleTagManager);
        $field = array_values(array_filter(
            $status->fields,
            static fn ($f): bool => $f->name === 'container_id',
        ))[0];

        self::assertTrue($status->configured);
        self::assertFalse($field->secret);
        self::assertSame(self::VAULT_CONTAINER, $field->preview);
        self::assertSame([], CredentialProvider::GoogleTagManager->secretFieldNames());
    }
}
