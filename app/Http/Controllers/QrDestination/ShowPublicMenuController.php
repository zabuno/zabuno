<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Analytics\UseCase\RecordAnalyticsEvent;
use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Application\MenuCatalog\Port\OutOfStockPort;
use App\Application\Ordering\Port\OrderingSwitchPort;
use App\Application\Publication\Dto\PublicationRecord;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\Publication\UseCase\ResolveGuestMenuView;
use App\Application\QrDestination\Dto\QrCodeRecord;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\Entitlement\Entitlement;
use App\Domain\Publication\MenuPublicAddress;
use App\Domain\QrDestination\QrToken;
use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use App\Http\Responses\GuestDeadEnd;
use App\Http\Responses\GuestOutOfService;
use App\Support\Analytics\VisitorKey;
use App\Support\Localization\GuestLocale;
use App\Support\Localization\GuestText;
use App\Support\Money\MoneyFormatContract;
use App\Support\Seo\MenuStructuredData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class ShowPublicMenuController extends Controller
{
    public function __construct(
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly RecordAnalyticsEvent $recordAnalyticsEvent,
        private readonly CanonicalUrl $canonical,
        private readonly PublicMenuAddressPort $addresses,
        private readonly OutOfStockPort $outOfStock,
        private readonly GuestText $guestText,
        private readonly ResolveGuestMenuView $guestMenuView,
        private readonly EntitlementRepositoryPort $entitlements,
        private readonly OrderingSwitchPort $orderingSwitch,
    ) {}

    public function __invoke(Request $request, string $token): SymfonyResponse
    {
        try {
            $qrToken = QrToken::fromString($token);
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }

        $record = $this->qrCodes->findActiveByToken($qrToken->value());

        if ($record === null) {
            return $this->notFound();
        }

        /*
            AYNI KOD, SAATE GÖRE DOĞRU MENÜ (sahibin 2026-09-05 kararı,
            `docs/109` §7.1).

            Karekodun hedefi değişmez ve bu satır onu değiştirmez: kod hâlâ
            aynı menüye bağlıdır. Değişen şey, o bağın hangi ŞUBEYE
            götürdüğü ve o şubenin o saatte hangi menüyü servis ettiğidir.
            Misafir 08:00'de kahvaltıyı, 20:00'de akşam menüsünü görür;
            masadaki kâğıt hiç değişmez.

            Şubede saat tanımlanmamışsa sonuç kodun kendi menüsüdür — tek
            menülü şubeler için bu satır hiçbir şeyi değiştirmez.

            SERVİS DIŞI SAAT AYRI BİR HÂLDİR (FF-139). Sahip bir gece menüsü
            tanımlayıp saatini verip içeriğini yayınlamamış olabilir; o saatte
            masadaki misafire "menü bulunamadı" demek, duran bir restoranı
            kapanmış göstermek olurdu. `null` yalnız gerçekten gösterilecek
            hiçbir şey kalmadığında gelir ve tek tip çıkmaz sokağa düşer.
        */
        $view = $this->guestMenuView->forAddressedMenu($record->workspaceId, $record->menuId);

        if ($view === null) {
            return $this->notFound();
        }

        $address = $this->addresses->findByQrToken($qrToken->value());

        if ($address === null) {
            return $this->notFound();
        }

        $publication = $view->publication;

        if ($publication === null) {
            /*
                MENÜ AÇILIŞI YAZILMAZ. Ölçüm "kaç kez menü açıldı" sorusunu
                cevaplar ve burada açılan bir menü yok; yazsaydık sahibin en
                temel sayacı, hiç kimsenin yemek görmediği gecelerle şişerdi.
                Taramanın kendisi zaten `/q/` ucunda `QrResolve` olarak
                kayıtlıdır, yani ziyaret kaybolmuyor.
            */
            return GuestOutOfService::respond(
                $request,
                $address['brand_name'],
                $address['locale'],
                $view->nextServiceClock,
            );
        }

        $this->recordAnalyticsEvent->handle(
            $record->workspaceId,
            $record->locationId,
            $record->id,
            // Ölçüm, misafirin GERÇEKTEN gördüğü menüyü yazar: kodun bağlı
            // olduğu menüyü yazsaydı "kahvaltı kaç kez açıldı" sorusu
            // sonsuza kadar cevapsız kalırdı.
            $view->servingMenuId,
            AnalyticsEventType::MenuOpen,
            // Ham IP ve tarayıcı bilgisi SAKLANMAZ; yalnız günlük dönen bir
            // tuzla türetilmiş özet yazılır (`docs/68`).
            VisitorKey::forRequest($request, $record->workspaceId, Carbon::now()),
        );

        // Sayfa burada RENDER EDİLİR (yönlendirilmez): misafirin karekodu
        // taradıktan sonra bir sıçrama daha beklemesi için sebep yok ve
        // huninin ikinci yarısı burada ölçülür.
        //
        // Ama KANONİK adres bu değildir: arama motoruna menünün kalıcı
        // adresi gösterilir ve bu sayfa indekslenmez. Böylece token hiçbir
        // zaman sitemap'e girmez ve `/q/` için koyulan hız sınırı anlamlı
        // kalır (`docs/38` §21).
        // Tür segmentinin dili İŞLETMENİN dilidir (FF-116): token sayfası da
        // aynı kanonik adresi göstermek zorunda, yoksa iki yüzey birbirine
        // farklı adres ilan eder.
        $menuAddress = MenuPublicAddress::fromKeyAndSlug(
            $address['key'],
            $address['slug'],
            $address['locale'],
        );

        $canonicalPath = $menuAddress->path();

        /*
            Dil YANITTAN ÖNCE çözülür: menünün kendi metinleri buna bağlı ve
            şube kapalıysa üstündeki şerit de aynı seçime yaslanır.
        */
        $guestLocale = GuestLocale::resolve($request, $address['locale']);

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
                ÜSTÜNDE bir cümledir ve kararı `ResolveGuestMenuView` verir —
                karekod yüzeyi ile kalıcı adres yüzeyi ayrışamaz.

                Şablona KARARIN KENDİSİ geçirilir, cümlesi değil (FF-143):
                cümleyi burada kurmak, misafirin gördüğü dört yüzeyde dört
                ayrı kopya demekti. Çizim ortak parçadadır ve `null` gelen
                şerit HİÇ çizilmez — boş bir kap bile değil.
            */
            'closedNotice' => $view->closedNotice,
            /*
                SEPET — YA GERÇEKTEN ÇALIŞIR, YA HİÇ ÇİZİLMEZ (`docs/115` S3).

                Karar BURADA verilir ve sayfaya basılır; istemci "acaba"
                diye denemez. `null` gelen bir sepet ekranda tek bir düğme
                bile bırakmaz: masadaki misafire basınca hiçbir şey olmayan
                bir düğme göstermek, ona olmayan bir yetenek vaat etmektir
                ve bunu restoran değil ürün öder.
            */
            'ordering' => $this->orderingFor($record, $publication, $guestLocale),
            // Kimlik alanı EKLENMEDEN önce yayınlanmış menüler için canlı
            // ad yedektir (`docs/75`). Donmuş bir değer varsa şablon ona
            // bakar, buraya değil.
            'fallbackBrandName' => $address['brand_name'],
            'analyticsContext' => [
                'zabuno_surface' => 'menu',
                'zabuno_tenant_id' => (string) $record->workspaceId,
                'zabuno_location_id' => (string) $record->locationId,
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
            ->header('X-Robots-Tag', 'noindex, follow')
            ->cookie(GuestLocale::COOKIE, $guestLocale, 60 * 24 * 365, '/', null, $request->isSecure(), false, false, 'Lax');
    }

    /**
     * "BU MASA ŞU AN SİPARİŞ VEREBİLİR Mİ?" — dört şart, tek cevap.
     *
     * Şartların sırası `StoreGuestOrderController` ile AYNI ve bu bilerek:
     * sayfa ile uç aynı soruya farklı cevap verirse, misafir çizilmiş bir
     * düğmeye basar ve sunucudan ret yer. İki yerde iki karar olmasının
     * sebebi kopya değil, YÖN: uç "bu isteği kabul eder miyim" diye sorar,
     * sayfa "bu düğmeyi çizer miyim" diye. Uç kendi kararını yine kendisi
     * verir; buradaki karar onun yerine geçmez, yalnız yapılamayacak işi
     * ekrana çizmez.
     *
     * BEŞİNCİ ŞART PARA BİRİMİDİR ve o yalnız burada var: sepetin toplamı
     * misafirin telefonunda oluşuyor ve iki para biriminden toplanan bir
     * sayı anlamsızdır (`BuildOrderLines::currencyMismatch`). Menü tek bir
     * para birimi kullanmıyorsa sepet çizilmez — yanlış bir toplam, masada
     * ödenen bir yanlıştır.
     *
     * @return array{submitPath:string, money:array<string, mixed>, text:array<string, string>}|null
     */
    private function orderingFor(QrCodeRecord $record, PublicationRecord $publication, string $guestLocale): ?array
    {
        if ($record->diningTableId === null) {
            // Masaya bağlı olmayan kod (afiş, kartvizit, giriş kodu):
            // siparişin düşeceği masa yok.
            return null;
        }

        if (! $this->grantsOrdering($publication->entitlementKeys, $record->workspaceId)) {
            return null;
        }

        if (! $this->orderingSwitch->acceptsOrders($record->workspaceId, $record->locationId)) {
            return null;
        }

        $currency = $this->singleCurrencyOf($publication->snapshot);

        if ($currency === null) {
            return null;
        }

        $money = MoneyFormatContract::for($currency);

        if ($money === null) {
            return null;
        }

        return [
            // Adres SUNUCUDAN basılır. İstemcide kurulsaydı, belirtecin
            // biçimi değiştiği gün sayfa sessizce yanlış uca yazardı.
            'submitPath' => '/q/'.$record->token.'/orders',
            'money' => $money->toArray(),
            'text' => $this->guestText->ordering($guestLocale),
        ];
    }

    /**
     * Sipariş hakkı — ÖNCE YAYINA DONMUŞ hak, yoksa canlı plan.
     *
     * Sıra `StoreGuestOrderController::grantsOrdering()` ile aynıdır ve aynı
     * olmak zorundadır: masadaki basılı karekod aynı kâğıttır ve sahip
     * planını düşürdüğünde o kâğıdın gösterdiği yayın değişmez
     * (`docs/114` §3 Dalga 6). Sayfa canlı planı, uç donmuş hakkı okusaydı
     * misafir sepeti görür ama gönderemezdi.
     *
     * @param  list<string>|null  $frozen
     */
    private function grantsOrdering(?array $frozen, int $workspaceId): bool
    {
        if ($frozen !== null) {
            return in_array(Entitlement::OrderingBasic->value, $frozen, true);
        }

        return $this->entitlements->forWorkspace($workspaceId)->grants(Entitlement::OrderingBasic);
    }

    /**
     * Menünün TEK para birimi — birden çoksa ya da hiç yoksa `null`.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function singleCurrencyOf(array $snapshot): ?string
    {
        $currency = null;

        foreach ($snapshot['categories'] ?? [] as $category) {
            foreach ($category['menuItems'] ?? [] as $item) {
                $code = trim((string) ($item['currencyCode'] ?? ''));

                if ($code === '') {
                    continue;
                }

                $currency ??= $code;

                if ($currency !== $code) {
                    return null;
                }
            }
        }

        return $currency;
    }

    private function notFound(): SymfonyResponse
    {
        // Tarayıcıda ham JSON gören bir misafir, ürünü bozuk sanır.
        // Yanıt her durumda aynıdır (QR-PUBLIC-404-UNIFORM-01).
        return GuestDeadEnd::respond(request());
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
