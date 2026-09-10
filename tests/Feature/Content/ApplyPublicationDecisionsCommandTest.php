<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Application\Content\Port\ContentLibraryPort;
use App\Domain\Content\PagePublicationStatus;
use App\Domain\Content\PublicationDecision;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PUBLISH-DECISION-01…10 — verilmiş bir insan kararı UYGULANIR, üretilmez.
 *
 * ── Bozulmayan karar ─────────────────────────────────────────────────────
 *
 * `SyncContentStatusCommand`'ın tavanı `content_draft`tır ve öyle kalıyor:
 * *"Bir betiğin atlayabildiği kapı, kapı değildir."* Bu komut o kapıyı
 * ATLAMAZ, çünkü kapıdan geçen şey bir betik değil bir insandır — kararı
 * `config/content-publication-decisions.php` içinde adıyla, sebebiyle ve
 * gününle yazılı, kod incelemesinden geçmiş bir satırdır.
 *
 * Farkı bu dosyadaki kapılar ölçer: toptan bir yayın YOK (03), adı geçmeyen
 * bir sayfa açılmaz (03), metni olmayan bir satır açılmaz (04), kütükte
 * olmayan bir satır sessizce atlanmaz (05), ve bir insanın sonradan verdiği
 * karar bir betikle geri alınmaz (07).
 */
final class ApplyPublicationDecisionsCommandTest extends TestCase
{
    use RefreshDatabase;

    // --- PUBLISH-DECISION-01 ---------------------------------------------

    /**
     * KARARIN ADI GEÇEN HER SAYFANIN METNİ YAZILMIŞ OLMALI.
     *
     * Bu kapı çalışma zamanını değil DOSYANIN KENDİSİNİ ölçer: birisi
     * kararlar dosyasına metni olmayan bir anahtar eklerse, kusuru
     * dağıtımda değil kod incelemesinde görür.
     */
    public function test_every_named_page_has_a_written_text_and_a_recorded_address(): void
    {
        $library = $this->app->make(ContentLibraryPort::class);
        /** @var array<string, string> $sourcePaths */
        $sourcePaths = (array) config('site-source-paths');

        $decisions = PublicationDecision::listFrom((array) config('content-publication-decisions'));

        self::assertNotSame([], $decisions, 'Kararlar dosyası boş — ölçüm dayanaksız.');

        foreach ($decisions as $decision) {
            self::assertNotNull(
                $library->find($decision->pageKey, $decision->locale),
                "PUBLISH-DECISION-01: [{$decision->pageKey}/{$decision->locale}] için yazılmış metin yok. "
                .'Yayınlanmış görünüp 404 dönen bir sayfa, hiç yayınlanmamış olandan kötüdür.'
            );

            self::assertArrayHasKey(
                $decision->pageKey,
                $sourcePaths,
                "PUBLISH-DECISION-01: [{$decision->pageKey}] için kaynak dilde bir adres kayıtlı değil."
            );
        }
    }

    // --- PUBLISH-DECISION-02 ---------------------------------------------

    /**
     * YALNIZ SUNULAN DİLLER — ve her sayfa İKİ DİLDE DE karara bağlanmış.
     *
     * Bu kapı bir dönem "yalnız kaynak dil" diyordu ve gerekçesi ölçülebilir
     * bir olguydu: Türkçe kütük satırlarının METNİ YOKTU, dolayısıyla bir
     * Türkçe satırı açmak 386 tane 404 vaadinin ilkini vermek olurdu.
     *
     * Sahibin kararıyla (2026-09-10) on sekiz sayfanın Türkçe metni yazıldı
     * ve kapı yön değiştirdi. Yerine geçen kural DAHA SIKI: bir dil yalnız
     * `shipped_locales` içindeyse karara girebilir VE bir sayfa iki dilde de
     * karara bağlanmışsa geçerlidir. Tek dilde açılmış bir sayfa, dil
     * değiştiricide bir çıkmaz sokak ve hreflang'de tek yönlü bir iddia
     * olurdu (`docs/119` §10.4).
     */
    public function test_every_decided_page_is_decided_in_every_shipped_language(): void
    {
        /** @var list<string> $shipped */
        $shipped = array_values((array) config('i18n.shipped_locales'));

        self::assertContains((string) config('i18n.source_locale'), $shipped);

        $byPage = [];

        foreach (PublicationDecision::listFrom((array) config('content-publication-decisions')) as $decision) {
            self::assertContains(
                $decision->locale,
                $shipped,
                "PUBLISH-DECISION-02: [{$decision->pageKey}] sunulmayan bir dilde yayına alınıyor."
            );

            $byPage[$decision->pageKey][] = $decision->locale;
        }

        self::assertNotSame([], $byPage, 'Kararlar dosyası boş — ölçüm dayanaksız.');

        foreach ($byPage as $pageKey => $locales) {
            sort($locales);
            $expected = $shipped;
            sort($expected);

            self::assertSame(
                $expected,
                $locales,
                "PUBLISH-DECISION-02: [{$pageKey}] her sunulan dilde karara bağlanmamış."
            );
        }
    }

