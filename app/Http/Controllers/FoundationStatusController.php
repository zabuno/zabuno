<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Billing\UseCase\ListPlanCatalog;
use App\Domain\Url\CanonicalUrl;
use App\Support\Localization\SiteText;
use App\Support\Money\PriceLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Throwable;

/**
 * Herkese açık pazarlama ve yasal sayfalar.
 *
 * Bu sayfalar SUNUCUDA üretilir ve React paketini hiç yüklemez. Karar
 * ölçümle alındı: istemcide üretildiklerinde bir tarayıcı botunun gördüğü
 * gövde 1.736 bayttı ve içeriği `<div id="app"></div>` ibaretti — yani
 * ürünün kendi tanıtımı ne arama motorunda ne de JavaScript çalıştırmayan
 * AI botlarında görünüyordu.
 *
 * Etkileşim gerektiren yüzeyler (`/app`, `/platform`) React olarak kalır;
 * burada etkileşim yok, yalnız metin ve bağlantı var.
 */
final class FoundationStatusController extends Controller
{
    /** Yasal sayfa yollarının başlıkları. */
    private const LEGAL_TITLES = [
        'terms' => 'Terms',
        'privacy' => 'Privacy',
        'kvkk' => 'KVKK',
    ];

    public function __construct(
        private readonly CanonicalUrl $canonical,
        private readonly ListPlanCatalog $plans,
        private readonly SiteText $siteText,
    ) {}

    public function __invoke(Request $request): View
    {
        $path = trim($request->getPathInfo(), '/');
        $shared = [
            'coreModuleCount' => count(config('core-modules')),
            'canonicalUrl' => $this->canonical->for($request->getSchemeAndHttpHost(), $request->getPathInfo()),
            // Yasal sayfalarda gezinti çıpaları ana sayfaya işaret eder;
            // burada o başlıklar yok.
            'anchorPrefix' => $path === '' ? '' : '/',
            /*
                FİYAT KAYDOLMADAN GÖRÜLÜR — `docs/88` (P1-01).

                Plan listesi bugüne kadar `auth` + çalışma alanı bağlamı
                ardındaydı: fiyatı görmek için kaydolmak gerekiyordu, yani
                ürün kaydolmayı fiyatı görmeye bağlı kılıyordu.
            */
            'plans' => $this->publicPlans(
                SiteText::pick($request->getPreferredLanguage(['en', 'tr'])),
            ),
            // Metin ŞABLONDA değil KATALOGDA yaşar (`docs/85` ile aynı
            // gerekçe): Blade'e yazılan bir cümleyi sahip hiçbir PO
            // dosyasından çeviremez.
            'st' => $this->siteText->all(
                SiteText::pick($request->getPreferredLanguage(['en', 'tr'])),
            ),
        ];

        if ($path === 'pricing') {
            return view('public.pricing', $shared);
        }

        if (isset(self::LEGAL_TITLES[$path])) {
            return view('public.legal', $shared + ['title' => self::LEGAL_TITLES[$path]]);
        }

        return view('public.home', $shared);
    }

    /**
     * Plan kataloğunun HERKESE AÇIK görünümü.
     *
     * Yalnız ad, biçimlendirilmiş fiyat ve hak listesi geçer: iç kimlikler,
     * sürüm numaraları ve sıralama alanları ziyaretçinin işi değil.
     *
     * @return list<array{name: string, price: ?string, entitlements: list<string>}>
     */
    private function publicPlans(string $locale): array
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
