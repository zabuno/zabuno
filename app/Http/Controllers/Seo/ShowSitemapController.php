<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Application\Content\UseCase\ResolveLocaleAlternates;
use App\Application\Content\UseCase\ResolvePageDelivery;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Domain\Content\PageEnvironment;
use App\Domain\Legal\CompanyProfile;
use App\Domain\Publication\MenuPublicAddress;
use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `sitemap.xml` — arama motorunun bu sitede NE OLDUĞUNU öğrendiği tek yer.
 *
 * Menü sayfalarına iç bağlantı yoktur: bir menüye ya basılı bir karekodla
 * ya da bu dosyayla ulaşılır. Sitemap olmadan "menüler indekslensin"
 * kararı kâğıt üstünde kalır.
 *
 * Üç kural pazarlığa kapalıdır:
 *
 * 1. **QR token'ı ASLA girmez.** Token basılmış bir kodun anahtarıdır ve
 *    `/q/` yüzeyi bilerek hız sınırlıdır; token listesini yayımlamak,
 *    taranmasını engellemeye çalıştığımız uzayı toplu hâlde teslim etmek
 *    olurdu (`docs/38` §18).
 * 2. **Yalnız indekslenebilir menüler girer.** Sitemap ile sayfanın kendi
 *    robots sinyali aynı cevabı vermek zorundadır; çelişki gönderen bir
 *    site, arama motorunun kendi kararını vermesine davetiye çıkarır.
 * 3. **Kurumsal sayfalar KÜTÜKTEN gelir — FF-214, `docs/129`.** Bir adresin
 *    buraya girip girmemesi, o adresin 200 mü 404 mü döndüğüyle AYNI
 *    `PageRenderDecision` nesnesinden okunur (`ResolvePageDelivery`). Daha
 *    önce böyle değildi: bu dosya kütüğü hiç okumuyor, dört adresi sabit
 *    yazıyordu. Yani sahibin yayına aldığı bir sayfa sitemap'e hiç
 *    girmeyecekti ve kimse fark etmeyecekti — sitemap yine geçerli bir XML
 *    döndürüyor (`docs/128` §6.4).
 *
 * **Ölçek — tek dosya, dizin DEĞİL (`docs/129` §5).** Sınır 50.000 URL ve
 * 50 MB'tır. Bugün kütükte 402 satır var, bunların 326'sı gerçek sayfa; canlı
 * sitemap ise yayın kararlarına bağlı olarak bundan çok daha küçük. Erken
 * bölmek, hiçbir sorunu çözmeyen bir dolaylılık katmanı eklemek olurdu.
 * Bölme günü ÖLÇÜLEBİLİR: URL sayısı 50.000'e yaklaştığında — ve o sayıya
 * kurumsal sayfalar değil, yayınlanan MENÜLER yaklaşır.
 */
final class ShowSitemapController extends Controller
{
    /**
     * Satıcının kimliğini SÖYLEMEK zorunda olan sayfalar (FF-216).
     *
     * `LegalDocument::$requiresSellerIdentity` ile aynı olgu; burada yol
     * olarak duruyor çünkü `/about` bir yasal belge değil, kurumsal bir
     * sayfadır ve aynı kurala tabidir.
     *
     * @var list<string>
     */
    private const SELLER_IDENTITY_PATHS = [
        '/about',
        '/distance-sales',
        '/pre-information',
        '/delivery',
        /*
            KURUMSAL SÖZLEŞMELER (FF-228, `docs/140`). Bir veri işleme
            sözleşmesinin "işleyen"i ve bir hizmet seviyesi taahhüdünün
            taahhüt edeni ADIYLA anılmak zorundadır; tarafı "not yet
            provided" yazan bir DPA, bir zincirin hukukçusuna gönderilebilir
            bir belge değildir. Kabul edilebilir kullanım ve lisans listesi
            bu kuralın DIŞINDA: ikisi de bir sözleşme değil bir bildirimdir
            ve tarafsız hâlleriyle de doğrudur.
        */
        '/data-processing',
        '/sla',
    ];

