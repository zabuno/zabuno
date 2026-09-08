<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Application\Assurance\Port\AssuranceLibraryPort;
use App\Application\Legal\Port\SubprocessorRegistryPort;
use App\Console\Commands\ExportStaticSiteCommand;
use App\Domain\Assurance\ClaimState;
use App\Domain\Legal\ServiceLevelCommitment;
use App\Http\Controllers\PublicSite\ShowAccessibilityStatementController;
use App\Http\Controllers\PublicSite\ShowTrustCentreController;
use App\Infrastructure\Legal\Documents\ServiceLevelTerms;
use App\Support\Site\SiteNavigation;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * GÜVENCE-01…09 — güven merkezi ve erişilebilirlik beyanı (FF-252,
 * `docs/107` Faz 3).
 *
 * ── ÜRÜN SORUNU ─────────────────────────────────────────────────────────
 *
 * Bir zincirin satın alma birimi bu ürünü değerlendirirken üç şey sorar:
 * *verim nerede duruyor, kim dokunuyor, ne taahhüt ediyorsunuz.* Cevapların
 * hepsi depoda vardı — alt işleyen listesi kasadan ölçülüyor, hizmet
 * seviyesi yapılandırmadan geliyor, yedek tatbikatı bir komut — ama hiçbiri
 * bir sayfada toplanmamıştı. Toplanmamış bir cevap, o birim için YOK
 * demektir.
 *
 * ── BU DOSYA NE ÖLÇÜYOR ─────────────────────────────────────────────────
 *
 * Sayfaların açıldığını, ÇİZİLDİKLERİ KAYNAĞIN ölçülen kaynak olduğunu ve
 * kayıt sürücülü zincire (gezinti, altbilgi, sitemap, statik önizleme)
 * gerçekten bağlandıklarını. Dürüstlük kuralları ayrı bir dosyada
 * (`AssuranceHonestyGateTest`), çünkü orası kırıldığında sebebi bir bağlantı
 * değil bir CÜMLE olur ve ikisini aynı dosyada toplamak hangi kusurun
 * hangisi olduğunu bulanıklaştırırdı.
 *
 * Requirement ID'leri: GÜVENCE-01…09.
 */
