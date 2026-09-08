<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Application\Legal\Port\SubprocessorRegistryPort;
use App\Application\Legal\Port\ThirdPartyLicensePort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\ServiceLevelCommitment;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialStatus;
use App\Infrastructure\Legal\MeasuredSubprocessors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * CONTRACTS-01…10 — kurumsal sözleşmeler (FF-228, `docs/107` Faz 3.2,
 * `docs/140`).
 *
 * ÜRÜN SORUNU. Bir zincirin satın alma ya da hukuk birimi dört şey sorar ve
 * bugüne kadar dördünün de cevabı yoktu: *verimizi kimler işliyor* (DPA),
 * *ne kadar ayakta kalacağını taahhüt ediyorsunuz* (SLA), *neyi yasaklıyor
 * ve ihlalde ne yapıyorsunuz* (AUP), *bu yazılım kimin kodunu taşıyor*
 * (lisanslar). Cevapsız bir soru, satın alma sürecinde bir "hayır"dır.
 *
 * BU PAKETİN SINAVI UYDURMAMAKTIR. Alt işleyen listesi ÖLÇÜLÜR, lisans
 * listesi TÜRETİLİR, hizmet seviyesi rakamları ise yoktur ve sayfa bunu
 * söyler. Aşağıdaki testler o üç sözü tek tek ölçer; bir gün biri bozulursa
 * kırılır.
 */
