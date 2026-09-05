<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Analytics\UseCase\RecordAnalyticsEvent;
use App\Application\MenuCatalog\Port\OutOfStockPort;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\Publication\UseCase\ResolveGuestMenuView;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\Publication\MenuPublicAddress;
use App\Domain\QrDestination\QrToken;
use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use App\Http\Responses\GuestDeadEnd;
use App\Http\Responses\GuestOutOfService;
use App\Support\Analytics\VisitorKey;
use App\Support\Localization\GuestLocale;
use App\Support\Localization\GuestText;
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
            Dil YANITTAN ÖNCE çözülür: artık iki metin haritası ona bağlı —
            menünün kendi metinleri ve, şube kapalıysa, üstündeki şerit.
        */
        $guestLocale = GuestLocale::resolve($request, $address['locale']);

        // ŞUBE KAPALI ŞERİDİ (FF-141). Menü GİZLENMEZ; kapalılık menünün
        // ÜSTÜNDE bir cümledir ve kararı `ResolveGuestMenuView` verir —
        // karekod yüzeyi ile kalıcı adres yüzeyi ayrışamaz.
        $closedNotice = $view->closedNotice;

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
                ŞERİT AÇIKKEN HİÇ ÇİZİLMEZ. `null` geçmek, şablona boş bir
                kap vermekten farklıdır: boş kap sayfanın üstünde sebepsiz
                bir boşluk ve ekran okuyucuda boş bir duyuru bölgesi
                bırakırdı.
            */
            'closedText' => $closedNotice === null ? null : $this->guestText->closedNotice(
                $guestLocale,
                $closedNotice->nextOpeningClock,
                $closedNotice->nextOpeningIsoWeekday,
                $closedNotice->nextOpeningIsToday,
            ),
            // Saatin MAKİNEYE okunan hâli: şeridin cümlesi çeviriye bağlıdır,
            // durumu ve saati ise ölçüm ve testler çeviriden bağımsız
            // okuyabilmeli.
            'closedNextOpeningClock' => $closedNotice?->nextOpeningClock,
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
