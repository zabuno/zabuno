<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Application\Content\UseCase\ResolvePageDelivery;
use App\Domain\Content\PageEnvironment;
use App\Domain\Url\UrlNormalizer;
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
 *    O kararı üreten tek yol `ResolvePageDelivery`dir (`docs/129` §3): kütükteki
 *    durum "yayında" dese bile, o dilde yazılmış bir metin yoksa adres 404
 *    döner ve gezintiye GİRMEZ.
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
                    /*
                        SATICI KİM? (FF-216) Ödeme kuruluşunun üye iş yeri
                        incelemesi "hakkımızda" ve "iletişim" başlıklarını
                        sitede ADIYLA arar; ikisi de yaşayan rotadır ve
                        şirket kimliğini TEK kaynaktan (`CompanyProfile`)
                        okur.
                    */
                    ['labelKey' => 'site.nav.about', 'path' => '/about'],
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
                    /*
                        UZAKTAN SATIŞIN BELGELERİ (FF-198, `docs/107` Faz 1.2).
                        Hepsi yaşayan rotadır (`ShowLegalDocumentController`)
                        ve her zaman bağlanabilir. Ticari ileti izni metni
                        (`/marketing-consent`) bilerek altbilgide DEĞİL: onu
                        okuyacak kişi kayıt ekranındadır ve oradan bağlanır.
                    */
                    ['labelKey' => 'site.footer.distanceSales', 'path' => '/distance-sales'],
                    ['labelKey' => 'site.footer.preInformation', 'path' => '/pre-information'],
                    /*
                        TESLİMAT/İFA AYRI BİR BAŞLIK (FF-216). Dijital bir
                        hizmette "teslimat" hesabın ne zaman aktifleştiğidir;
                        bunu mesafeli satışın yedinci bölümüne gömmek, o
                        başlığı adıyla arayan incelemede görünmemek olurdu.
                    */
                    ['labelKey' => 'site.footer.delivery', 'path' => '/delivery'],
                    ['labelKey' => 'site.footer.refundPolicy', 'path' => '/refund-policy'],
                    ['labelKey' => 'site.footer.cookies', 'path' => '/cookies'],
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly SiteText $siteText,
        /*
            KAPIYA GİDEN TEK YOL (`docs/129` §3).

            Önceden burada `PageGate::decide()` DOĞRUDAN çağrılıyordu ve bu,
            aynı sorunun dördüncü kez ayrı yazılmış hâliydi. Kapı tek başına
            "bu satırın durumu gösterilebilir mi" sorusunu yanıtlar; ziyaretçinin
            gördüğü 200/404 ise bir soru daha sorar: *o dilde gerçekten yazılmış
            bir metin var mı?*

            Ölçülen sonuç (2026-09-08): kütükte `published` işaretli ama içeriği
            yazılmamış bir sayfa gezintide GÖRÜNÜYOR ve tıklandığında 404
            dönüyordu — çünkü `ShowCorporatePageController` son emniyet kemerini
            ayrıca uyguluyor, gezinti ise uygulamıyordu. Altbilginin zenginleşmesi
            tam olarak bu kusuru yüzlerce bağlantıya çoğaltacaktı.
        */
        private readonly ResolvePageDelivery $delivery,
        /*
            BAĞLANTI, SUNUCUNUN YÖNLENDİRMEDEN SUNDUĞU ADRESE GİDER.

            Kütükteki kanonik yol sondaki eğik çizgiyi taşır (`/en/product/`)
            ama adres politikası `never_except_root` (`config/url.php`): o
            adres 301 döner. İç bir bağlantıyı yönlendirmeye sokmak, her
            tıklamaya bir tur eklemek ve arama motoruna iki adres göstermektir.
            Kırıntı ve "ilgili sayfalar" listesi zaten bu normalleştiriciyi
            kullanıyor; altbilgi de aynı kaynağı kullanır.
        */
        private readonly UrlNormalizer $normalizer,
    ) {}

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
     * Üç bölge döner: `header`, `footer` ve `content`. İlk ikisi elle
     * bildirilmiş gruplardır; `content` ise altbilginin pSEO katıdır ve
     * kütükten türer (bkz. `contentMenus()`).
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

        /*
            İÇERİK MENÜLERİ — altbilginin KÜTÜKTEN türeyen katı.

            Sahibin isteği (2026-09-08): *"çoook zengin, çok katmanlı, çok row,
            çok menu grubu, pSEO için footer üzerinde content menus."*

            Elle yazılmış zengin bir ızgara bugün YÜZLERCE 404'e giden bağlantı
            demekti: içeriği yazılmış on altı sayfanın hiçbiri yayında değil.
            Bu yüzden bu kat elle YAZILMIYOR, kütükten türüyor — ve o gün
            geldiğinde tek bir Blade satırı değişmeden zenginleşiyor.
        */
        $shell['content'] = $this->contentMenus($locale);

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

        $environment = $this->environment();

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
            if ($this->isLinkable($page, $environment)) {
                $linkable[] = $page->canonical_path;
            }
        }

        return $linkable;
    }

    /**
     * ALTBİLGİNİN İÇERİK MENÜLERİ — kütükten türer, elle yazılmaz.
     *
     * ── Kapı tek cümledir ────────────────────────────────────────────────
     *
     * *Buradaki her bağlantı 200 döner.* Bunu sağlayan şey bir dikkat değil,
     * bir kaynak: liste ziyaretçinin alacağı HTTP kodunu üreten aynı
     * `ResolvePageDelivery` kararından süzülüyor (`docs/129` §3). Ayrı bir
     * süzgeç yazsaydık, ikisi bir gün ayrışır ve altbilgi 404'lere bağlanırdı
     * — üstelik altbilgi, kimsenin bakmadığı yerdir.
     *
     * ── Grup iskeleti tasarımda, boş grup ekranda YOK ─────────────────────
     *
     * Gruplar sayfaların KENDİ hiyerarşisinden çıkar (`parent_key`): bağlanabilir
     * bir ata, altında bağlanabilir çocuklarıyla bir grup olur. Atası
     * bağlanamayan sayfalar tek bir "Explore" grubunda toplanır. Bir grubun
     * çizilebilmesi için içinde EN AZ BİR bağlantı olması gerekir; başlığı
     * çizilip altı boş kalan bir grup, olmayan bir bölümün sözünü vermektir.
     *
     * ── Etiket nereden geliyor ────────────────────────────────────────────
     *
     * Sayfanın KENDİ YAZILMIŞ kısa adından (`breadcrumbTitle`) — kütüğün
     * `title` alanından DEĞİL. Bir katalog anahtarı yazmak, her yeni sayfa
     * için bir kod değişikliği ve bir çeviri borcu üretirdi; oysa bu katın
     * bütün varlık sebebi, sahibin bir sayfayı yayına almasının altbilgiyi
     * kendiliğinden zenginleştirmesi.
     *
     * Kaynak neden DEĞİŞTİ (ölçüldü, 2026-09-08): kütükteki `title`, site
     * haritası BELGESİNDEN gelir ve o belge Türkçedir — kaynak dil satırları
     * için bile (`ImportSiteMapCommand`, `docs/118` E4). Üstelik bir kısmı
     * başlık bile değil, bir AÇIKLAMA cümlesidir: `/en/product/qr-menu/`
     * satırının başlığı "QR, dijital, mobil ve temassız menü özelliklerini
     * tek sayfada anlatır". On sekiz sayfa yayına alındığı gün İngilizce bir
     * sitenin altbilgisi Türkçe cümlelerle dolacaktı.
     *
     * Uydurma da yok, çeviri de: kırıntı başlığı sayfanın kendi içeriğinde
     * ZATEN yazılı ve ziyaretçi onu sayfanın içinde de görüyor. Metni
     * olmayan bir satır zaten bu listeye giremiyor, dolayısıyla kısa ad her
     * zaman var; yine de kütüğün başlığı son çare olarak duruyor.
     *
     * @return list<array{id: string, label: string, items: list<array{label: string, href: string, emphasis: bool}>}>
     */
    private function contentMenus(?string $locale): array
    {
        $locale = SiteText::pick($locale ?? app()->getLocale());
        $environment = $this->environment();

        try {
            /*
                YALNIZ YAYIN İDDİASI OLAN SATIRLAR OKUNUR.

                Kütükte 400'e yakın satır var ve hepsini her sayfa yüklemesinde
                okumak, altbilgiyi sitenin en pahalı parçası yapardı. Kapıdan
                geçme ihtimali olan tek küme, insan eliyle yayına alınmış
                satırlardır; gerisi zaten `not-found` döner.
            */
            $pages = ContentPage::query()
                ->where('locale', $locale)
                ->where('was_ever_published', true)
                ->orderBy('canonical_path')
                ->get();
        } catch (Throwable) {
            // Kütük okunamazsa site ÖLMEZ — yaşayan gruplar çizilmeye devam
            // eder (aynı karar, `linkableRegistryPaths()`).
            return [];
        }

        /** @var array<string, ContentPage> $linkable */
        $linkable = [];
        /** @var array<string, string> $label Sayfanın kendi yazılmış kısa adı. */
        $label = [];

        /** @var ContentPage $page */
        foreach ($pages as $page) {
            /*
                Karar TEK KEZ sorulur. `isLinkable()` zaten `ResolvePageDelivery`
                çağırıyordu ve etiketi ondan ayrı bir yerden okumak, aynı sayfa
                için iki ayrı kaynak demekti — ikisi bir gün ayrışır.
            */
            $delivery = $this->delivery->for($page, $environment);

            if (! $delivery->decision->isLinkable()) {
                continue;
            }

            $linkable[$page->page_key] = $page;
            $label[$page->page_key] = $delivery->content?->metadata->breadcrumbTitle ?? (string) $page->title;
        }

        // Elle yazılmış gruplarda ZATEN duran adres burada tekrar edilmez:
        // aynı bağlantıyı iki kez vermek, ziyaretçiye iki farklı yer olduğunu
        // düşündürür.
        $declared = $this->declaredTargets();

        /** @var array<string, list<ContentPage>> $grouped */
        $grouped = [];

        foreach ($linkable as $page) {
            if (in_array($page->canonical_path, $declared, true)) {
                continue;
            }

            $parentKey = (string) $page->parent_key;
            $grouped[isset($linkable[$parentKey]) ? $parentKey : ''][] = $page;
        }

        $groups = [];

        foreach ($grouped as $parentKey => $children) {
            $heading = $parentKey === ''
                ? $this->siteText->get('site.nav.explore', $locale)
                : $label[$parentKey];

            $groups[] = [
                'id' => 'content-'.($parentKey === '' ? 'explore' : str_replace('.', '-', $parentKey)),
                'label' => $heading,
                'items' => array_map(
                    fn (ContentPage $page): array => [
                        'label' => $label[$page->page_key],
                        'href' => $this->normalizer->normalize($page->canonical_path)->target(),
                        'emphasis' => false,
                    ],
                    $children,
                ),
            ];
        }

        return $groups;
    }

    /**
     * Bu satır bugün BAĞLANABİLİR mi — ziyaretçinin alacağı kodla aynı karar.
     */
    private function isLinkable(ContentPage $page, PageEnvironment $environment): bool
    {
        /*
            Önizleme yetkisi gezintiyi DEĞİŞTİRMEZ: bir menü herkese aynı
            siteyi göstermeli. `ResolvePageDelivery` bunu zaten böyle
            varsayıyor (önizleme `false` sabitlenmiş), yani burada ikinci bir
            karar verilmiyor.
        */
        return $this->delivery->for($page, $environment)->decision->isLinkable();
    }

    private function environment(): PageEnvironment
    {
        return PageEnvironment::tryFrom((string) config('content.page_environment'))
            ?? PageEnvironment::Production;
    }
}
