<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Application\Content\UseCase\BuildBreadcrumbTrail;
use App\Application\Content\UseCase\ResolveLocaleAlternates;
use App\Application\Content\UseCase\ResolvePageDelivery;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageEnvironment;
use App\Domain\Url\CanonicalUrl;
use App\Domain\Url\UrlNormalizer;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Support\Localization\SiteText;
use App\Support\Seo\CorporatePageStructuredData;
use App\Support\Site\SiteShell;
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
        private readonly ResolvePageDelivery $delivery,
        private readonly BuildBreadcrumbTrail $breadcrumbs,
        private readonly CanonicalUrl $canonical,
        private readonly UrlNormalizer $normalizer,
        private readonly ResolveLocaleAlternates $alternates,
        private readonly SiteShell $shell,
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

        /*
            Ortam YAPILANDIRMADAN okunur, `APP_ENV`'den türetilmez
            (`config/content.php`). Türetseydik yerelde ve testte staging
            davranışı çıkardı; asıl tehlike ise tersidir — yapılandırması
            unutulmuş bir sunucunun taslakları 200 ile sunması. Varsayılan
            bu yüzden production.
        */
        $environment = PageEnvironment::tryFrom((string) config('content.page_environment')) ?? PageEnvironment::Production;

        /*
            TEK KARAR (FF-214). Şablon/dış bağlantı elemesi, kapı kararı ve
            "yayında ama metni yok" emniyet kemeri artık burada değil,
            `ResolvePageDelivery`'de — ve aynı nesne `sitemap.xml`i besliyor.
            Ayrı yerlerde durdukları sürece sitemap ile bu denetleyicinin bir
            gün farklı cevap vermesi an meselesiydi (`docs/129`).
        */
        $delivery = $this->delivery->for($page, $environment);
        $decision = $delivery->decision;

        if ($decision->mode === 'not-found') {
            abort(404);
        }

        $stage = $delivery->stage;

        /*
            KABUK — kurumsal sitenin geri kalanıyla AYNI (`docs/100` §2).

            Bu sayfalar kendi belgesini kuruyordu ve hiç gezintisi yoktu:
            yayına alınan ilk kurumsal sayfa, ziyaretçiyi üst çubuğu ve
            altbilgisi olmayan bir adaya bırakırdı. Kabuk verisi artık
            `SiteShell`'den geliyor; kanonik adres ve dil KAYDIN kendisinden,
            istekten değil (`docs/118` E4).
        */
        // Ölçüm kimliği KÜTÜKTEN gelir (`page_key`), adresten türetilmez:
        // bir sayfa `/tr/urun/qr-menu/`den başka bir yola taşınsa bile
        // geçmiş raporlar ikiye bölünmemeli (`docs/100` Faz 3).
        $shell = $this->shell->context($request, $page->page_key, $page->canonical_path, $page->locale);

        /*
            AŞAMA ETİKETİ SAYFANIN DEĞİL, ÜRÜNÜN dilindedir (FF-249).

            "Hazırlanıyor" bir kurumsal sayfanın YAZILMIŞ metni değil, ürünün
            kendi etiketidir ve katalogdan gelir; katalog ise yalnız sunulan
            dillerde tamdır (`i18n.shipped_locales`). Burada `$page->locale`
            kullanılıyordu: `/tr/…` altındaki bir kayıt, Türkçe kataloğun 227
            metninden 135'i boşken yarım Türkçe bir kabuk çizerdi. Sayfanın
            kendi dili `<html lang>`de duruyor ve orada doğrudur.
        */
        $locale = $shell['lang']->ui;

        /*
            `mode === 'content'` ise metnin VAR OLDUĞU zaten kararın içinde:
            metin yoksa karar "iskeleti var, içeriği yok"a düşürülmüş olurdu
            (`ResolvePageDelivery`). Ziyaretçiye gösterilen aşama da oradan
            geliyor — fişte "yayında" yazıp 404 dönmek, anlamsız bir çelişki
            göstermek olurdu.
        */
        if ($decision->mode === 'content' && $delivery->content !== null) {
            return $this->withRobots(
                $this->renderContent($request, $page, $delivery->content, $environment, $shell),
                $decision->robots,
            );
        }

        $response = $this->withRobots(
            response()->view('content.under-construction', $shell + [
                'page' => $page,
                'stage' => $this->siteText->get($stage->translationKey(), $locale),
                'isMaintenance' => $decision->mode === 'maintenance',
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
        array $shell,
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

        /*
            Dil karşılıkları YALNIZ yayınlanmış sayfada ilan edilir; bu yüzden
            burada, "hazırlanıyor" dalında değil. Bir 404 gövdesinin hreflang
            taşıması, var olmayan bir sayfanın karşılığı olduğunu söylemekti.
        */
        $localeAlternates = $this->alternates->handle(
            $page,
            $environment,
            $request->getSchemeAndHttpHost(),
        );

        return response()->view('content.page', $shell + [
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
            'localeAlternates' => $localeAlternates['alternates'],
            'xDefaultUrl' => $localeAlternates['xDefault'],
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

            if ($target === null) {
                continue;
            }

            if (! $this->delivery->for($target, $environment)->decision->isLinkable()) {
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
