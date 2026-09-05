<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Domain\Content\PageEnvironment;
use App\Domain\Content\PageGate;
use App\Models\ContentPage;
use App\Support\Localization\SiteText;
use Throwable;

/**
 * Kurumsal sitenin gezinti KAYNAĞI — tek yer.
 *
 * `docs/118` §2 ve `docs/105` §2.2 (madde 3): *"Header/footer/mega menü yeni
 * sayfa yaratmaz; aynı canonical'e bağlanır"* ve *"yayınlanmamış sayfa hiçbir
 * yerden iç bağlantı almaz"*.
 *
 * Bu sınıf o iki cümlenin uygulanmış hâlidir. İki tür hedef tanır ve ikisini
 * de aynı soruyla süzer — "bu adres bugün gerçekten çalışıyor mu?":
 *
 * 1. YAŞAYAN ROTA (`/pricing`, `/help`): bugün sunucuda karşılığı olan, kendi
 *    denetleyicisi olan sayfalar. Bunlar kütükte değildir (kütük yalnız `/tr/`
 *    ve `/en/` altını tutar, `docs/105` §8) ve her zaman bağlanabilir.
 * 2. KÜTÜK YOLU (`/tr/urun/`): sayfa kütüğündeki canonical yollar. Bunlar
 *    yalnız `PageRenderDecision::isLinkable()` evet dediğinde gezintiye girer.
 *
 * Neden ikinci kural bu kadar sert: mega menü doğası gereği "ileride olacak"
 * sayfaları listelemeye davet eder. Kütükteki 386 yolun bugün hiçbiri yayında
 * değil; hepsini menüye yazmak, ziyaretçiye 386 tane 404 sunmak olurdu.
 * Bağlantının varlığı, arkasındaki sayfanın çalıştığı İDDİASIDIR.
 */
final class SiteNavigation
{
    /**
     * Gezinti kütüğü — bölge, grup ve maddeler.
     *
     * Buradaki her `path` ya bir rotadır ya da site haritasındaki bir
     * canonical yoldur; üçüncü bir tür YOKTUR. Yeni bir menü maddesi
     * eklemek, önce o sayfanın var olmasını gerektirir.
     *
     * `registry` alanı, maddenin hangi soruyla süzüleceğini söyler.
     *
     * @var array<string, list<array{
     *     id: string,
     *     labelKey: string,
     *     registry: bool,
     *     items: list<array{labelKey: string, path: string, anchor?: bool, emphasis?: bool}>
     * }>>
     */
    private const GROUPS = [
        'header' => [
            [
                /*
                    ANA GEZİNTİ. Çıpalar ana sayfadaki GERÇEK başlıklara gider
                    (`docs/38` §4); gerçek sayfası olan şey (Fiyat, Yardım,
                    İletişim) her yerde gerçek yoldur.
                */
                'id' => 'primary',
                'labelKey' => 'site.nav.primary',
                'registry' => false,
                'items' => [
                    ['labelKey' => 'site.nav.features', 'path' => '#features', 'anchor' => true],
                    ['labelKey' => 'site.nav.howItWorks', 'path' => '#how-it-works', 'anchor' => true],
                    ['labelKey' => 'site.nav.pricing', 'path' => '/pricing'],
                    ['labelKey' => 'site.nav.help', 'path' => '/help'],
                    ['labelKey' => 'site.nav.contact', 'path' => '/contact'],
                ],
            ],
            [
                /*
                    MEGA MENÜ — sahibin kendi site haritasındaki üst menü
                    (`docs/106` §3.1). Bugün bu grubun BİR maddesi bile
                    çizilmiyor, çünkü kütükteki karşılıkları henüz yayında
                    değil; grup da bu yüzden hiç çizilmiyor.

                    `/tr/fiyatlandirma/` bilerek YOK: aynı niyeti bugün
                    yayında olan `/pricing` karşılıyor ve iki bağlantı aynı
                    şeye götürseydi, ziyaretçi hangisinin doğru olduğunu
                    bilemezdi (`docs/106` §1: aynı arama niyeti tek sayfa).
                    O adresin göçü kendi paketinin işi (`docs/105` §4.1).
                */
                'id' => 'explore',
                'labelKey' => 'site.nav.explore',
                'registry' => true,
                'items' => [
                    ['labelKey' => 'site.nav.product', 'path' => '/tr/urun/'],
                    ['labelKey' => 'site.nav.solutions', 'path' => '/tr/cozumler/'],
                    ['labelKey' => 'site.nav.integrations', 'path' => '/tr/entegrasyonlar/'],
                    ['labelKey' => 'site.nav.customers', 'path' => '/tr/musteriler/'],
                    ['labelKey' => 'site.nav.resources', 'path' => '/tr/kaynaklar/'],
                ],
            ],
            [
                'id' => 'account',
                'labelKey' => 'site.nav.account',
                'registry' => false,
                'items' => [
                    ['labelKey' => 'site.nav.login', 'path' => '/login'],
                    ['labelKey' => 'site.nav.register', 'path' => '/register', 'emphasis' => true],
                ],
            ],
        ],
        'footer' => [
            [
                'id' => 'product',
                'labelKey' => 'site.footer.product',
                'registry' => false,
                'items' => [
                    ['labelKey' => 'site.nav.pricing', 'path' => '/pricing'],
                    ['labelKey' => 'site.nav.help', 'path' => '/help'],
                    ['labelKey' => 'site.nav.contact', 'path' => '/contact'],
                ],
            ],
            [
                'id' => 'legal',
                'labelKey' => 'site.footer.legal',
                'registry' => false,
                'items' => [
                    ['labelKey' => 'site.footer.terms', 'path' => '/terms'],
                    ['labelKey' => 'site.footer.privacy', 'path' => '/privacy'],
                    ['labelKey' => 'site.footer.kvkk', 'path' => '/kvkk'],
                ],
            ],
        ],
    ];