    // --- PUBLISH-DECISION-03 ---------------------------------------------

    public function test_it_publishes_exactly_the_named_pages_and_nothing_else(): void
    {
        $named = $this->ledgerRow('urun.qr-menu', '/en/product/qr-menu/');
        $unnamed = $this->ledgerRow('urun.analitik', '/en/product/analytics/');
        // Metni YAZILMIŞ ama bu ölçümün karar kümesinde ADI GEÇMEYEN satır:
        // "adı geçmeyen açılmaz" kuralı ancak açılabilir bir satırla ölçülür.
        $turkish = $this->ledgerRow('urun.qr-menu', '/tr/urun/qr-menu/', 'tr');

        $this->decide([$this->decision('urun.qr-menu')]);

        $this->artisan('site:apply-publication-decisions')->assertSuccessful();

        self::assertSame(PagePublicationStatus::Published, $named->refresh()->status());
        self::assertTrue($named->was_ever_published);
        self::assertNotNull($named->published_at);

        // Adı geçmeyen sayfa KIPIRDAMAZ. Toptan bir yayın komutu olsaydı
        // burası da açılırdı ve kimse fark etmezdi.
        self::assertSame(PagePublicationStatus::Planned, $unnamed->refresh()->status());
        self::assertSame(PagePublicationStatus::Planned, $turkish->refresh()->status());
    }

    // --- PUBLISH-DECISION-04 ---------------------------------------------

    public function test_a_decision_naming_a_page_without_text_stops_the_command_and_publishes_nothing(): void
    {
        $writable = $this->ledgerRow('urun.qr-menu', '/en/product/qr-menu/');
        /*
            METNİ OLMAYAN DİL ARTIK TÜRKÇE DEĞİL.

            Bu ölçüm bir dönem Türkçeyi kullanıyordu, çünkü Türkçe yuva
            bilerek boştu. Sahibin kararıyla Türkçe metinler yazıldı ve
            ölçüm o gün SESSİZCE ANLAMINI KAYBEDERDİ: komut başarılı olur,
            test yeşil kalır ve "metni olmayan satır açılmaz" kuralı hiçbir
            şeyi ölçmezdi.

            Bu yüzden ölçü, metni GERÇEKTEN yazılmamış bir dile taşındı.
            Almanca altyapıda tanınan bir dildir (`i18n.supported_locales`)
            ama içerik kütüphanesinde tek satırı yoktur — yani kapının
            ölçtüğü durumun bugünkü gerçek örneği.
        */
        $textless = $this->ledgerRow('urun.qr-menu', '/de/produkt/qr-menu/', 'de');

        $this->decide([
            $this->decision('urun.qr-menu'),
            $this->decision('urun.qr-menu', 'de'),
        ]);

        $this->artisan('site:apply-publication-decisions')->assertFailed();

        /*
            HİÇBİRİ açılmaz. Yarım uygulanmış bir yayın, hiç uygulanmamış
            olandan kötüdür: sahip altbilgide bir şeyler görür ve kararının
            tamamının işlediğini sanır.
        */
        self::assertSame(PagePublicationStatus::Planned, $writable->refresh()->status());
        self::assertSame(PagePublicationStatus::Planned, $textless->refresh()->status());
    }

    // --- PUBLISH-DECISION-05 ---------------------------------------------

    public function test_a_decision_whose_ledger_row_is_missing_stops_the_command_loudly(): void
    {
        // Kütük hiç doldurulmadı — `site:import-map` koşmamış bir sunucu.
        $this->decide([$this->decision('urun.qr-menu')]);

        $this->artisan('site:apply-publication-decisions')->assertFailed();
    }

    // --- PUBLISH-DECISION-06 ---------------------------------------------

    public function test_running_it_twice_changes_nothing(): void
    {
        $page = $this->ledgerRow('urun.qr-menu', '/en/product/qr-menu/');
        $this->decide([$this->decision('urun.qr-menu')]);

        $this->artisan('site:apply-publication-decisions')->assertSuccessful();
        $stamp = $page->refresh()->published_at;

        $this->artisan('site:apply-publication-decisions')->assertSuccessful();

        self::assertSame(PagePublicationStatus::Published, $page->refresh()->status());
        // Damga TAZELENMEZ: `lastmod` her koşuda değişseydi arama motoruna
        // değişmemiş bir sayfa her gün yeniden taratılırdı.
        self::assertEquals($stamp, $page->refresh()->published_at);
    }

    // --- PUBLISH-DECISION-07 ---------------------------------------------