final class AssurancePagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{0:string,1:string}> yol → beyan anahtarı */
    public static function assurancePages(): array
    {
        return [
            ['/trust', 'trust'],
            ['/accessibility', 'accessibility'],
        ];
    }

    private function xpath(string $uri): DOMXPath
    {
        $response = $this->get($uri);
        $response->assertOk();

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.((string) $response->getContent()));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    // --- GÜVENCE-01 --------------------------------------------------------

    #[DataProvider('assurancePages')]
    public function test_the_page_opens_and_says_which_statement_it_is(string $path, string $key): void
    {
        $xpath = $this->xpath($path);

        $main = $xpath->query('//main[@data-assurance-statement]');
        self::assertNotFalse($main);
        self::assertSame(
            1,
            $main->length,
            "GÜVENCE-01: [{$path}] bir güvence beyanı olduğunu işaretlemiyor; kapılar sayfayı tanıyamaz.",
        );
        self::assertSame($key, $main->item(0)?->attributes?->getNamedItem('data-assurance-statement')?->nodeValue);
    }

    // --- GÜVENCE-02 --------------------------------------------------------

    #[DataProvider('assurancePages')]
    public function test_every_claim_states_its_kind_in_words_not_only_in_colour(string $path): void
    {
        $xpath = $this->xpath($path);

        $claims = $xpath->query('//*[@data-claim-state]');
        self::assertNotFalse($claims);
        self::assertGreaterThan(0, $claims->length, "GÜVENCE-02: [{$path}] hiç iddia çizmiyor.");

        $labels = [];

        foreach (ClaimState::cases() as $state) {
            $labels[$state->value] = true;
        }

        foreach ($claims as $claim) {
            $state = $claim->attributes?->getNamedItem('data-claim-state')?->nodeValue;

            self::assertArrayHasKey(
                (string) $state,
                $labels,
                "GÜVENCE-02: [{$path}] tanınmayan bir iddia hâli çiziyor: {$state}.",
            );

            /*
                HÂL KELİMEYLE YAZILI MI?

                Rengi göremeyen bir okuyucu için tek başına bir kenar rengi
                hiçbir şey demez — ve bunu erişilebilirlik beyanı olan bir
                sayfada söyleyip yapmamak, sayfanın kendisini çürütürdü.
            */
            $words = $xpath->query('.//*[contains(concat(" ", @class, " "), " site-claim-state ")]', $claim);
            self::assertNotFalse($words);
            self::assertSame(
                1,
                $words->length,
                "GÜVENCE-02: [{$path}] bir iddianın hâli yalnız öznitelikte duruyor, kelimeyle yazılmıyor.",
            );
            self::assertNotSame('', trim((string) $words->item(0)?->textContent));
        }
    }

    // --- GÜVENCE-03 --------------------------------------------------------

    public function test_the_trust_centre_draws_the_measured_subprocessor_list_rather_than_writing_a_second_one(): void
    {
        /*
            KASADA HİÇBİR SAĞLAYICI OLMASA BİLE barındırma satırı vardır:
            `MeasuredSubprocessors` onu envanterin başına koyar. Sayfa bu
            cümleyi ÇİZMELİ — kendi sözcükleriyle yeniden yazarsa iki liste
            doğar ve ikisi bir gün ayrışır.
        */
        $inventory = app(SubprocessorRegistryPort::class)->inventory();

        self::assertNotSame([], $inventory->active, 'Envanter boş dönemez: barındırma her zaman ilk satırdır.');

        $body = (string) $this->get('/trust')->assertOk()->getContent();

        foreach ($inventory->active as $subprocessor) {
            self::assertStringContainsString(
                htmlspecialchars($subprocessor->sentence(), ENT_QUOTES),
                $body,
                'GÜVENCE-03: güven merkezi alt işleyen satırını olduğu gibi çizmiyor — '.
                'kendi kopyasını yazan bir sayfa, DPA ile bir gün ayrışır.',
            );
        }
    }

    // --- GÜVENCE-04 --------------------------------------------------------

    public function test_the_trust_centre_shows_the_service_credit_sentence_it_would_be_easiest_to_hide(): void
    {
        /*
            SAHİBİN KARARI SIFIR (`docs/140`): hedef tutmadığında hizmet
            kredisi ÖDENMEZ, çare iptal etmektir. Gizlenen bir sınır, güven
            merkezinin kendisini çürütür — bu yüzden cümle sayfada açıkça
            görünür ve cümlenin kendisi sözleşmeden gelir.
        */
        config(['sla.service_credit_percent' => '0']);

        $sentence = ServiceLevelTerms::creditSentence(
            ServiceLevelCommitment::fromConfig(),
        );

        self::assertStringContainsString('no service credit is paid', $sentence);
        self::assertStringContainsString('your remedy is to cancel', $sentence);

        $this->get('/trust')
            ->assertOk()
            ->assertSee($sentence, escape: true);
    }

    // --- GÜVENCE-05 --------------------------------------------------------

    public function test_an_entered_service_credit_is_shown_with_its_figure(): void
    {
        // Bir gün sahip bir oran verirse sayfa onu SAKLAMAZ da, uydurmaz da.
        config(['sla.service_credit_percent' => '10']);

        $this->get('/trust')
            ->assertOk()
            ->assertSee('a service credit of 10%', escape: true);
    }

    // --- GÜVENCE-06 --------------------------------------------------------

    public function test_the_availability_figure_appears_only_when_all_four_values_are_set(): void
    {
        /*
            DÖRDÜ BİRLİKTE YA DA HİÇBİRİ (`ServiceLevelCommitment`). Üçü dolu
            biri boşken bir oran göstermek, okuyucuya tam bir taahhüt varmış
            izlenimi verirdi.
        */
        config([
            'sla.availability_target_percent' => '99.8',
            'sla.measurement_source' => null,
            'sla.incident_notification_minutes' => '60',
            'sla.service_credit_percent' => '0',
        ]);

        $this->get('/trust')
            ->assertOk()
            ->assertDontSee('99.8%', escape: true)
            ->assertSee('No availability percentage is committed today', escape: true)
            ->assertSee('measurement_source', escape: true);

        config(['sla.measurement_source' => 'https://status.example.test']);

        $this->get('/trust')
            ->assertOk()
            ->assertSee('99.8% availability per calendar month', escape: true);
    }

    // --- GÜVENCE-07 --------------------------------------------------------

    #[DataProvider('assurancePages')]
    public function test_the_page_is_reachable_from_the_footer_and_from_the_sitemap(string $path): void
    {
        /*
            ELLE YAZILMIŞ, 404 VEREN BAĞLANTI BIRAKMA.

            Gezintinin bildirdiği hedef ile sitemap'in ilan ettiği adres ve
            ziyaretçinin aldığı HTTP kodu aynı cevabı vermek zorunda; üçünü
            ayrı ayrı ölçmek, bir gün birinin ötekilerden ayrılmasıdır.
        */
        self::assertContains(
            $path,
            app(SiteNavigation::class)->declaredTargets(),
            "GÜVENCE-07: [{$path}] gezintinin bildirdiği hedefler arasında yok.",
        );

        $this->get('/about')
            ->assertOk()
            ->assertSee('href="'.$path.'"', escape: false);

        /*
            KÖK ADRES ARANMAZ, YOLUN KENDİSİ ARANIR: `app.url` bir
            yapılandırmadır ve bu yazılım tek bir alan adına ait değil
            (`SAAS-DOMAIN`). Testin bir alan adı varsayması, kuralı değil
            kurulumu ölçmek olurdu.
        */
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee($path.'</loc>', escape: false);
    }

    // --- GÜVENCE-08 --------------------------------------------------------

    #[DataProvider('assurancePages')]
    public function test_the_top_level_address_is_reserved_against_a_business_slug(string $path): void
    {
        /*
            URL-RESERVED-COVERS-ROUTES-13. Bir işletme `trust` slug'ını
            alırsa güven merkezi o işletmenin menüsüyle gölgelenirdi — ve
            gölgelendiği hiçbir yerde bir hata olarak görünmezdi.
        */
        self::assertContains(
            ltrim($path, '/'),
            (array) config('url-policy.reserved_slugs'),
            "GÜVENCE-08: [{$path}] rezerve slug listesinde yok.",
        );
    }

    // --- GÜVENCE-09 --------------------------------------------------------

    public function test_the_static_preview_covers_both_pages_so_the_browser_audit_can_measure_them(): void
    {
        /*
            `scripts/mobile-ux-audit` kurumsal sayfaları statik önizleme
            üzerinden ölçüyor. Bu listeye eklenmeyen bir sayfa 320 pikselde
            HİÇ ölçülmez — ve ölçülmemiş bir sayfa için "taşma yok" demek,
            bu paketin yasakladığı cümlenin ta kendisi olurdu.
        */
        $controllers = (new ReflectionClass(ExportStaticSiteCommand::class))
            ->getConstant('SHELL_CONTROLLERS');

        self::assertIsArray($controllers);

        foreach ([
            ShowTrustCentreController::class,
            ShowAccessibilityStatementController::class,
        ] as $controller) {
            self::assertContains(
                $controller,
                $controllers,
                'GÜVENCE-09: '.$controller.' statik önizlemede yok; sayfası dar ekranda hiç ölçülmez.',
            );
        }
    }

    // --- GÜVENCE-10 --------------------------------------------------------

    #[DataProvider('assurancePages')]
    public function test_the_scene_stays_calm_one_canvas_and_nothing_over_the_text(string $path): void
    {
        /*
            SAHNE-B7 sayfa başına bir tuval şart koşuyor. Burada ayrıca
            ölçülmesinin sebebi ayrı: bu iki sayfa OKUNACAK sayfalar ve
            gövdede dekoratif bir katman bulunması, kapının değil bu paketin
            kusuru olurdu.
        */
        $xpath = $this->xpath($path);

        $canvases = $xpath->query('//canvas[@data-scene="field"]');
        self::assertNotFalse($canvases);
        self::assertSame(1, $canvases->length, "GÜVENCE-10: [{$path}] sayfa başına bir tuval kuralını bozuyor.");

        $decorInBody = $xpath->query('//main//*[contains(concat(" ", @class, " "), " site-stage-layer ")]');
        self::assertNotFalse($decorInBody);
        self::assertSame(
            0,
            $decorInBody->length,
            "GÜVENCE-10: [{$path}] gövdesinde dekoratif katman var; okunacak bir metnin üstüne süsleme gelmez.",
        );
    }

    // --- GÜVENCE-11 --------------------------------------------------------

    #[DataProvider('assurancePages')]
    public function test_the_statement_is_assembled_behind_the_port(string $path, string $key): void
    {
        // Denetleyici kendi metnini yazmaz: iki sayfa da aynı derleyiciden gelir.
        $library = app(AssuranceLibraryPort::class);

        $statement = $key === 'trust' ? $library->trustCentre() : $library->accessibilityStatement();

        $this->get($path)
            ->assertOk()
            ->assertSee($statement->title, escape: true)
            ->assertSee($statement->summary, escape: true);
    }
}
