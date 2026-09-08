<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Billing\UseCase\ListPlanCatalog;
use App\Support\Localization\SiteText;
use App\Support\Money\PriceLabel;
use App\Support\Site\HomeStory;
use App\Support\Site\SiteShell;
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
    /** Adres → ölçüm kimliği. Rapor bu adları okur, adresleri değil. */
    // Yasal sayfalar artık burada DEĞİL: sekiz belge kendi denetleyicisinde
    // (`ShowLegalDocumentController`, FF-198). Bu sınıfta yalnız ana sayfa
    // ve fiyat kaldı.
    private const PAGE_KEYS = [
        '' => 'home',
        'pricing' => 'pricing',
    ];

    public function __construct(
        private readonly ListPlanCatalog $plans,
        private readonly SiteText $siteText,
        private readonly SiteShell $shell,
        private readonly HomeStory $story,
    ) {}

    public function __invoke(Request $request): View
    {
        $path = trim($request->getPathInfo(), '/');

        /*
            KABUK VERİSİ TEK YERDEN (`SiteShell`).

            Metin kataloğu, kanonik adres, çıpa öneki, ölçüm kimliği ve
            gezinti aynı yerde üretilir. Ölçüm kimliği sabit bir sözlükten
            gelir, adresten türetilmez: adres yarın değişirse geçmiş raporlar
            ikiye bölünmemeli.
        */
        $shell = $this->shell->context($request, self::PAGE_KEYS[$path] ?? 'unknown');

        /*
            DİL KABUKTAN GELİR, burada İKİNCİ KEZ pazarlık edilmez (FF-249).

            Aşağıdaki iki satır `SiteText::pick($request->getPreferredLanguage(
            ['en', 'tr']))` çağırıyordu — elle yazılmış, `i18n.shipped_locales`e
            hiç bağlı olmayan bir liste. Sonucu ölçüldü (2026-09-08): sunulan
            tek dil `en` iken Türkçe bir tarayıcı Türkçe plan etiketleri ve
            Türkçe bir başlık alıyor, aynı ekrandaki "Create an account"
            İngilizce kalıyordu. Aynı ekranda iki dil, `<html lang="en">`
            altında.

            Kabuğun seçtiği dil zaten SUNULAN bir dildir; ikinci bir seçim
            yapmak, o kararı sessizce ezmek olurdu.
        */
        $locale = $shell['lang']->ui;

        $shared = $shell + [
            /*
                FİYAT KAYDOLMADAN GÖRÜLÜR — `docs/88` (P1-01).

                Plan listesi bugüne kadar `auth` + çalışma alanı bağlamı
                ardındaydı: fiyatı görmek için kaydolmak gerekiyordu, yani
                ürün kaydolmayı fiyatı görmeye bağlı kılıyordu.
            */
            'plans' => $this->publicPlans($locale),
        ];

        if ($path === 'pricing') {
            return view('public.pricing', $shared);
        }

        /*
            ANA SAYFANIN ÜÇ LİSTESİ (`docs/138`).

            Fiyat sayfasına GEÇMEZ: orada zincir de parça listesi de
            görünmüyor ve okunmayacak 25 maddeyi her istekte çözmek,
            hiçbir şeyin görünmediği bir iş demekti.
        */
        return view('public.home', $shared + [
            // Aynı gerekçe (FF-249): tek dil kaynağı kabuğun kendisi.
            'story' => $this->story->lists($locale),
        ]);
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
