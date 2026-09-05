<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\MenuCatalog\Port\OutOfStockPort;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\Publication\UseCase\ResolveGuestMenuView;
use App\Domain\Publication\MenuPublicAddress;
use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use App\Http\Responses\GuestDeadEnd;
use App\Http\Responses\GuestOutOfService;
use App\Support\Localization\GuestLocale;
use App\Support\Localization\GuestText;
use App\Support\Seo\MenuStructuredData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Yayınlanan menünün KALICI herkese açık adresi:
 * `/restoran/pasa-doner/menu/ab12cd34ef` (`docs/105` §4.2).
 *
 * Kimlik `key`'dir, `slug` yalnız okunabilirliktir. Yanlış veya eski bir
 * slug ile gelen istek doğru adrese **kalıcı olarak** yönlendirilir — yani
 * restoran adını değiştirdiğinde paylaşılmış bağlantılar ölmez, kendini
 * onarır.
 *
 * Bu adres, basılı QR token'ından ayrıdır ve bilerek öyledir: token bir
 * kodun anahtarıdır ve `/q/` yüzeyi hız sınırlıdır; onu sitemap'te
 * yayımlamak, taranmasını engellemeye çalıştığımız uzayı teslim etmek
 * olurdu (`docs/38` §21).
 */
final class ShowPublicMenuByKeyController extends Controller
{
    public function __construct(
        private readonly PublicMenuAddressPort $addresses,
        private readonly CanonicalUrl $canonical,
        private readonly OutOfStockPort $outOfStock,
        private readonly GuestText $guestText,
        private readonly ResolveGuestMenuView $guestMenuView,
    ) {}

