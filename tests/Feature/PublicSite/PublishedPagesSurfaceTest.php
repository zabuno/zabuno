<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Application\Content\Port\ContentLibraryPort;
use App\Domain\Content\PublicationDecision;
use App\Domain\Url\UrlNormalizer;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PUBLISHED-SURFACE-01…05 — yayın kararı UYGULANDIĞINDA ne oluyor, ÖLÇÜLÜR.
 *
 * ── Neden bu dosya var ───────────────────────────────────────────────────
 *
 * `FooterContentMenusTest` altbilginin kütükten türeme MEKANİZMASINI ölçer ve
 * bunu uydurma satırlarla yapar. Buradaki kapılar başka bir soruyu sorar:
 * *deponun GERÇEK yayın kararları uygulandığında ziyaretçinin gördüğü yüzey
 * ne oluyor?*
 *
 * Fark önemli, çünkü kusur tam olarak o boşlukta yaşıyordu: mekanizma
 * doğruydu ve hiç çalışmıyordu — yayına alınmış tek bir sayfa yoktu.
 * Mekanizmayı ölçen bir test, "altbilgi bugün boş" cümlesini de doğru
 * bulur.
 */
final class PublishedPagesSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /** Yayın kararlarını gerçekten uygular; kaç sayfa açıldığını döner. */
    private function applyRealDecisions(): int
    {
        $this->artisan('site:import-map')->assertSuccessful();
        $this->artisan('site:apply-publication-decisions')->assertSuccessful();

        return count(PublicationDecision::listFrom((array) config('content-publication-decisions')));
    }

    /** @return list<string> Yayına alınan sayfaların YÖNLENDİRMESİZ adresleri. */
    private function publishedTargets(): array
    {
        $normalizer = $this->app->make(UrlNormalizer::class);
        $targets = [];

        foreach (PublicationDecision::listFrom((array) config('content-publication-decisions')) as $decision) {
            $page = ContentPage::query()
                ->where('page_key', $decision->pageKey)
                ->where('locale', $decision->locale)
                ->firstOrFail();

            $targets[] = $normalizer->normalize($page->canonical_path)->target();
        }

        sort($targets);

        return $targets;
    }

    private function footer(string $path): string
    {
        $html = (string) $this->withHeaders(['Accept-Language' => 'en'])
            ->get($path)->assertOk()->getContent();

        preg_match('#<footer\b.*?</footer>#s', $html, $match);

        self::assertNotSame([], $match, 'Sayfada altbilgi yok — ölçüm dayanaksız.');

        return $match[0];
    }

    /**
     * pSEO BANDININ TAMAMI — iç içe `<details>`leri SAYARAK.
     *
     * Bandın içindeki her grup FF-237'den beri kendi `<details>`i (grup
     * dörtten çok madde taşıyorsa kapalı başlar). Tembel bir düzenli ifade
     * (`.*?</details>`) ilk İÇ kapanışta durur ve bandın yalnız ilk grubunu
     * yakalar: on sekiz bağlantılı bir bant üç bağlantı gibi ÖLÇÜLÜR ve kapı
     * yanlış yerde kırılır — ölçüldü (2026-09-08).
     *
     * Bu yüzden bant düzenli ifadeyle değil, açılış/kapanış SAYILARAK
     * kesiliyor. Yöntem iç içe geçme derinliğinden bağımsızdır; bir gruba bir
     * katlama daha eklendiğinde kapı yine bandın tamamını ölçer.
     */
    private function band(string $footer): string
    {
        $start = strpos($footer, '<details class="site-footer-content"');

        self::assertNotFalse($start, 'PUBLISHED-SURFACE-02: pSEO bandı hiç çizilmedi.');

        $start = (int) $start;
        $depth = 0;
        $offset = $start;

        while (preg_match('#</?details\\b#', $footer, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            [$tag, $at] = $match[0];
            $depth += $tag === '</details' ? -1 : 1;
            $offset = $at + strlen($tag);

            if ($depth === 0) {
                $end = strpos($footer, '>', $offset);

                return substr($footer, $start, ($end === false ? $offset : $end + 1) - $start);
            }
        }

        self::fail('PUBLISHED-SURFACE-02: pSEO bandının kapanışı bulunamadı.');
    }

    /**
     * Etiket karşılaştırması için etiket İÇİ boşluğu tek boşluğa indirir.
     *
     * Blade uzun bir `<a>`yı okunur kalsın diye satırlara bölüyor; ziyaretçi
     * için hiçbir şey değişmez, düz bir alt dize araması içinse her şey.
     * Karşılaştırılan üçlü aynı kalır: adres, sınıf ve etiket.
     */
    private static function tidy(string $html): string
    {
        return (string) preg_replace(['#\\s+#', '#\\s+>#'], [' ', '>'], $html);
    }

    /** @return list<string> */
    private function sitemapLocations(): array
    {
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        preg_match_all('~<loc>(.*?)</loc>~', $xml, $matches);

        return $matches[1];
    }

    // --- PUBLISHED-SURFACE-01 --------------------------------------------

    /**
     * KAPI TEK CÜMLE: altbilgideki her bağlantı 200 döner — 302 değil.
     *
     * Altbilgi aniden on sekiz bağlantı büyüdü ve altbilgi, kimsenin
     * bakmadığı yerdir; kırık bir bağlantı orada aylarca yaşayabilir.
     */
    public function test_every_link_in_the_grown_footer_resolves_or_authenticates(): void
    {
        $published = $this->applyRealDecisions();

        preg_match_all('~href="(/[^"\#]*)"~', $this->footer('/pricing'), $matches);

        $targets = array_values(array_unique($matches[1]));

        self::assertGreaterThan(
            $published,
            count($targets),
            'PUBLISHED-SURFACE-01: altbilgi yayınlanan sayfa sayısından kısa — ölçüm dayanaksız.'
        );

        foreach ($targets as $target) {
            // The account entry intentionally sends guests to authentication.
            if ($target === '/app') {
                $this->get($target)->assertRedirect(route('login'));

                continue;
            }

            self::assertSame(
                200,
                $this->get($target)->getStatusCode(),
                "PUBLISHED-SURFACE-01: altbilgideki [{$target}] 200 dönmüyor."
            );
        }
    }

    // --- PUBLISHED-SURFACE-02 --------------------------------------------

    /**
     * pSEO BANDI, KARAR DOSYASININ AYNASIDIR — ne eksik ne fazla.
     *
     * Bir sayfa daha yayına alındığında bu kapı kendiliğinden büyür; bir
     * sayfa karardan çıkarıldığında kendiliğinden küçülür. Sabit bir sayı
     * yazsaydık, kapı bir sonraki karara kadar yaşardı.
     */
    public function test_the_pseo_band_carries_exactly_the_decided_pages(): void
    {
        $this->applyRealDecisions();

        $band = $this->band($this->footer('/pricing'));

        preg_match_all('~href="(/[^"\#]*)"~', $band, $links);

        $inBand = array_values(array_unique($links[1]));
        sort($inBand);

        self::assertSame($this->publishedTargets(), $inBand);

        // Grup iskeleti sayfaların KENDİ hiyerarşisinden çıkar: ürün ağacı,
        // menü yönetiminin alt ağacı ve ebeveyni olmayanların yığını.
        preg_match_all('~data-nav-group="(content-[^"]+)"~', $band, $groups);

        self::assertSame(
            ['content-explore', 'content-urun', 'content-urun-menu-yonetimi'],
            $groups[1]
        );
    }

    // --- PUBLISHED-SURFACE-03 --------------------------------------------

    /**
     * SITEMAP TAM OLARAK YAYINLANAN KADAR BÜYÜR.
     *
     * Ölçüm iki kere yapılır — karar uygulanmadan önce ve sonra — çünkü
     * "sitemap 27 adres" cümlesi tek başına hiçbir şey kanıtlamaz: içindeki
     * yasal belgeler ayrı bir sebeple girip çıkabilir.
     */
    public function test_the_sitemap_grows_by_exactly_the_published_pages(): void
    {
        $this->artisan('site:import-map')->assertSuccessful();

        $before = $this->sitemapLocations();

        $this->artisan('site:apply-publication-decisions')->assertSuccessful();

        $after = $this->sitemapLocations();

        self::assertCount(count($before) + count($this->publishedTargets()), $after);

        $paths = array_map(
            static fn (string $location): string => (string) parse_url($location, PHP_URL_PATH),
            $after,
        );

        foreach ($this->publishedTargets() as $target) {
            self::assertContains(
                $target,
                $paths,
                "PUBLISHED-SURFACE-03: [{$target}] yayında ama sitemap'te yok."
            );
        }

        /*
            TÜRKÇE SATIRLAR SITEMAP'E GİRMEZ. 386'sının metni yok ve çeviri
            kilidi kapalı (`docs/120` §7); biri girseydi arama motoruna
            açılmayan bir adres ilan edilmiş olurdu.
        */
        foreach ($after as $location) {
            self::assertStringNotContainsString('/tr/', $location);
        }
    }

    // --- PUBLISHED-SURFACE-04 --------------------------------------------

    /**
     * ETİKET, SAYFANIN KENDİ YAZILMIŞ KISA ADIDIR.
     *
     * Kütükteki `title` alanı site haritası BELGESİNDEN gelir ve o belge
     * Türkçedir (`docs/118` E4) — kaynak dil satırları için bile. Ölçüldü
     * (2026-09-08): kütükteki başlıkların bir kısmı başlık bile değil, bir
     * AÇIKLAMA cümlesi ("QR, dijital, mobil ve temassız menü özelliklerini
     * tek sayfada anlatır").
     *
     * Yani kütüğün başlığını altbilgiye yazmak, İngilizce bir sitede Türkçe
     * cümleler asmak demekti. Burada çeviri YAPILMIYOR: sayfanın kendi
     * İngilizce kırıntı başlığı (`breadcrumbTitle`) zaten depoda yazılı ve
     * ziyaretçi onu sayfanın içinde de görüyor.
     */
    public function test_labels_are_the_pages_own_written_short_names(): void
    {
        $this->applyRealDecisions();

        $band = self::tidy($this->band($this->footer('/pricing')));
        $library = $this->app->make(ContentLibraryPort::class);
        $normalizer = $this->app->make(UrlNormalizer::class);

        foreach (PublicationDecision::listFrom((array) config('content-publication-decisions')) as $decision) {
            $page = ContentPage::query()
                ->where('page_key', $decision->pageKey)
                ->where('locale', $decision->locale)
                ->firstOrFail();

            $content = $library->find($decision->pageKey, $decision->locale);

            self::assertNotNull($content);

            $href = $normalizer->normalize($page->canonical_path)->target();

            self::assertStringContainsString(
                '<a href="'.$href.'" class="site-footer-link">'.e($content->metadata->breadcrumbTitle).'</a>',
                $band,
                "PUBLISHED-SURFACE-04: [{$href}] etiketi sayfanın kendi kısa adı değil."
            );
        }

        // Kütüğün Türkçe açıklaması ziyaretçiye ASLA ulaşmaz.
        self::assertStringNotContainsString('tek sayfada anlatır', $band);
        self::assertStringNotContainsString('genel bakış', $band);
    }

    // --- PUBLISHED-SURFACE-05 --------------------------------------------

    /**
     * TÜRKÇE KÜTÜK SATIRLARI KIPIRDAMADI.
     *
     * 386 Türkçe satırın metni yok. Yayın kararı yalnız kaynak dili açtı;
     * bir tanesi bile ilerlemiş olsaydı, ziyaretçiye 404 vaat eden bir
     * bağlantı doğardı.
     */
    public function test_the_turkish_rows_were_not_touched(): void
    {
        $this->applyRealDecisions();

        self::assertSame(
            0,
            ContentPage::query()->where('locale', 'tr')->where('was_ever_published', true)->count()
        );

        self::assertStringNotContainsString('href="/tr/', $this->footer('/pricing'));
    }
}
