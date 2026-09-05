<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Application\Content\Port\ContentLibraryPort;
use App\Application\Content\UseCase\BuildBreadcrumbTrail;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageEnvironment;
use App\Domain\Content\PageGate;
use App\Domain\Content\PagePublicationStatus;
use App\Domain\Url\CanonicalUrl;
use App\Domain\Url\UrlNormalizer;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Support\Localization\SiteText;
use App\Support\Seo\CorporatePageStructuredData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Kurumsal sitenin TEK giriş kapısı — FF-117, yönerge §3 ve §7.
 *
 * Site haritasındaki 414 yol için 414 denetleyici ya da 414 Blade dosyası
 * üretilmez. Her yol kütükte bir kayıttır; bu denetleyici o kaydı bulur,
 * `PageGate`'e sorar ve kararı uygular. Bir sayfayı açmak için koddan bir
 * bileşen silinmez — yalnız kontrollü yayın durumu değişir.
 */
final class ShowCorporatePageController extends Controller
{
    /**
     * Bakım yanıtının `Retry-After` değeri.
     *
     * Gerçekçi olmalı ve uydurulmamalı: yarım saat, kısa bir bakım için dürüst
     * bir tahmindir. Uzun sürecek bir iş 503 değil, planlı bir yayın durumu
     * meselesidir.
     */
    private const int RETRY_AFTER_SECONDS = 1800;

    public function __construct(
        private readonly SiteText $siteText,
        private readonly ContentLibraryPort $library,
        private readonly BuildBreadcrumbTrail $breadcrumbs,
        private readonly CanonicalUrl $canonical,
        private readonly UrlNormalizer $normalizer,
    ) {}

    public function __invoke(Request $request): SymfonyResponse
    {
        $path = rtrim($request->getPathInfo(), '/').'/';

        $page = ContentPage::query()->where('canonical_path', $path)->first();

        // Kütükte olmayan bir yol için hazırlanıyor ekranı göstermek, olmayan
        // bir sayfayı yapıyormuş gibi göstermek olurdu.
        if ($page === null) {
            abort(404);
        }

        // Şablon bir DESENDİR (`/tr/blog/{slug}/`), bir sayfa değil. Dış
        // bağlantı da bu sitede bir sayfa değildir.
        if ($page->is_template || $page->is_external) {
            abort(404);
        }

        /*
            Ortam YAPILANDIRMADAN okunur, `APP_ENV`'den türetilmez
            (`config/content.php`). Türetseydik yerelde ve testte staging
            davranışı çıkardı; asıl tehlike ise tersidir — yapılandırması
            unutulmuş bir sunucunun taslakları 200 ile sunması. Varsayılan
            bu yüzden production.
        */
        $environment = PageEnvironment::tryFrom((string) config('content.page_environment')) ?? PageEnvironment::Production;

        $decision = PageGate::decide(
            $page->status(),
            $environment,
            /*
                Önizleme yetkisi HENÜZ YOK: imzalı önizleme token'ı bu paketin
                dışında. Varsayılanı `false` bırakmak, yanlış tarafta hata
                yapmamak demek — `true` bırakmak taslakları herkese açardı.
            */
            false,
            $page->was_ever_published,
        );

        if ($decision->mode === 'not-found') {
            abort(404);
        }

        $locale = SiteText::pick($page->locale);
        $stage = $page->status();

        if ($decision->mode === 'content') {
            $content = $this->library->find($page->page_key, $page->locale);

            /*
                YAYINDA AMA İÇERİĞİ YOK — son emniyet kemeri.

                Kütükteki durum elle ileri sürülebilir; kalite kapısı bir
                süreçtir, bir kilit değil. Böyle bir sayfayı 200 ile sunmak,
                kapının en baştan engellemek için var olduğu şeyi üretirdi:
                hiçbir soruya cevap vermeyen ince bir sayfa. Doğru cevap
                "burada henüz bir şey yok"tur.

                Bu aynı zamanda `docs/118` E4'ün bugünkü hâlidir: Türkçe içerik
                yuvası bilerek boş, dolayısıyla Türkçe adres yayına alınsa bile
                404 kalır.
            */
            if ($content !== null) {
                return $this->withRobots(
                    $this->renderContent($request, $page, $content, $environment, $locale),
                    $decision->robots,
                );
            }

            /*
                Ziyaretçiye gösterilen AŞAMA da düzeltilir: kütük "yayında"
                diyor ama gösterilecek bir metin yok. Fişte "yayında" yazıp
                404 dönmek, ziyaretçiye anlamsız bir çelişki göstermek olurdu;
                gerçek durum "iskeleti var, içeriği yok"tur.
            */
            $stage = PagePublicationStatus::Scaffolded;
            $decision = PageGate::decide($stage, $environment, false, false);
        }

        $response = $this->withRobots(
            response()->view('content.under-construction', [
                'page' => $page,
                'stage' => $this->siteText->get($stage->translationKey(), $locale),
                'isMaintenance' => $decision->mode === 'maintenance',
                'st' => $this->siteText->all($locale),
            ], $decision->statusCode),
            $decision->robots,
        );

        if ($decision->statusCode === 503) {
            $response->headers->set('Retry-After', (string) self::RETRY_AFTER_SECONDS);
        }

        return $response;
    }

