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

    /**
     * Yayına alınan sayfaların YÖNLENDİRMESİZ adresleri.
     *
     * `$locale` VERİLİRSE yalnız o dilin satırları döner ve bu bir kolaylık
     * değil bir zorunluluk: altbilginin içerik katı TEK bir dilin kütük
     * satırlarından türer (`SiteNavigation::contentMenus`). İki dili tek
     * listede beklemek, İngilizce altbilgide Türkçe adresler aramak olurdu —
     * yani tam olarak bu paketin ÖNLEDİĞİ şey.
     *
     * @return list<string>
     */
    private function publishedTargets(?string $locale = null): array
    {
        $normalizer = $this->app->make(UrlNormalizer::class);
        $targets = [];

        foreach (PublicationDecision::listFrom((array) config('content-publication-decisions')) as $decision) {
            if ($locale !== null && $decision->locale !== $locale) {
                continue;
            }

            $page = ContentPage::query()
                ->where('page_key', $decision->pageKey)
                ->where('locale', $decision->locale)
                ->firstOrFail();

            $targets[] = $normalizer->normalize($page->canonical_path)->target();
        }

        sort($targets);

        return $targets;
    }

    private function footer(string $path, string $locale = 'en'): string
    {
        $html = (string) $this->withHeaders(['Accept-Language' => $locale])
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
            count($this->publishedTargets('en')),
            count($targets),
            'PUBLISHED-SURFACE-01: altbilgi yayınlanan sayfa sayısından kısa — ölçüm dayanaksız.'
        );
        self::assertGreaterThan(0, $published);

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

        /*
            İKİ DİL AYRI AYRI ÖLÇÜLÜR ve aynası da ayrıdır. Ziyaretçi tek bir
            dilde okur; altbilgisinde öteki dilin adreslerini görmesi, ona
            farkında olmadan dil değiştiren bir bağlantı sunmak olurdu.
            Grup iskeletinin iki dilde AYNI çıkması da bir sonuç, bir
            tesadüf değil: iskelet sayfaların kendi hiyerarşisinden
            (`parent_key`) doğuyor ve hiyerarşi dilden bağımsız.
        */
        foreach (['en' => 'tr', 'tr' => 'en'] as $locale => $other) {
            $band = $this->band($this->footer('/pricing', $locale));

            preg_match_all('~href="(/[^"\#]*)"~', $band, $links);

            $inBand = array_values(array_unique($links[1]));
            sort($inBand);

            self::assertSame($this->publishedTargets($locale), $inBand, $locale);
            self::assertStringNotContainsString('href="/'.$other.'/', $band, $locale);

            preg_match_all('~data-nav-group="(content-[^"]+)"~', $band, $groups);

            self::assertSame(
                ['content-explore', 'content-urun', 'content-urun-menu-yonetimi'],
                $groups[1],
                $locale
            );
        }
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
            METNİ OLMAYAN TÜRKÇE SATIRLAR SITEMAP'E YİNE GİRMEZ.

            Buradaki kural bir dönem "hiçbir `/tr/` adresi giremez" diye
            yazılıydı ve o gün doğruydu: 386 Türkçe satırın metni yoktu.
            Ölçünün KENDİSİ hiç değişmedi — sitemap yalnız gerçekten açılan
            adresleri ilan eder (`ResolvePageDelivery`) — değişen şey, on
            sekizinin artık gerçekten açılıyor olması.

            Bu yüzden kural bir dil yasağı olarak DEĞİL, bir sayı olarak
            yazılır: kütükte kaç Türkçe satır varsa değil, kaç tanesinin
            kararı verilmişse o kadar `/tr/` adresi görünür. Geri kalan 368
            satır hâlâ dışarıdadır ve biri sızsaydı bu kapı kırılırdı.
        */
        $turkish = array_values(array_filter(
            $paths,
            static fn (string $path): bool => str_starts_with($path, '/tr/'),
        ));

        sort($turkish);

        self::assertSame($this->publishedTargets('tr'), $turkish);
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

        $library = $this->app->make(ContentLibraryPort::class);
        $normalizer = $this->app->make(UrlNormalizer::class);

        /*
            ETİKET, O DİLİN KENDİ KISA ADIDIR.

            Kütükteki `title` alanı iki dilde de aynıdır — belgeden gelir ve
            belge Türkçedir. Türkçe satırlar için bu, kaza eseri doğru
            görünürdü ve tam olarak bu yüzden ölçülüyor: etiketin doğru
            olması kaynağın doğru olmasından gelmeli, tesadüften değil.
            Türkçe altbilgide görünen "QR menü", kütüğün açıklama cümlesi
            değil, sayfanın kendi Türkçe kırıntı başlığıdır.
        */
        foreach (['en', 'tr'] as $locale) {
            $band = self::tidy($this->band($this->footer('/pricing', $locale)));

            foreach (PublicationDecision::listFrom((array) config('content-publication-decisions')) as $decision) {
                if ($decision->locale !== $locale) {
                    continue;
                }

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

            // Kütüğün açıklama cümlesi ziyaretçiye ASLA ulaşmaz — iki dilde de.
            self::assertStringNotContainsString('tek sayfada anlatır', $band, $locale);

            /*
                "genel bakış" YASAĞI YALNIZ İNGİLİZCEDE ANLAMLIDIR.

                İngilizce altbilgide bu ifade ancak kütüğün Türkçe açıklaması
                sızdıysa görünebilir — yasak orada gerçek bir kusuru yakalar.
                Türkçe altbilgide ise aynı ifade sayfanın KENDİ kırıntı
                başlığıdır ("Ürün genel bakışı"); iki dilde birden yasaklamak,
                meşru Türkçe etiketi kusur saymak olurdu.
            */
            if ($locale === 'en') {
                self::assertStringNotContainsString('genel bakış', $band, $locale);
            }
        }
    }

    // --- PUBLISHED-SURFACE-05 --------------------------------------------

    /**
     * YALNIZ METNİ YAZILMIŞ TÜRKÇE SATIRLAR AÇILDI — ne bir eksik, ne bir fazla.
     *
     * Burada bir dönem "Türkçe satırlar KIPIRDAMADI" ölçülüyordu ve gerekçesi
     * doğruydu: 386 satırın metni yoktu, biri açılsaydı ziyaretçiye 404 vaat
     * eden bir bağlantı doğardı.
     *
     * Sahibin ikinci dil kararı (2026-09-10) on sekizinin metnini yazdırdı.
     * Ölçünün RUHU değişmedi — hâlâ "metni olmayan hiçbir satır açılmasın"
     * diyor — ama artık bunu bir yasakla değil bir SAYIYLA söylüyor:
     * açılanların kümesi, karar dosyasında adıyla sayılanların kümesine eşit
     * olmak zorunda. Toptan bir "Türkçeyi aç" bu kapıdan geçemez.
     */
    public function test_only_the_named_turkish_rows_were_opened(): void
    {
        $this->applyRealDecisions();

        $opened = ContentPage::query()
            ->where('locale', 'tr')
            ->where('was_ever_published', true)
            ->orderBy('page_key')
            ->pluck('page_key')
            ->all();

        $decided = [];

        foreach (PublicationDecision::listFrom((array) config('content-publication-decisions')) as $decision) {
            if ($decision->locale === 'tr') {
                $decided[] = $decision->pageKey;
            }
        }

        sort($decided);

        self::assertSame($decided, $opened);

        // Geri kalan Türkçe satırlar kütükte duruyor ve KAPALI.
        self::assertGreaterThan(
            count($decided),
            ContentPage::query()->where('locale', 'tr')->count(),
        );
    }

    /**
     * BİR DİLİN ALTBİLGİSİ ÖTEKİ DİLE KÖPRÜ KURMAZ.
     *
     * Kusurun kendisi buydu ve üst çubukta yaşıyordu: elle yazılmış `/tr/…`
     * adresleri, İngilizce okuyan bir ziyaretçiyi Türkçe sayfaya
     * götürüyordu. Kapı artık iki yönlü ölçülüyor — kabuğun TAMAMINDA, yalnız
     * altbilgide değil.
     */
    public function test_the_chrome_of_one_language_never_links_into_the_other(): void
    {
        $this->applyRealDecisions();

        foreach (['en' => '/tr/', 'tr' => '/en/'] as $locale => $foreign) {
            $html = (string) $this->withHeaders(['Accept-Language' => $locale])
                ->get('/pricing')->assertOk()->getContent();

            $chrome = '';

            foreach (['header', 'footer'] as $tag) {
                preg_match("#<{$tag}\b.*?</{$tag}>#s", $html, $match);
                $chrome .= $match[0] ?? '';
            }

            self::assertNotSame('', $chrome, 'Kabuk hiç çizilmedi — ölçüm dayanaksız.');
            self::assertStringNotContainsString('href="'.$foreign, $chrome, $locale);
        }
    }
}