    /**
     * BİR İNSANIN SONRAKİ KARARI BİR BETİKLE GERİ ALINMAZ.
     *
     * Komut her konteyner açılışında elle çalıştırılabilir. Sahip bir sayfayı
     * bakıma aldıysa, bir sonraki koşu onu sessizce yayına döndürmemeli —
     * yoksa "bakıma al" düğmesi bir sonraki dağıtıma kadar sürer.
     */
    public function test_it_never_overrules_a_later_human_decision(): void
    {
        $page = $this->ledgerRow('urun.qr-menu', '/en/product/qr-menu/');
        $this->decide([$this->decision('urun.qr-menu')]);

        $this->artisan('site:apply-publication-decisions')->assertSuccessful();

        $page->publication_status = PagePublicationStatus::Maintenance->value;
        $page->save();

        $this->artisan('site:apply-publication-decisions')->assertSuccessful();

        self::assertSame(PagePublicationStatus::Maintenance, $page->refresh()->status());
    }

    // --- PUBLISH-DECISION-08 ---------------------------------------------

    /**
     * GERİ ALMA YOLU — tek komut, ve dönüşü de tek komut.
     *
     * Durum makinesi yayından taslağa dönmeye izin VERMEZ ve vermemeli:
     * yayınlanmış bir adres yayınlanmıştır. Dürüst geri alma `maintenance`
     * kapısıdır — sayfa gezintiden ve sitemap'ten çıkar, ziyaretçiye 503
     * "vardı, kısa süreliğine yok" der, ve `--restore` ile geri gelir.
     */
    public function test_rollback_takes_the_named_pages_out_and_restore_brings_them_back(): void
    {
        $page = $this->ledgerRow('urun.qr-menu', '/en/product/qr-menu/');
        $this->decide([$this->decision('urun.qr-menu')]);

        $this->artisan('site:apply-publication-decisions')->assertSuccessful();

        $this->artisan('site:apply-publication-decisions --rollback')->assertSuccessful();
        self::assertSame(PagePublicationStatus::Maintenance, $page->refresh()->status());
        self::assertNotNull($page->unpublished_at);
        $this->get('/en/product/qr-menu')->assertStatus(503);

        $this->artisan('site:apply-publication-decisions --restore')->assertSuccessful();
        self::assertSame(PagePublicationStatus::Published, $page->refresh()->status());
        $this->get('/en/product/qr-menu')->assertOk();
    }

    // --- PUBLISH-DECISION-09 ---------------------------------------------

    public function test_dry_run_changes_nothing(): void
    {
        $page = $this->ledgerRow('urun.qr-menu', '/en/product/qr-menu/');
        $this->decide([$this->decision('urun.qr-menu')]);

        $this->artisan('site:apply-publication-decisions --dry-run')->assertSuccessful();

        self::assertSame(PagePublicationStatus::Planned, $page->refresh()->status());
    }

    // --- PUBLISH-DECISION-10 ---------------------------------------------

    /**
     * SEBEPSİZ VE SAHİPSİZ KARAR YOKTUR. Boş bir sebep, kararın neden
     * verildiğini kimsenin bilmediği bir yayın demektir.
     */
    public function test_a_decision_without_an_owner_or_a_reason_is_refused(): void
    {
        foreach ([
            ['page_key' => 'urun', 'locale' => 'en', 'decided_by' => '', 'decided_on' => '2026-09-08', 'reason' => 'x'],
            ['page_key' => 'urun', 'locale' => 'en', 'decided_by' => 'x', 'decided_on' => '2026-09-08', 'reason' => ' '],
            ['page_key' => 'urun', 'locale' => 'en', 'decided_by' => 'x', 'decided_on' => 'dün', 'reason' => 'x'],
        ] as $row) {
            try {
                PublicationDecision::listFrom([$row]);
                self::fail('PUBLISH-DECISION-10: eksik bir karar kabul edildi: '.json_encode($row));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    // --- Yardımcılar -----------------------------------------------------

    /** @param list<array<string, string>> $decisions */
    private function decide(array $decisions): void
    {
        config()->set('content-publication-decisions', $decisions);
    }

    /** @return array<string, string> */
    private function decision(string $pageKey, string $locale = 'en'): array
    {
        return [
            'page_key' => $pageKey,
            'locale' => $locale,
            'decided_by' => 'Zabuno sahibi',
            'decided_on' => '2026-09-08',
            'reason' => 'Ölçüm için verilmiş karar.',
        ];
    }

    private function ledgerRow(string $pageKey, string $path, string $locale = 'en'): ContentPage
    {
        return ContentPage::query()->create([
            'page_key' => $pageKey,
            'parent_key' => null,
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'Kütükteki başlık',
            'priority' => 'P0',
            'publication_status' => PagePublicationStatus::Planned->value,
            'was_ever_published' => false,
        ]);
    }
}
