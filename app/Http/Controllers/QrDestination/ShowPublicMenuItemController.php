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
use App\Support\Seo\MenuItemStructuredData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Tek bir ürünün herkese açık adresi — `docs/105` §4.3.
 *
 *     /restoran/pasa-doner/menu/ab12cd34ef/urun/101-adana-kebap
 *
 * Sahibin ilk örneği `#item=101` idi. Fragment sunucuya HİÇ ulaşmaz:
 * indekslenmez, ayrı bir görüntüleme olarak ölçülemez ve paylaşılan bir
 * bağlantıda hangi ürün olduğu sunucu tarafından bilinemez. Yol segmenti
 * üçünü de yapar. Menü sayfasının kendi çıpası (`#item-101`) yerinde kalır ve
 * çalışmaya devam eder — biri diğerinin yerine geçmez.
 *
 * KALİTE KAPISI. Anlatacak şeyi olmayan bir ürün sayfası indekslenmez: adı ve
 * fiyatı olan ama açıklaması, görseli ve alerjeni olmayan bir sayfa, menüdeki
 * satırın kopyasıdır. Yüzlerce böyle sayfayı aramaya açmak programatik SEO'nun
 * tam olarak yapmaması gereken şeydir (`docs/105` §2.2, yönerge §13.4).
 */
final class ShowPublicMenuItemController extends Controller
{
    public function __construct(
        private readonly PublicMenuAddressPort $addresses,
        private readonly PublicationRepositoryPort $publications,
        private readonly CanonicalUrl $canonical,
        private readonly OutOfStockPort $outOfStock,
        private readonly GuestText $guestText,
    ) {}

    public function __invoke(Request $request): SymfonyResponse
    {
        $key = (string) $request->route('key');

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

        // Kimlik segmentin BAŞINDADIR: `101-adana-kebap`. Slug yalnız
        // okunabilirliktir ve yanlışsa adres kendini onarır.
        $itemId = (int) (explode('-', (string) $request->route('item'))[0] ?? 0);

        $found = $this->findItem($publication->snapshot, $itemId);

        if ($found === null) {
            // Olmayan ürün, olmayan menüyle AYNI çıkmaz sokağa düşer: özel bir
            // hata metni rota şeklini ifşa eder.
            return GuestDeadEnd::respond($request);
        }

        [$item, $categoryName] = $found;

        $menuAddress = MenuPublicAddress::fromKeyAndSlug(
            $address['key'],
            $address['slug'],
            $address['locale'],
        );

        $canonicalPath = $menuAddress->itemPath($itemId, (string) $item['productName']);

        if ($request->getPathInfo() !== $canonicalPath) {
            return redirect($canonicalPath, 301);
        }

        $guestLocale = GuestLocale::resolve($request, $address['locale']);
        $canonicalUrl = $this->canonical->for($request->getSchemeAndHttpHost(), $canonicalPath);
        $soldOut = in_array($itemId, $this->outOfStock->forMenu($publication->menuId), true);

        $response = response()->view('public-menu-item', [
            'item' => $item,
            'itemId' => $itemId,
            'categoryName' => $categoryName,
            'soldOut' => $soldOut,
            'menuPath' => $menuAddress->path(),
            'menuKey' => $address['key'],
            'brandName' => (string) ($publication->snapshot['identity']['brandName'] ?? '') !== ''
                ? (string) $publication->snapshot['identity']['brandName']
                : $address['brand_name'],
            'guestLocale' => $guestLocale,
            'guestText' => $this->guestText->all($guestLocale),
            'contentLocale' => $address['locale'] !== '' ? $address['locale'] : null,
            'canonicalUrl' => $canonicalUrl,
            'analyticsContext' => [
                'zabuno_surface' => 'menu_item',
                'zabuno_tenant_id' => (string) $address['workspace_id'],
                'zabuno_menu_id' => (string) $address['menu_id'],
            ],
            'structuredData' => json_encode(
                MenuItemStructuredData::forItem($item, $canonicalUrl, $categoryName, [
                    'name' => $address['brand_name'],
                    'url' => $this->canonical->for($request->getSchemeAndHttpHost(), $menuAddress->path()),
                ]),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
            ),
        ], 200);

        if (! self::hasSomethingToSay($item)) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response->cookie(
            GuestLocale::COOKIE,
            $guestLocale,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            false,
            false,
            'Lax',
        );
    }

    /**
     * Ürünün menüdeki satırdan FAZLA bir şeyi var mı?
     *
     * Ad ve fiyat menüde zaten yazıyor. Açıklama, görsel ya da alerjen bilgisi
     * yoksa ürün sayfası o satırın kopyasıdır ve indekslenmemelidir. Aynı kural
     * menü sayfasında da geçerlidir: hiçbir yere götürmeyen bir bağlantı
     * kurulmaz.
     *
     * @param  array<string, mixed>  $item
     */
    public static function hasSomethingToSay(array $item): bool
    {
        if (trim((string) ($item['description'] ?? '')) !== '') {
            return true;
        }

        if (($item['image'] ?? null) !== null) {
            return true;
        }

        return ($item['allergens'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{0: array<string, mixed>, 1: string}|null
     */
    private function findItem(array $snapshot, int $itemId): ?array
    {
        if ($itemId <= 0) {
            return null;
        }

        foreach ($snapshot['categories'] ?? [] as $category) {
            foreach ($category['menuItems'] ?? [] as $item) {
                if ((int) ($item['menuItemId'] ?? 0) === $itemId) {
                    return [$item, (string) ($category['name'] ?? '')];
                }
            }
        }

        return null;
    }
}