    /**
     * Kütüğe BAĞLI OLMAYAN, bugün gerçekten 200 dönen adresler.
     *
     * Ölçüldü (`docs/129` §4): bu on beş yolun HİÇBİRİ `content_pages`
     * tablosunda yok ve olamaz — hiçbiri kurumsal kapıdan geçmiyor. Ana
     * sayfa `FoundationStatusController`'ın, on üç yasal belge ise
     * `ShowLegalDocumentController`'ın kendi rotasıdır (`routes/web.php`);
     * yayın durumları yok çünkü yayın kararı zaten verilmiş — sayfa canlı.
     *
     * Bu yüzden sabit KALIYORLAR, ve sabit kalmaları bir istisna değil bir
     * sınır: kütükten gelmeyen bir adres, kütüğün kararına da tabi değildir.
     * Biri bir gün kütüğe taşınırsa aşağıdaki tekrar süzgeci onu iki kez
     * listelemekten korur.
     */
    private const array LIVING_PATHS = [
        '/',
        '/about',
        '/terms',
        '/privacy',
        '/kvkk',
        '/distance-sales',
        '/pre-information',
        '/delivery',
        '/refund-policy',
        '/cookies',
        '/marketing-consent',
        // Kurumsal sözleşmeler (FF-228, `docs/107` Faz 3.2).
        '/data-processing',
        '/sla',
        '/acceptable-use',
        '/third-party-licenses',
    ];

    public function __construct(
        private readonly PublicMenuAddressPort $addresses,
        private readonly CanonicalUrl $canonical,
        private readonly ResolvePageDelivery $delivery,
        private readonly ResolveLocaleAlternates $alternates,
    ) {}

