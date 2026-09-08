<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Localization\SiteText;

/**
 * Ana sayfanın ÜÇ LİSTESİ — zincir, parçalar ve sınırlar (`docs/138`).
 *
 * ═══ NEDEN AYRI BİR SINIF ═══
 *
 * Üç liste toplam 25 madde ve 50 dize taşıyor. Hepsini `SiteText::all()`
 * içine dökmek haritayı iki katına çıkarır ve şablonda 50 satırlık bir
 * `@foreach` dizisi olarak yeniden yazılmasını gerektirirdi — yani sıra
 * ve içerik İKİ yerde durur, ilk ayrıştıklarında hangisinin doğru olduğu
 * belirsiz kalırdı.
 *
 * ═══ İDDİA UYDURULMAZ: ENVANTERE BAĞLIDIR ═══
 *
 * Ana sayfa ürün hakkında iddia yazar. O iddiaların KAYNAĞI burada değil,
 * `app/Infrastructure/Content/Pages/ProductOverviewPage.php` içinde: ürünün
 * kendi genel bakış sayfası, her yeteneği ONU ÜRETEN DOSYAYLA birlikte
 * sayar ve neyin OLMADIĞINI da aynı titizlikle yazar.
 *
 * Bu sınıf yalnız SIRAYI ve katalog anahtarlarını bildirir. Başlıkların o
 * envanterin terimleriyle birebir aynı kalmasını `HomeSceneContractTest`
 * (HOME-REAL-07) ölçer: bir yetenek üründen düşerse ya da adı değişirse,
 * ana sayfa sessizce eski iddiayı taşımaya devam edemez.
 *
 * Bu, "sahte özellik listesi" sorununun kod düzeyindeki cevabıdır. Bir
 * pazarlama sayfasında bir satır silmeyi kimse hatırlamaz; bir kırmızı test
 * hatırlatır.
 */
final class HomeStory
{
    /**
     * Zincir — hesaptan masadaki koda. Sıra ÜRÜNÜN sırasıdır.
     *
     * @var list<string>
     */
    public const CHAIN = [
        'site.home.chain.step1',
        'site.home.chain.step2',
        'site.home.chain.step3',
        'site.home.chain.step4',
        'site.home.chain.step5',
        'site.home.chain.step6',
    ];

    /**
     * Parçalar — ürünün on iki yeteneği.
     *
     * @var list<string>
     */
    public const PARTS = [
        'site.home.parts.qrMenu',
        'site.home.parts.menuManagement',
        'site.home.parts.tables',
        'site.home.parts.branding',
        'site.home.parts.media',
        'site.home.parts.languages',
        'site.home.parts.branches',
        'site.home.parts.analytics',
        'site.home.parts.ai',
        'site.home.parts.ordering',
        'site.home.parts.team',
        'site.home.parts.ratings',
    ];

    /**
     * Sınırlar — ürünün NE OLMADIĞI.
     *
     * @var list<string>
     */
    public const LIMITS = [
        'site.home.limits.till',
        'site.home.limits.reservations',
        'site.home.limits.integrations',
        'site.home.limits.store',
        'site.home.limits.dishNames',
        'site.home.limits.oneBrand',
        'site.home.limits.sectors',
    ];

    public function __construct(private readonly SiteText $text) {}

    /**
     * Üç liste, şablonun beklediği biçimde.
     *
     * @return array{chain: list<array{title: string, body: string}>, parts: list<array{title: string, body: string}>, limits: list<array{title: string, body: string}>}
     */
    public function lists(?string $locale = null): array
    {
        return [
            'chain' => $this->resolve(self::CHAIN, $locale),
            'parts' => $this->resolve(self::PARTS, $locale),
            'limits' => $this->resolve(self::LIMITS, $locale),
        ];
    }

    /**
     * @param  list<string>  $stems
     * @return list<array{title: string, body: string}>
     */
    private function resolve(array $stems, ?string $locale): array
    {
        return array_map(
            fn (string $stem): array => [
                'title' => $this->text->get($stem.'.title', $locale),
                'body' => $this->text->get($stem.'.body', $locale),
            ],
            $stems,
        );
    }
}
