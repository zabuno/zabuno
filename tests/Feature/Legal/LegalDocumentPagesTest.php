<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Domain\Legal\CompanyProfile;
use App\Support\Site\SiteNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LEGAL-PAGES-01…08 — yasal metinler yayında, dürüst ve kabuğun içinde
 * (`docs/107` Faz 1.2, FF-198).
 *
 * ÜRÜN SORUNU. `/terms`, `/privacy`, `/kvkk` bugüne kadar "hazırlanıyor"
 * yazan on üç satırlık bir yer tutucuydu. Uzaktan satış için gereken
 * metinlerin (mesafeli satış, ön bilgilendirme, iptal-iade, çerez, ticari
 * ileti izni) hiçbiri yoktu. Bir kebapçı kaydolurken neyi kabul ettiğini
 * okuyamıyordu, çünkü okuyacak bir şey yoktu.
 *
 * BU PAKET METİN YAZAR AMA UYDURMAZ. Şirket bilgisi `.env`'den gelir ve
 * varsayılanı yoktur; girilmemişse sayfa "not yet provided" der ve üstte
 * "hukuki inceleme bekliyor" notu taşır. Not, `LEGAL_REVIEWED_AT`
 * dolduğunda kalkar. Böylece belge yayınlanır ama söylemediği bir şeyi
 * söylemez.
 */
