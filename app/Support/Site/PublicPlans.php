<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Application\Billing\UseCase\ListPlanCatalog;
use App\Support\Localization\SiteText;
use App\Support\Money\PriceLabel;
use Throwable;

/**
 * Plan kataloğunun HERKESE AÇIK görünümü — tek yer (FF-251).
 *
 * ═══ NEDEN TAŞINDI ═══
 *
 * Bu projeksiyon `FoundationStatusController::publicPlans()` içinde
 * yaşıyordu ve orada tek müşterisi vardı. Yatırımcı sayfası da fiyatı
 * gösterince ikinci müşteri doğdu — ve ikinci bir kopya yazmak, "ücretsiz mi,
 * fiyatlanmamış mı, fiyatlı mı" ayrımının iki yerde iki ayrı cevabı olması
 * demekti. O ayrım bir biçimlendirme tercihi değil bir DÜRÜSTLÜK kararıdır
 * (`docs/88`): tutarı girilmemiş bir planı "ücretsiz" göstermek, tutulmayacak
 * bir söz vermektir.
 *
 * Mantık DEĞİŞMEDİ, yalnız yerini değiştirdi; davranışı ölçen testler
 * (`PublicPricingPageTest`) aynen duruyor.
 */
final class PublicPlans
{
    public function __construct(
        private readonly ListPlanCatalog $plans,
        private readonly SiteText $siteText,
    ) {}

    /**
     * Yalnız ad, biçimlendirilmiş fiyat ve hak listesi geçer: iç kimlikler,
     * sürüm numaraları ve sıralama alanları ziyaretçinin işi değil.
     *
     * @return list<array{name: string, price: ?string, free: bool, entitlements: list<string>}>
     */
    public function forLocale(string $locale): array
    {
        try {
            $plans = $this->plans->handle();
        } catch (Throwable) {
            /*
                KATALOG OKUNAMAZSA SAYFA ÖLMEZ.

                Bu sayfalar bugüne kadar tamamen statikti; fiyatı katalogdan
                okumak onlara bir veritabanı bağımlılığı ekledi. Veritabanı
                bir an tökezlediğinde tanıtım sitesinin tamamının 500 vermesi,
                fiyat göstermemekten çok daha kötü olurdu — ziyaretçi ürünün
                çöktüğünü görür.

                Boş liste, sayfanın dürüst boş hâline düşer: "fiyatlar henüz
                yayımlanmadı, bize yazın".
            */
            return [];
        }

        $siteText = $this->siteText;

        return array_map(
            static function ($plan) use ($siteText, $locale): array {
                /*
                    ÜÇ AYRI DURUM, üç ayrı cevap.

                    - Tutar YOK (`null`): fiyatlanmamış — "bize yazın".
                    - Tutar SIFIR: ücretsiz. `0,00 TRY` teknik olarak doğru
                      ama insan onu "ücretsiz" diye okumaz, bir hata sanır.
                    - Tutar var: biçimlendirilmiş fiyat.
                */
                if ($plan->amountMinor === null || $plan->currency === null) {
                    $price = null;
                    $free = false;
                } elseif ($plan->amountMinor === 0) {
                    $price = null;
                    $free = true;
                } else {
                    $price = PriceLabel::for($plan->amountMinor, $plan->currency);
                    $free = false;
                }

                return [
                    'name' => $plan->name,
                    'price' => $price,
                    'free' => $free,
                    // Ham anahtar basmak sessizce geliştirici dilini
                    // sızdırmak olurdu; tanınmayan anahtar hiç gösterilmez.
                    'entitlements' => array_values(array_filter(array_map(
                        static fn (string $key): ?string => $siteText->entitlementLabel($key, $locale),
                        $plan->entitlements,
                    ))),
                ];
            },
            $plans,
        );
    }
}