    public function __construct(private readonly SiteText $siteText) {}

    /**
     * Gezintinin işaret ettiği BÜTÜN yollar — çıpalar hariç.
     *
     * Çıpa bir sayfa değil, bir sayfanın içindeki başlıktır; onu "var mı"
     * diye kütükte aramak yanlış soruyu sormak olurdu.
     *
     * @return list<string>
     */
    public function declaredTargets(): array
    {
        $targets = [];

        foreach (self::GROUPS as $groups) {
            foreach ($groups as $group) {
                foreach ($group['items'] as $item) {
                    if ($item['anchor'] ?? false) {
                        continue;
                    }

                    $targets[] = $item['path'];
                }
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * Kabuğun çizeceği gezinti — yalnız GERÇEKTEN çalışan adresler.
     *
     * @param  string  $anchorPrefix  Ana sayfada `''`, diğer sayfalarda `'/'`.
     * @return array<string, list<array{
     *     id: string,
     *     label: string,
     *     items: list<array{label: string, href: string, emphasis: bool}>
     * }>>
     */
    public function forShell(string $anchorPrefix, ?string $locale = null): array
    {
        $linkable = $this->linkableRegistryPaths();
        $shell = [];

        foreach (self::GROUPS as $region => $groups) {
            $shell[$region] = [];

            foreach ($groups as $group) {
                $items = [];

                foreach ($group['items'] as $item) {
                    if (($group['registry'] ?? false) && ! in_array($item['path'], $linkable, true)) {
                        continue;
                    }

                    $items[] = [
                        'label' => $this->siteText->get($item['labelKey'], $locale),
                        'href' => ($item['anchor'] ?? false) ? $anchorPrefix.$item['path'] : $item['path'],
                        'emphasis' => $item['emphasis'] ?? false,
                    ];
                }

                // Boş bir grup, olmayan bir bölümün sözünü verir: başlığı
                // çizilir, altı boş kalır. Hiç çizilmemesi daha dürüst.
                if ($items === []) {
                    continue;
                }

                $shell[$region][] = [
                    'id' => $group['id'],
                    'label' => $this->siteText->get($group['labelKey'], $locale),
                    'items' => $items,
                ];
            }
        }

        return $shell;
    }

    /**
     * Kütükteki hangi gezinti hedefi bugün bağlantı verilebilir?
     *
     * TEK sorguda okunur: her menü maddesi için ayrı sorgu, her sayfa
     * yüklemesinde beş sorgu demekti ve mega menü büyüdükçe artacaktı.
     *
     * @return list<string>
     */
    private function linkableRegistryPaths(): array
    {
        $candidates = [];

        foreach (self::GROUPS as $groups) {
            foreach ($groups as $group) {
                if (! ($group['registry'] ?? false)) {
                    continue;
                }

                foreach ($group['items'] as $item) {
                    $candidates[] = $item['path'];
                }
            }
        }

        if ($candidates === []) {
            return [];
        }

        $environment = PageEnvironment::tryFrom((string) config('content.page_environment'))
            ?? PageEnvironment::Production;

        try {
            $pages = ContentPage::query()->whereIn('canonical_path', array_unique($candidates))->get();
        } catch (Throwable) {
            /*
                KÜTÜK OKUNAMAZSA SİTE ÖLMEZ.

                Kurumsal sayfalar bugüne kadar hiç veritabanına dokunmuyordu;
                gezintiyi kütüğe bağlamak onlara bir bağımlılık ekledi.
                Veritabanı bir an tökezlediğinde tanıtım sitesinin TAMAMININ
                500 vermesi, mega menüyü göstermemekten çok daha kötüdür:
                ziyaretçi ürünün çöktüğünü görür.

                Boş liste dürüst bir düşüştür — yaşayan sayfalar (fiyat,
                yardım, iletişim, yasal) kütükte değil, dolayısıyla üst çubuk
                ve altbilgi çalışmaya devam eder. Aynı karar fiyat kataloğu
                için de verilmişti (`FoundationStatusController::publicPlans`).
            */
            return [];
        }

        $linkable = [];

        /** @var ContentPage $page */
        foreach ($pages as $page) {
            // Şablon bir DESEN, dış bağlantı da bu sitede bir sayfa değildir
            // (`ShowCorporatePageController` ile aynı kural).
            if ($page->is_template || $page->is_external) {
                continue;
            }

            $decision = PageGate::decide(
                $page->status(),
                $environment,
                // Önizleme yetkisi gezintiyi DEĞİŞTİRMEZ: bir menü herkese
                // aynı siteyi göstermeli.
                false,
                $page->was_ever_published,
            );

            if ($decision->isLinkable()) {
                $linkable[] = $page->canonical_path;
            }
        }

        return $linkable;
    }
}