final class LegalDocumentPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{0:string,1:string}> yol → belge anahtarı */
    public static function legalPages(): array
    {
        return [
            ['/terms', 'terms'],
            ['/privacy', 'privacy'],
            ['/kvkk', 'kvkk'],
            ['/distance-sales', 'distance-sales'],
            ['/pre-information', 'pre-information'],
            ['/refund-policy', 'refund-policy'],
            ['/cookies', 'cookies'],
            ['/marketing-consent', 'marketing-consent'],
        ];
    }

    private function withoutCompany(): void
    {
        config([
            'legal.company' => [
                'legal_name' => null, 'address' => null, 'mersis' => null,
                'tax_office' => null, 'tax_number' => null, 'email' => null, 'phone' => null,
            ],
            'legal.reviewed_at' => null,
        ]);
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

    // --- LEGAL-PAGES-01: her belge yayında, kabuğun içinde, tek H1 --------

    #[DataProvider('legalPages')]
    public function test_every_legal_document_is_served_inside_the_single_shell_with_one_heading(string $path, string $key): void
    {
        $html = (string) $this->get($path)->assertOk()->getContent();

        self::assertSame(1, preg_match_all('#<h1\b#', $html), "LEGAL-PAGES-01: [{$path}] tek h1 taşımalı.");
        self::assertSame(1, preg_match_all('#<header\b#', $html), "LEGAL-PAGES-01: [{$path}] kabuğun üst çubuğu yok.");
        self::assertSame(1, preg_match_all('#<footer\b#', $html), "LEGAL-PAGES-01: [{$path}] kabuğun alt çubuğu yok.");
        self::assertStringContainsString('data-legal-document="'.$key.'"', $html);
        self::assertStringNotContainsString('pending qualified legal review and is not yet published', $html,
            'LEGAL-PAGES-01: yer tutucu metin hâlâ duruyor — belge yayınlanmamış.');
    }

    // --- LEGAL-PAGES-02: sürüm, yürürlük tarihi ve bölüm listesi görünür --

    #[DataProvider('legalPages')]
    public function test_version_effective_date_and_section_list_are_visible(string $path, string $key): void
    {
        $document = app(LegalLibraryPort::class)->find($key);

        self::assertNotNull($document, "LEGAL-PAGES-02: [{$key}] kütüphanede yok.");

        $html = (string) $this->get($path)->assertOk()->getContent();

        self::assertStringContainsString('data-legal-version="'.$document->version.'"', $html);
        self::assertStringContainsString($document->effectiveDate, $html, "LEGAL-PAGES-02: [{$path}] yürürlük tarihi görünmüyor.");
        self::assertStringContainsString('data-legal-contents', $html, "LEGAL-PAGES-02: [{$path}] sayfa içi bölüm listesi yok.");

        self::assertGreaterThanOrEqual(3, count($document->sections), "LEGAL-PAGES-02: [{$key}] en az üç numaralı bölüm taşımalı.");

        foreach ($document->sections as $index => $section) {
            $number = $index + 1;
            self::assertStringContainsString('id="section-'.$number.'"', $html, "LEGAL-PAGES-02: [{$path}] {$number}. bölüm çıpası yok.");
            self::assertStringContainsString('href="#section-'.$number.'"', $html, "LEGAL-PAGES-02: [{$path}] {$number}. bölüm listede yok.");
        }

        // Bölüm başlıkları H2: H1 tek, H2 en az bölüm sayısı kadar.
        self::assertGreaterThanOrEqual(count($document->sections), preg_match_all('#<h2\b#', $html));
    }

    // --- LEGAL-PAGES-03: şirket bilgisi UYDURULMAZ ------------------------

    public function test_missing_company_facts_are_said_to_be_missing_and_the_review_note_is_shown(): void
    {
        $this->withoutCompany();

        $html = (string) $this->get('/terms')->assertOk()->getContent();

        self::assertStringContainsString(CompanyProfile::NOT_PROVIDED, $html,
            'LEGAL-PAGES-03: boş şirket alanı sayfada "not yet provided" olarak görünmeli.');
        self::assertStringContainsString('data-legal-review="pending"', $html,
            'LEGAL-PAGES-03: inceleme notu yok — belge, incelenmiş gibi görünüyor.');
        self::assertStringContainsString('This text is pending legal review', $html);
    }

    public function test_configured_company_facts_are_the_ones_shown(): void
    {
        $this->withCompany();
        config(['legal.reviewed_at' => null]);

        $html = (string) $this->get('/terms')->assertOk()->getContent();

        self::assertStringContainsString('Örnek Yazılım A.Ş.', $html);
        self::assertStringNotContainsString(CompanyProfile::NOT_PROVIDED, $html,
            'LEGAL-PAGES-03: bütün alanlar doluyken "not yet provided" kalmamalı.');
        // İnceleme tarihi girilmediyse not, şirket dolu olsa da kalır.
        self::assertStringContainsString('data-legal-review="pending"', $html);
    }

    // --- LEGAL-PAGES-04: LEGAL_REVIEWED_AT notu kaldırır ------------------

    public function test_a_recorded_legal_review_removes_the_pending_note(): void
    {
        $this->withCompany();
        config(['legal.reviewed_at' => '2026-10-01']);

        $html = (string) $this->get('/privacy')->assertOk()->getContent();

        self::assertStringNotContainsString('data-legal-review="pending"', $html);
        self::assertStringNotContainsString('This text is pending legal review', $html);
    }

    public function test_an_unparseable_review_date_keeps_the_note_because_a_typo_is_not_a_review(): void
    {
        $this->withCompany();
        config(['legal.reviewed_at' => 'yes']);

        $this->get('/privacy')->assertOk()->assertSee('data-legal-review="pending"', false);
    }

    // --- LEGAL-PAGES-05: indekslenebilir, ölçüm kimliği, şirket alanı ------

    #[DataProvider('legalPages')]
    public function test_legal_pages_stay_indexable_and_declare_their_measurement_identity(string $path, string $key): void
    {
        config(['analytics.gtm_container_id' => 'GTM-TEST123']);

        $response = $this->withCookie('zabuno_measurement_consent', 'granted')->get($path);

        $response->assertOk();
        self::assertNull($response->headers->get('X-Robots-Tag'), "LEGAL-PAGES-05: [{$path}] noindex taşıyor.");
        $response->assertSee('"zabuno_page":"legal_'.str_replace('-', '_', $key).'"', false);
    }

    // --- LEGAL-PAGES-06: altbilgi bağlantıları TEK kaynaktan --------------

    public function test_the_footer_links_every_new_document_from_the_single_navigation_source(): void
    {
        $targets = app(SiteNavigation::class)->declaredTargets();
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        preg_match('#<footer\b.*?</footer>#s', $html, $footer);

        foreach (['/distance-sales', '/pre-information', '/refund-policy', '/cookies'] as $path) {
            self::assertContains($path, $targets, "LEGAL-PAGES-06: [{$path}] gezinti kaynağında yok.");
            self::assertStringContainsString('href="'.$path.'"', $footer[0] ?? '', "LEGAL-PAGES-06: [{$path}] altbilgide yok.");
        }
    }

    public function test_the_sitemap_carries_the_new_legal_pages(): void
    {
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (['/distance-sales', '/pre-information', '/refund-policy', '/cookies'] as $path) {
            self::assertStringContainsString($path.'</loc>', $xml, "LEGAL-PAGES-06: [{$path}] sitemap'te yok.");
        }
    }

    // --- LEGAL-PAGES-07: hesap verisi talebi yalnız veri sayfasında ------

    public function test_the_account_data_request_section_stays_on_the_kvkk_page_only(): void
    {
        $this->get('/kvkk')->assertOk()->assertSee('id="account-data-request"', false);
        $this->get('/distance-sales')->assertOk()->assertDontSee('id="account-data-request"', false);
    }

    // --- LEGAL-PAGES-08: statik önizleme yeni sayfaları da üretir --------

    public function test_the_static_export_includes_the_new_legal_pages(): void
    {
        $out = storage_path('framework/testing/legal-export-'.bin2hex(random_bytes(4)));

        try {
            $this->artisan('site:export-static', ['--out' => $out])->assertSuccessful();

            foreach (['cookies', 'distance-sales', 'pre-information', 'refund-policy'] as $dir) {
                self::assertFileExists($out.'/'.$dir.'/index.html', "LEGAL-PAGES-08: [{$dir}] statik önizlemede yok.");
            }
        } finally {
            File::deleteDirectory($out);
        }
    }

    // --- Kütüphane sözleşmesi ---------------------------------------------

    public function test_the_library_knows_exactly_the_eight_documents_and_every_text_is_english_source(): void
    {
        $library = app(LegalLibraryPort::class);
        $keys = array_map(static fn ($document) => $document->key, $library->all());

        sort($keys);

        self::assertSame(
            ['cookies', 'distance-sales', 'kvkk', 'marketing-consent', 'pre-information', 'privacy', 'refund-policy', 'terms'],
            $keys,
        );

        foreach ($library->all() as $document) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $document->effectiveDate);
            self::assertNotSame('', $document->version);

            foreach ($document->sections as $section) {
                self::assertNotSame('', trim($section->heading));
                self::assertNotSame([], $section->paragraphs);
            }
        }

        self::assertNull($library->find('unknown'));
    }

    /**
     * Belgeler ürünün YAPMADIĞI şeyi vaat etmez. Bu liste, `docs/107`
     * Faz 1'de "yok" ölçülen yeteneklerin metinde vaat olarak geçmemesini
     * ölçer; bir gün o yetenek gelirse liste küçülür, metin büyür.
     */
    public function test_no_document_promises_a_response_time_or_an_invented_price(): void
    {
        foreach (app(LegalLibraryPort::class)->all() as $document) {
            $text = strtolower(implode(' ', array_merge(...array_map(
                static fn ($section) => $section->paragraphs,
                $document->sections,
            ))));

            foreach (['within 24 hours', 'within 48 hours', 'business days', '99.9%', 'guaranteed uptime', '₺', 'try/month'] as $claim) {
                self::assertStringNotContainsString($claim, $text,
                    "[{$document->key}] \"{$claim}\" — ürünün tutmadığı bir taahhüt ya da uydurma bir tutar.");
            }
        }
    }
}
