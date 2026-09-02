<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\MenuCatalog\Port\OutOfStockPort;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Domain\Publication\MenuPublicAddress;
use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use App\Http\Responses\GuestDeadEnd;
use App\Support\Localization\GuestLocale;
use App\Support\Localization\GuestText;
use App\Support\Seo\MenuStructuredData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Yayınlanan menünün KALICI herkese açık adresi: `/menu/{key}/{slug}`.
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
        private readonly PublicationRepositoryPort $publications,
        private readonly CanonicalUrl $canonical,
        private readonly OutOfStockPort $outOfStock,
        private readonly GuestText $guestText,
    ) {}

    public function __invoke(Request $request, string $key, ?string $slug = null): SymfonyResponse
    {
        if (! MenuPublicAddress::isKey($key)) {
            return GuestDeadEnd::respond($request);
        }

        $address = $this->addresses->findByPublicKey($key);

        if ($address === null) {
            return GuestDeadEnd::respond($request);
        }

        $publication = $this->publications->current($address['workspace_id'], $address['menu_id']);

        if ($publication === null) {
            return GuestDeadEnd::respond($request);
        }

        $canonicalPath = MenuPublicAddress::fromKeyAndSlug($address['key'], $address['slug'])->path();

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
            // Metin ŞABLONDA değil KATALOGDA yaşar: Blade'e yazılan bir
            // cümleyi sahip hiçbir PO dosyasından çeviremez (`docs/82`).
            /*
                ARAYÜZ dili ile İÇERİK dili AYRIDIR (`docs/85`).

                Ürün adlarını restoran kendi dilinde yazar ve biz onları
                çevirmiyoruz. Arayüzü İngilizceye alan misafire menünün de
                İngilizce olacağını ima etmek, tutulmayacak bir söz vermek
                olurdu.
            */
            'guestLocale' => $guestLocale = GuestLocale::resolve($request, $address['locale']),
            'guestText' => $this->guestText->all(
                $guestLocale,
                $this->countCategories($publication->snapshot),
                $this->countItems($publication->snapshot),
            ),
            // Kimlik alanı EKLENMEDEN önce yayınlanmış menüler için canlı
            // ad yedektir (`docs/75`). Donmuş bir değer varsa şablon ona
            // bakar, buraya değil.
            'fallbackBrandName' => $address['brand_name'],
            'analyticsContext' => [
                'zabuno_surface' => 'menu',
                'zabuno_tenant_id' => (string) $address['workspace_id'],
                'zabuno_menu_id' => (string) $address['menu_id'],
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