final class CorporateContractsTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{0:string,1:string}> */
    public static function contractPages(): array
    {
        return [
            ['/data-processing', 'data-processing'],
            ['/sla', 'sla'],
            ['/acceptable-use', 'acceptable-use'],
            ['/third-party-licenses', 'third-party-licenses'],
        ];
    }

    private function withCompany(): void
    {
        config([
            'legal.company' => [
                'legal_name' => 'Örnek Yazılım A.Ş.',
                'address' => 'Örnek Mah. 1, İstanbul',
                'mersis' => '0123456789012345',
                'tax_office' => 'Kadıköy',
                'tax_number' => '1234567890',
                'email' => 'legal@example.test',
                'phone' => '+90 212 000 00 00',
            ],
        ]);
    }

    /** @param  array<string, mixed>  $values */
    private function withSla(array $values): void
    {
        config(['sla' => $values]);
    }

    private function text(string $key): string
    {
        $document = app(LegalLibraryPort::class)->find($key);

        self::assertInstanceOf(LegalDocument::class, $document, "[{$key}] kütüphanede yok.");

        $paragraphs = [$document->title, $document->summary];

        foreach ($document->sections as $section) {
            $paragraphs[] = $section->heading;

            foreach ($section->paragraphs as $paragraph) {
                $paragraphs[] = $paragraph;
            }
        }

        return implode("\n", $paragraphs);
    }

    // --- CONTRACTS-01: dört belge de rotasından yayında ------------------

    #[DataProvider('contractPages')]
    public function test_each_corporate_contract_is_published_at_its_own_route(string $path, string $key): void
    {
        $this->withCompany();

        $html = (string) $this->get($path)->assertOk()->getContent();

        self::assertStringContainsString('data-legal-document="'.$key.'"', $html);
        self::assertStringContainsString('data-legal-version="0.1"', $html);
        // İnceleme notu KALDIRILMADI: bu metinler bir hukukçunun üzerine
        // çalışacağı taslaktır ve sayfa bunu söylemeye devam eder.
        self::assertStringContainsString('data-legal-review="pending"', $html);
    }

    /**
     * Kütüphaneye eklenip rotası unutulan bir belge, hiçbir uyarı vermeden
     * 404 döner ve altbilgiden ona bağlantı verilir. Tek liste
     * (`LegalLibraryPort::KEYS`) bunu imkânsız kılar; bu test o tekliği ölçer.
     */
    public function test_every_key_the_library_knows_has_a_route_that_answers(): void
    {
        $this->withCompany();

        foreach (LegalLibraryPort::KEYS as $key) {
            $this->get('/'.$key)->assertOk();
            self::assertNotNull(app(LegalLibraryPort::class)->find($key), "[{$key}] rotası var ama belgesi yok.");
        }
    }

    // --- CONTRACTS-02: alt işleyen listesi ÖLÇÜLÜR ------------------------

    /**
     * Kasa boş ve ölçüm kapalıyken listede YALNIZ barındırma vardır.
     *
     * Bu, listenin elle yazılmadığının en ucuz kanıtı: elle yazılmış bir
     * liste Mailgun ve Iyzico'yu her koşulda sayardı — bu dağıtımda ikisi de
     * yapılandırılmamış olsa bile.
     */
    public function test_the_subprocessor_list_names_only_what_is_actually_configured(): void
    {
        config([
            'services.mailgun.domain' => null,
            'services.mailgun.secret' => null,
            'services.iyzico.sandbox.api_key' => null,
            'services.iyzico.sandbox.secret_key' => null,
            'analytics.gtm_container_id' => '',
        ]);

        $inventory = app(SubprocessorRegistryPort::class)->inventory();
        $names = array_map(static fn ($subprocessor): string => $subprocessor->name, $inventory->active);

        self::assertSame(['netcup GmbH'], $names, 'CONTRACTS-02: kasa boşken barındırmadan başka bir alt işleyen sayılmamalı.');

        $text = $this->text('data-processing');

        self::assertStringContainsString('netcup GmbH', $text);
        self::assertStringNotContainsString('Mailgun', $text, 'CONTRACTS-02: yapılandırılmamış bir sağlayıcı listede.');
        self::assertStringNotContainsString('Google Analytics 4', $text);
        // Bir sözleşme metninde ".." bir dizgi hatasıdır ve okuyucuya
        // üretilmiş bir cümle olduğunu ele verir.
        self::assertStringNotContainsString('..', $text, 'CONTRACTS-02: üretilen satırda çift nokta var.');
    }

    public function test_an_env_configured_mail_provider_appears_in_the_list(): void
    {
        config([
            'services.mailgun.domain' => 'mail.example.test',
            'services.mailgun.secret' => 'key-not-a-real-secret',
            'analytics.gtm_container_id' => '',
        ]);

        self::assertStringContainsString('Mailgun', $this->text('data-processing'));
    }

    public function test_measurement_tools_appear_only_when_the_container_and_the_destination_are_both_on(): void
    {
        config(['analytics.gtm_container_id' => '', 'analytics.destinations.ga4' => true]);
        self::assertStringNotContainsString('Google Analytics 4', $this->text('data-processing'));

        $this->refreshApplication();

        config([
            'analytics.gtm_container_id' => 'GTM-TEST123',
            'analytics.destinations' => ['ga4' => true, 'yandex_metrica' => false, 'hotjar' => false],
        ]);

        $text = $this->text('data-processing');

        self::assertStringContainsString('Google Tag Manager', $text);
        self::assertStringContainsString('Google Analytics 4', $text);
        self::assertStringNotContainsString('Yandex Metrica', $text);
    }

    // --- CONTRACTS-03: liste ESKİMEZ, çünkü eskiyeceği gün CI kırılır -----

    /**
     * Kasaya bir sağlayıcı eklendiği gün bu test kırılır.
     *
     * `MeasuredSubprocessors::describe()` varsayılansız bir `match`tir; yeni
     * bir `CredentialProvider` case'i tarif edilmeden eklenirse burada
     * `UnhandledMatchError` fırlar ve belge tarif edilmemiş bir sağlayıcıyla
     * yayına çıkamaz.
     */
    public function test_every_credential_provider_is_described_for_the_subprocessor_list(): void
    {
        config(['analytics.gtm_container_id' => '']);

        foreach (CredentialProvider::cases() as $provider) {
            $this->refreshApplication();
            config(['analytics.gtm_container_id' => '']);

            $this->app->bind(PlatformCredentialAdminPort::class, fn (): object => new class($provider) implements PlatformCredentialAdminPort
            {
                public function __construct(private readonly CredentialProvider $provider) {}

                public function all(): array
                {
                    return [new CredentialStatus($this->provider, true, 'active', [])];
                }

                public function status(CredentialProvider $provider): CredentialStatus
                {
                    return new CredentialStatus($provider, false, 'unset', []);
                }

                public function put(CredentialProvider $provider, array $values, ?int $byUserId): void {}

                public function disable(CredentialProvider $provider, ?int $byUserId = null): void {}
            });

            $inventory = app(SubprocessorRegistryPort::class)->inventory();

            self::assertCount(
                2,
                $inventory->active,
                "CONTRACTS-03: [{$provider->value}] etkinken alt işleyen listesinde bir satır üretmiyor.",
            );
        }
    }

    /**
     * Ölçüm konteynerine yeni bir araç eklendiği gün de aynı şey olur.
     *
     * `config/analytics.php#destinations` bir araç için CSP kaynağı
     * tanımlanmasını gerektirir; o gün burada bir ad tanımlanmazsa araç
     * sessizce alt işleyen listesinin DIŞINDA kalırdı.
     */
    public function test_every_configured_measurement_destination_has_a_name_in_the_subprocessor_list(): void
    {
        foreach (array_keys((array) config('analytics.destinations', [])) as $key) {
            self::assertArrayHasKey(
                (string) $key,
                MeasuredSubprocessors::MEASUREMENT_TOOL_NAMES,
                "CONTRACTS-03: ölçüm hedefi [{$key}] alt işleyen listesinde adsız.",
            );
        }
    }

    /**
     * Kasa okunamadığında belge SUSMAZ.
     *
     * Boş bir liste "başka kimse yok" diye okunur; bir veri işleme
     * sözleşmesinde söylenebilecek en yanlış cümle budur.
     */
    public function test_an_unreadable_credential_store_is_said_out_loud_and_not_shown_as_an_empty_list(): void
    {
        $this->app->bind(PlatformCredentialAdminPort::class, fn (): object => new class implements PlatformCredentialAdminPort
        {
            public function all(): array
            {
                throw new RuntimeException('vault down');
            }

            public function status(CredentialProvider $provider): CredentialStatus
            {
                throw new RuntimeException('vault down');
            }

            public function put(CredentialProvider $provider, array $values, ?int $byUserId): void {}

            public function disable(CredentialProvider $provider, ?int $byUserId = null): void {}
        });

        $inventory = app(SubprocessorRegistryPort::class)->inventory();

        self::assertTrue($inventory->vaultUnreadable);
        self::assertStringContainsString('could not be read', $this->text('data-processing'));
    }

    // --- CONTRACTS-04: yedek dürüstlüğü ----------------------------------

    /**
     * `docs/124` §7.4 ve §8 ölçtü: kopya AYNI SUNUCUDA, sunucu dışına kopya
     * yok. Bir DPA'da "yedekler ayrı bir konumda saklanır" cümlesi, bir
     * kesinti gününde ortaya çıkacak bir yalandır.
     */
    public function test_the_dpa_says_backups_are_on_the_same_server_and_never_claims_an_off_site_copy(): void
    {
        $text = strtolower($this->text('data-processing'));

        self::assertStringContainsString('backups are kept on the same server', $text);
        self::assertStringContainsString('no copy outside that server', $text);

        foreach (['off-site', 'offsite', 'separate location', 'geographically', 'point-in-time recovery is available'] as $claim) {
            self::assertStringNotContainsString($claim, $text, "CONTRACTS-04: [{$claim}] — ölçülmemiş bir yedek iddiası.");
        }
    }

    public function test_no_corporate_contract_claims_a_certification_that_does_not_exist(): void
    {
        $text = strtolower($this->text('data-processing'));

        // Sertifika YOKTUR ve belge bunu olumsuz cümleyle söyler.
        self::assertStringContainsString('there is no iso 27001 certificate', $text);
        self::assertStringContainsString('no external security audit', $text);
    }

    // --- CONTRACTS-05: SLA rakamsızken TAAHHÜT GİBİ GÖRÜNMEZ -------------

    public function test_the_service_level_page_carries_no_figure_at_all_while_the_owner_has_not_decided(): void
    {
        $this->withSla([
            'availability_target_percent' => null,
            'measurement_source' => null,
            'incident_notification_hours' => null,
            'service_credit_percent' => null,
        ]);

        $text = $this->text('sla');

        self::assertStringNotContainsString('%', $text, 'CONTRACTS-05: rakam yokken sayfada yüzde işareti var.');
        self::assertStringContainsString('no availability percentage', strtolower($text));
        // Eksik olan alanlar ADIYLA sayılır: sahip hangi anahtarı
        // dolduracağını sayfadan okuyabilmeli.
        foreach (ServiceLevelCommitment::FIELDS as $field) {
            self::assertStringContainsString($field, $text, "CONTRACTS-05: [{$field}] eksikler listesinde adıyla yok.");
        }
    }

    /**
     * Yarım bir yapılandırma "kısmi taahhüt" üretmez.
     *
     * Üç değer girilip ölçüm kaynağı boş bırakıldığında sayfa hâlâ hiçbir
     * rakam göstermez: doğrulanamayan bir oran bir taahhüt değildir.
     */
    public function test_three_values_out_of_four_still_commit_nothing(): void
    {
        $this->withSla([
            'availability_target_percent' => '99.5',
            'measurement_source' => null,
            'incident_notification_hours' => '4',
            'service_credit_percent' => '10',
        ]);

        $text = $this->text('sla');

        self::assertStringNotContainsString('99.5', $text);
        self::assertStringNotContainsString('%', $text);
        self::assertStringContainsString('measurement_source', $text);
    }

    public function test_a_typo_is_not_a_commitment(): void
    {
        $this->withSla([
            'availability_target_percent' => 'yes',
            'measurement_source' => '   ',
            'incident_notification_hours' => '0',
            'service_credit_percent' => '400',
        ]);

        self::assertSame(ServiceLevelCommitment::FIELDS, ServiceLevelCommitment::fromConfig()->missing());
        self::assertStringNotContainsString('%', $this->text('sla'));
    }

    public function test_the_figures_appear_exactly_as_configured_once_all_four_are_decided(): void
    {
        $this->withSla([
            'availability_target_percent' => '99.5',
            'measurement_source' => 'https://status.example.test',
            'incident_notification_hours' => '4',
            'service_credit_percent' => '10',
        ]);

        $text = $this->text('sla');

        self::assertStringContainsString('at least 99.5% of each calendar month', $text);
        self::assertStringContainsString('https://status.example.test', $text);
        self::assertStringContainsString('within 4 hours', $text);
        self::assertStringContainsString('service credit of 10%', $text);
        self::assertStringNotContainsString('no availability percentage', strtolower($text));
    }

    // --- CONTRACTS-06: lisans listesi TÜRETİLİR --------------------------

    /**
     * `composer.json` içindeki her doğrudan bağımlılık sayfada ADIYLA
     * görünür. Elle yazılmış bir liste bu testi ancak elle güncellenerek
     * geçebilirdi; türetilen liste her zaman geçer.
     */
    public function test_every_direct_composer_dependency_is_named_with_its_licence(): void
    {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $text = $this->text('third-party-licenses');

        $named = 0;

        foreach (array_keys((array) ($manifest['require'] ?? [])) as $package) {
            $package = (string) $package;

            if ($package === 'php' || str_starts_with($package, 'ext-')) {
                /*
                    Dil ve eklenti bir üçüncü taraf paket değildir ve kendi
                    satırını almaz. Arama SATIR BAŞINDAN yapılır: düz bir
                    "php " araması `iyzico/iyzipay-php 2.0.61` satırına da
                    takılırdı (ölçüldü).
                */
                self::assertStringNotContainsString("\n".$package.' ', $text);

                continue;
            }

            self::assertStringContainsString($package, $text, "CONTRACTS-06: [{$package}] lisans sayfasında yok.");
            $named++;
        }

        self::assertGreaterThan(5, $named, 'CONTRACTS-06: liste şüpheli derecede kısa — türetme çalışmıyor olabilir.');
    }

    public function test_direct_npm_dependencies_are_named_and_dev_tooling_is_not(): void
    {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents(base_path('package.json')), true);
        $text = $this->text('third-party-licenses');

        foreach (array_keys((array) ($manifest['dependencies'] ?? [])) as $package) {
            self::assertStringContainsString((string) $package, $text, "CONTRACTS-06: [{$package}] lisans sayfasında yok.");
        }

        // Derleme aracı dağıtılmaz: listede olmaması bir eksiklik değil, bir
        // karardır — ve sayfa o kararı yazıyor.
        foreach (['vitest', 'eslint', 'storybook', 'typescript'] as $devOnly) {
            self::assertStringNotContainsString("\n".$devOnly.' ', $text, "CONTRACTS-06: [{$devOnly}] dağıtılmayan bir araç ve listede olmamalı.");
        }

        // Aynı paket iki kez listelenmez: iç içe kurulmuş bir kopya doğrudan
        // bağımlılık değildir.
        $names = array_map(
            static fn ($package): string => $package->name,
            app(ThirdPartyLicensePort::class)->inventories()[1]->packages,
        );
        self::assertSame(array_values(array_unique($names)), $names, 'CONTRACTS-06: aynı paket birden çok kez listelenmiş.');

        self::assertStringContainsString('is not listed', $text);
    }

    /**
     * Dolaylı paketler SAYILIR ama listelenmez — ve sayı bir olgudur.
     */
    public function test_indirect_packages_are_counted_rather_than_described_as_many(): void
    {
        $inventories = app(ThirdPartyLicensePort::class)->inventories();

        self::assertCount(2, $inventories);

        foreach ($inventories as $inventory) {
            self::assertTrue($inventory->readable, "[{$inventory->ecosystem}] manifesti okunamadı.");
            self::assertGreaterThan(0, $inventory->transitiveCount, "[{$inventory->ecosystem}] dolaylı paket sayısı sıfır görünüyor.");
            self::assertNotSame([], $inventory->packages);
        }

        self::assertStringContainsString('further packages are pulled in indirectly', $this->text('third-party-licenses'));
    }

    // --- CONTRACTS-07: AUP yalnız YAPABİLDİĞİ yaptırımı yazar ------------

    /**
     * `WorkspaceState::Suspended` enum'da var ama onu YAZAN hiçbir yüzey yok
     * (ölçüldü). Panelde bir "askıya al" düğmesi varmış gibi yazan bir
     * politika, ihlal gününde uygulanamaz.
     */
    public function test_the_acceptable_use_policy_only_claims_enforcement_the_product_can_perform(): void
    {
        $text = $this->text('acceptable-use');

        self::assertStringContainsString('Public addresses are rate limited', $text);
        self::assertStringContainsString('quarantined and scanned', $text);
        self::assertStringContainsString('do not today carry a one-click suspension', $text);
        self::assertStringContainsString('There is no automatic content moderation', $text);
        self::assertStringContainsString('no bug bounty programme', $text);
    }

    /**
     * AUP ne bir yanıt süresi ne de bir iade yaratır: ikisi de başka
     * belgelerin konusudur ve iki yerde yazılan bir kural bir gün ayrışır.
     */
    public function test_the_acceptable_use_policy_does_not_invent_a_refund_or_a_response_time(): void
    {
        $text = strtolower($this->text('acceptable-use'));

        self::assertStringContainsString('governed by the cancellation and refund policy', $text);

        foreach (['within 24 hours', 'within 48 hours', 'business days'] as $claim) {
            self::assertStringNotContainsString($claim, $text);
        }
    }

    // --- CONTRACTS-08: 320 pikselde okunabilir uzun metin ----------------

    /**
     * Uzun bir hukuk metni dar ekranda okunamazsa yayınlanmamış sayılır.
     *
     * Gerçek düzen ölçümü `scripts/mobile-ux-audit` işidir (jsdom düzen
     * hesaplamaz); burada ölçülen, o ölçümün dayandığı YAPININ yerinde
     * olması: tek şablon, tek `main`, bölüm çıpaları ve sayfa içi liste.
     */
    #[DataProvider('contractPages')]
    public function test_a_long_contract_keeps_the_shared_narrow_screen_structure(string $path, string $key): void
    {
        $this->withCompany();

        $html = (string) $this->get($path)->assertOk()->getContent();
        $document = app(LegalLibraryPort::class)->find($key);

        self::assertNotNull($document);
        self::assertSame(1, preg_match_all('#<h1\b#', $html));
        self::assertStringContainsString('class="site-legal"', $html);
        self::assertStringContainsString('data-legal-contents', $html);
        self::assertGreaterThanOrEqual(6, count($document->sections), "[{$key}] kurumsal bir sözleşme için fazla kısa.");

        foreach ($document->sections as $index => $section) {
            self::assertStringContainsString('id="section-'.($index + 1).'"', $html);
        }
    }
}