    public function __invoke(Request $request): SymfonyResponse
    {
        /*
            Anahtar ROTA ADINDAN okunur, konumdan değil (FF-116). Üç rota bu
            denetleyiciye bağlı ve segment sayıları farklı:
            `/menu/{key}/{slug?}`, `/{type}/{business}/menu/{key}` ve
            `/{type}/menu/{key}`. Konumsal parametre kullanmak, bir gün
            yanlış segmenti anahtar sanmak demekti.
        */
        $key = (string) $request->route('key');

        if (! MenuPublicAddress::isKey($key)) {
            return GuestDeadEnd::respond($request);
        }

        $address = $this->addresses->findByPublicKey($key);

        if ($address === null) {
            return GuestDeadEnd::respond($request);
        }

        /*
            ADRES ŞUBEYE GÖTÜRÜR, SAAT MENÜYÜ SEÇER (sahibin 2026-09-05
            kararı, `docs/109` §7.1).

            `key` hâlâ kimliktir ve hâlâ değişmez: kartvizite basılan adres
            aynı kalır. Ama artık bir şubenin birden çok menüsü olabiliyor
            ve o adres, şubenin O ANDA servis ettiği menüyü açar. Menü
            başına ayrı bir adres vermek, aynı içeriği iki adreste
            indeksletirdi ve sahip hangisini basacağını bilemezdi.

            SERVİS DIŞI SAAT AYRI BİR HÂLDİR (FF-139). O saatin menüsü
            yayınlanmamışsa burada 404 dönmek, duran bir restorana "menü
            bulunamadı" dedirtirdi. `null` yalnız gerçekten gösterilecek
            hiçbir şey olmadığında gelir ve tek tip çıkmaz sokağa düşer.
        */
        $view = $this->guestMenuView->forAddressedMenu($address['workspace_id'], $address['menu_id']);

        if ($view === null) {
            return GuestDeadEnd::respond($request);
        }

        $menuAddress = MenuPublicAddress::fromKeyAndSlug(
            $address['key'],
            $address['slug'],
            // Segmentin dili İŞLETMENİN dilidir: bir Türk restoranının adresi
            // ziyaretçi İngilizce seçti diye `/restaurant/` olmaz. Menünün tek
            // bir kanonik adresi vardır.
            $address['locale'],
        );

        $canonicalPath = $menuAddress->path();

        if ($request->getPathInfo() !== $canonicalPath) {
            /*
                Kalıcı: slug değiştiyse eski adres ölmez, doğru adrese taşınır.

                DİL SEÇİMİ KORUNUR (`docs/85`). Misafir dil bağlantısına
                bastığında istek slugsuz adrese gidiyor; sorgu düşseydi seçim
                daha yolun başında kaybolur ve düğme çalışmıyor görünürdü.
            */
            $requestedLanguage = $request->query('lang');

            if (is_string($requestedLanguage) && GuestLocale::isSupported($requestedLanguage)) {
                $canonicalPath .= '?lang='.strtolower($requestedLanguage);
            }

            return redirect($canonicalPath, 301);
        }

        /*
            SERVİS DIŞI. Kanonik adrese GELDİKTEN SONRA bakılır: yanlış slug
            ile paylaşılmış bir bağlantı, servis dışı saatte de doğru adrese
            taşınmalı — yoksa misafir bağlantıyı kaydedip yarın açtığında
            hâlâ eski adreste olurdu.
        */
        $publication = $view->publication;

        if ($publication === null) {
            return GuestOutOfService::respond(
                $request,
                $address['brand_name'],
                $address['locale'],
                $view->nextServiceClock,
            );
        }

        /*
            ARAYÜZ dili ile İÇERİK dili AYRIDIR (`docs/85`): ürün adlarını
            restoran kendi dilinde yazar ve biz onları çevirmiyoruz.

            Seçim YANITTAN ÖNCE çözülür çünkü artık iki metin haritası ona
            bağlı: menünün kendi metinleri ve —şube kapalıysa— üstündeki
            şerit. İkisini ayrı yerlerde çözseydik bir gün ayrı dillere
            düşerlerdi.
        */
        $guestLocale = GuestLocale::resolve($request, $address['locale']);

        // Buraya analitik YAZILMAZ ve bu bilinçlidir. Ürünün ölçtüğü şey
        // "QR çözümlemesi" ve "menü açılışı"dır; arama motorundan gelen bir
        // ziyaretçi bir karekod taramamıştır. Onu tarama gibi kaydetmek,
        // ürünün birincil metriğini sessizce şişirirdi.
        //
        // Web ziyaret analitiği ise AYRI bir kanaldan ölçülür: sayfa
        // `zabuno_tenant_id` ile GTM'e bir görüntüleme bildirir. Böylece
        // "kaç kez tarandı" ile "kaç kez görüntülendi" birbirine karışmadan,
        // ikisi de tenant bazında görünür (docs/46).

        return response()->view('public-menu', [
            'snapshot' => $publication->snapshot,
            // "Bugün tükendi" YAYINDAN bağımsız okunur (`docs/82`): balık
            // servis sırasında biter ve yayın beklemek hem yavaş hem
            // tehlikelidir — sahibin taslağında yarım kalmış bir fiyat
            // düzenlemesi olabilir.
            'outOfStockItemIds' => $this->outOfStock->forMenu($publication->menuId),
            // Ölçüm betiği menünün KALICI ANAHTARINI gönderir; kiracıyı
            // istemciden almak, herkesin herkesin adına olay yazması
            // demekti (`docs/84`).
            'menuKey' => $address['key'],
            // Ürün adresini ŞABLON KURMAZ: adres kuralı alan katmanındadır ve
            // Blade'e sızarsa bir gün ikisi ayrışır (FF-116).
            'itemPathFor' => fn (int $menuItemId, string $productName): string => $menuAddress->itemPath($menuItemId, $productName),
            // Metin ŞABLONDA değil KATALOGDA yaşar: Blade'e yazılan bir
            // cümleyi sahip hiçbir PO dosyasından çeviremez (`docs/82`).
            /*
                ARAYÜZ dili ile İÇERİK dili AYRIDIR (`docs/85`).

                Ürün adlarını restoran kendi dilinde yazar ve biz onları
                çevirmiyoruz. Arayüzü İngilizceye alan misafire menünün de
                İngilizce olacağını ima etmek, tutulmayacak bir söz vermek
                olurdu.
            */
            'guestLocale' => $guestLocale,
            'guestText' => $this->guestText->all(
                $guestLocale,
                $this->countCategories($publication->snapshot),
                $this->countItems($publication->snapshot),
            ),
            /*
                ŞUBE KAPALI ŞERİDİ (FF-141). Menü GİZLENMEZ; kapalılık menünün
                ÜSTÜNDE bir cümledir. Karar `ResolveGuestMenuView` içinde
                verilir, böylece karekod yüzeyi ile kalıcı adres yüzeyi
                ayrışamaz.

                Şablona KARARIN KENDİSİ geçirilir, cümlesi değil (FF-143);
                cümleyi kuran ve çizen tek yer ortak parçadır.
            */
            'closedNotice' => $view->closedNotice,
            // Kimlik alanı EKLENMEDEN önce yayınlanmış menüler için canlı
            // ad yedektir (`docs/75`). Donmuş bir değer varsa şablon ona
            // bakar, buraya değil.
            'fallbackBrandName' => $address['brand_name'],
            'analyticsContext' => [
                'zabuno_surface' => 'menu',
                'zabuno_tenant_id' => (string) $address['workspace_id'],
                'zabuno_menu_id' => (string) $view->servingMenuId,
            ],
            'canonicalUrl' => $canonicalUrl = $this->canonical->for($request->getSchemeAndHttpHost(), $canonicalPath),
            'contentLocale' => $address['locale'] !== '' ? $address['locale'] : null,
            'structuredData' => json_encode(
                MenuStructuredData::forMenu(
                    $publication->snapshot,
                    $canonicalUrl,
                    // Arama motoruna da YAYINLANMIŞ ad gösterilir; sayfada
                    // yazan ad ile yapılandırılmış veri ayrışmamalı.
                    (string) ($publication->snapshot['identity']['brandName'] ?? '') !== ''
                        ? (string) $publication->snapshot['identity']['brandName']
                        : $address['brand_name'],
                ),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
            ),
        ], 200)
            // Seçim AYNI CİHAZDA hatırlanır: misafir her açılışta dili
            // yeniden seçmemeli (`docs/85`). `httpOnly` değil çünkü bir
            // kimlik taşımıyor; `Lax` çünkü başka sitelerden gelen isteklerin
            // dili değiştirmesine gerek yok.
            ->cookie(GuestLocale::COOKIE, $guestLocale, 60 * 24 * 365, '/', null, $request->isSecure(), false, false, 'Lax');
    }

    /** @param  array<string, mixed>  $snapshot */
    private function countCategories(array $snapshot): int
    {
        return count($snapshot['categories'] ?? []);
    }

    /** @param  array<string, mixed>  $snapshot */
    private function countItems(array $snapshot): int
    {
        $count = 0;

        foreach ($snapshot['categories'] ?? [] as $category) {
            $count += count($category['menuItems'] ?? []);
        }

        return $count;
    }
}