    private function renderContent(
        Request $request,
        ContentPage $page,
        PageContent $content,
        PageEnvironment $environment,
        string $locale,
    ): SymfonyResponse {
        $trail = $this->breadcrumbs->handle($page, $environment);

        /*
            Kanonik adres KÜTÜKTEN gelir (istekten değil: aynı içeriğe izleme
            parametresiyle ya da farklı bir yazımla ulaşılabilir), ama URL
            MOTORUNDAN geçirilir.

            İkincisi ölçülerek öğrenildi: kütük yolları site haritasının
            yazımıyla, sondaki bölü çizgisiyle duruyor; gerçek sunucu ise
            `config/url-policy.php` gereği o çizgiyi 301 ile atıyor. Ham yolu
            `<link rel=canonical>` yapmak, sayfanın kendi kanonik adresinin bir
            YÖNLENDİRMEYİ göstermesi demekti — arama motoruna verilebilecek en
            kafa karıştırıcı sinyallerden biri. (Test istemcisi bunu gizliyor:
            yolu isteği kurmadan önce kendisi normalleştiriyor.)
        */
        $canonicalUrl = $this->canonical->for($request->getSchemeAndHttpHost(), $page->canonical_path);

        return response()->view('content.page', [
            'page' => $page,
            'content' => $content,
            'trail' => $trail,
            'relatedLinks' => $this->relatedLinks($content, $environment),
            'structuredData' => json_encode(
                CorporatePageStructuredData::forPage(
                    contentType: $page->content_type,
                    content: $content,
                    canonicalUrl: $canonicalUrl,
                    siteUrl: $request->getSchemeAndHttpHost(),
                    trail: $trail,
                ),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
            'canonicalUrl' => $canonicalUrl,
            'pageKey' => $page->page_key,
            'pageLocale' => $page->locale,
            'anchorPrefix' => '/',
            'coreModuleCount' => count((array) config('core-modules')),
            'st' => $this->siteText->all($locale),
        ], 200);
    }

    /**
     * "İlgili sayfalar" — YALNIZ gerçekten açılan sayfalar.
     *
     * Süzgeç şablonda değil burada: bağlantı verilebilirlik `PageGate`'in
     * kararıdır ve o karar tek bir yerde yaşar (`PageRenderDecision::isLinkable`).
     * Yayınlanmamış bir sayfaya bağlantı vermek hem ziyaretçiyi 404'e
     * göndermek hem de deponun kendi bozuk-bağlantı kapısını kırmak olurdu.
     *
     * @return list<array{path: string, label: string}>
     */
    private function relatedLinks(PageContent $content, PageEnvironment $environment): array
    {
        $block = $content->block(BlockType::Related);

        if ($block === null) {
            return [];
        }

        $links = [];

        foreach ($block->entries as $entry) {
            if ($entry->pageKey === null) {
                continue;
            }

            $target = ContentPage::query()
                ->where('page_key', $entry->pageKey)
                ->where('locale', $content->locale)
                ->first();

            if ($target === null || $target->is_template || $target->is_external) {
                continue;
            }

            if (! PageGate::decide($target->status(), $environment, false, $target->was_ever_published)->isLinkable()) {
                continue;
            }

            // Kırıntıdaki ile aynı kural: bağlantı, sunucunun yönlendirmeden
            // sunduğu adrese gider.
            $links[] = [
                'path' => $this->normalizer->normalize($target->canonical_path)->target(),
                'label' => $entry->text,
            ];
        }

        return $links;
    }

    private function withRobots(SymfonyResponse $response, string $robots): SymfonyResponse
    {
        // Robots kararı KAPIDAN gelir; şablonda ikinci kez yazılmaz.
        $response->headers->set('X-Robots-Tag', str_replace(',', ', ', $robots));

        return $response;
    }
}