    public function __invoke(Request $request): Response
    {
        $base = $request->getSchemeAndHttpHost();
        $entries = [];

        foreach (self::LIVING_PATHS as $path) {
            /*
                EKSİK BİR SÖZLEŞME İNDEKSLENMEZ (FF-216).

                Satıcının kimliği girilmemişken mesafeli satış, ön
                bilgilendirme, teslimat ve hakkımızda metinleri tarafını
                "not yet provided" diye gösterir. O hâlleriyle sayfa yine 200
                döner ve okunabilir — ama arama motoruna sunulmaz. Sitemap ile
                sayfanın kendi robots sinyalinin AYNI cevabı vermesi bu sınıfın
                pazarlığa kapalı kuralıdır.
            */
            if (in_array($path, self::SELLER_IDENTITY_PATHS, true) && ! CompanyProfile::fromConfig()->isComplete()) {
                continue;
            }

            $entries[] = ['loc' => $this->canonical->for($base, $path), 'lastmod' => null, 'alternates' => []];
        }

        foreach ($this->registryEntries($base) as $entry) {
            $entries[] = $entry;
        }

        foreach ($this->addresses->indexableMenus() as $menu) {
            $entries[] = [
                'loc' => $this->canonical->for(
                    $base,
                    // Sitemap adresi, sayfanın kanonik ilan ettiği adresin
                    // AYNISI olmalı — biri `/restoran/`, diğeri `/restaurant/`
                    // derse tarayıcı iki farklı sayfa görür ve ikisini de
                    // yarım indeksler.
                    MenuPublicAddress::fromKeyAndSlug(
                        $menu['key'],
                        $menu['slug'],
                        $menu['locale'],
                    )->path(),
                ),
                'lastmod' => $this->lastModified($menu['published_at']),
                'alternates' => [],
            ];
        }

        return response($this->render($entries), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            // Sitemap sık değişir ve bayat bir kopya, yeni menülerin
            // keşfini geciktirir.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Kütükten türeyen adresler.
     *
     * Süzgeç YOK — daha doğrusu, süzgecin tamamı `ResolvePageDelivery`'nin
     * içinde ve orada, ziyaretçiye verilen cevabı üreten hesabın ta kendisi.
     * Burada `publication_status` sorgusu yazmak kolay olurdu ve YANLIŞ
     * olurdu: ikinci bir kural, birinci kuraldan bir gün ayrılır.
     *
     * @return list<array{loc: string, lastmod: string|null, alternates: array<string, string>}>
     */
    private function registryEntries(string $base): array
    {
        /*
            Ortam YAPILANDIRMADAN okunur, `APP_ENV`'den türetilmez — kapının
            kendi kuralı (`config/content.php`). Staging'de hiçbir sayfa
            sitemap'e girmez ve bu karar da aynı yerden gelir.
        */
        $environment = PageEnvironment::tryFrom((string) config('content.page_environment'))
            ?? PageEnvironment::Production;

        $entries = [];

        /*
            Kütüğün TAMAMI okunur (bugün 402 satır) ve her satır için tek
            karar verilir. Sayfa başına bir sorgu değil: satırlar tek seferde
            gelir, içerik kütüphanesi bellekte. Bu, sayfalama gerektirmeyen bir
            ölçek — ve gerektirdiği gün ölçülüp değişecek olan şey de burası.
        */
        $pages = ContentPage::query()->orderBy('canonical_path')->get();

        foreach ($pages as $page) {
            $decision = $this->delivery->for($page, $environment)->decision;

            if (! $decision->includeInSitemap) {
                continue;
            }

            /*
                Dil karşılıkları aynı süzgeçten geçer: `ResolveLocaleAlternates`
                da `ResolvePageDelivery`'yi kullanıyor. Bu deponun bugünkü
                gerçeği, kuralın neden bu kadar sıkı olduğunu gösteriyor —
                386 Türkçe satırın yalnız 16'sının kaynak dil karşılığı var
                (`config/site-source-paths.php`). Karşılığı OLMAYAN 370 sayfa
                için `/en/...` bir adres uydurmak, arama motoruna 404 vaat
                etmek olurdu.
            */
            $alternates = $this->alternates->handle($page, $environment, $base);

            $entries[] = [
                'loc' => $this->canonical->for($base, $page->canonical_path),
                // Tarih UYDURULMAZ. Kütükte yayın damgası yoksa `lastmod` da
                // yoktur; `now()` yazmak, değişmemiş bir sayfayı her gün
                // yeniden taratmak demekti.
                'lastmod' => $page->published_at?->format('Y-m-d'),
                'alternates' => $alternates['xDefault'] === null
                    ? $alternates['alternates']
                    : $alternates['alternates'] + ['x-default' => $alternates['xDefault']],
            ];
        }

        return $entries;
    }

    /** @param list<array{loc: string, lastmod: string|null, alternates: array<string, string>}> $entries */
    private function render(array $entries): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
        ];

        /*
            AYNI ADRES İKİ KEZ YAZILMAZ. Bugün çakışma yok (ölçüldü: sabit
            dokuz yolun hiçbiri kütükte değil), ama bu süzgeç bugün için değil:
            bir yol kütüğe taşındığı gün, çakışma sessizce oluşur ve tekrar
            eden bir sitemap girdisi arama motoruna aynı sayfayı iki kez ilan
            eder.
        */
        $seen = [];

        foreach ($entries as $entry) {
            if (isset($seen[$entry['loc']])) {
                continue;
            }

            $seen[$entry['loc']] = true;

            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>';

            foreach ($entry['alternates'] as $hreflang => $href) {
                $lines[] = '    <xhtml:link rel="alternate" hreflang="'.htmlspecialchars($hreflang, ENT_XML1 | ENT_QUOTES).'" href="'.htmlspecialchars($href, ENT_XML1 | ENT_QUOTES).'"/>';
            }

            if ($entry['lastmod'] !== null) {
                $lines[] = '    <lastmod>'.$entry['lastmod'].'</lastmod>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    private function lastModified(string $publishedAt): ?string
    {
        if ($publishedAt === '') {
            return null;
        }

        // Uydurulmuş bir tarih, tarih olmamasından kötüdür: arama motoruna
        // değişmemiş bir sayfayı yeniden tarat demektir.
        $timestamp = strtotime($publishedAt);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }
}
