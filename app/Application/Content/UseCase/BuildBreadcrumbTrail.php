<?php

declare(strict_types=1);

namespace App\Application\Content\UseCase;

use App\Application\Content\Port\ContentLibraryPort;
use App\Domain\Content\Breadcrumb;
use App\Domain\Content\PageEnvironment;
use App\Domain\Content\PageGate;
use App\Domain\Url\UrlNormalizer;
use App\Models\ContentPage;

/**
 * Ekmek kırıntısını KÜTÜKTEN üretir — yönerge §12 ve §13.2.
 *
 * Hiyerarşi içerikte DEĞİL kütükte yaşar (`content_pages.parent_key`); içerik
 * yalnız basamağın kısa adını verir. Tersini yapsaydık aynı ağaç iki yerde
 * yazılı olurdu ve bir gün ayrışırdı — sayfa `/tr/urun/qr-menu/` adresinde
 * dururken kırıntısı başka bir yol anlatırdı.
 *
 * İki kural burada uygulanır:
 *
 * 1. **Yayınlanmamış ata bağlantı ALMAZ** (`docs/105` §2.2(3)). Adıyla durur,
 *    tıklanamaz. Hiçbir yere götürmeyen bağlantı, deponun kendi bozuk-bağlantı
 *    kapısını kırar.
 * 2. **Yalnız AYNI DİLDEKİ atalar kırıntıya girer.** Kütük bugün tek dilli
 *    (site haritası Türkçe ağaçtan üretildi); başka dildeki bir atayı
 *    İngilizce bir sayfanın kırıntısına koymak, ziyaretçiye anlamadığı bir
 *    basamak göstermek olurdu.
 */
final class BuildBreadcrumbTrail
{
    /** Sonsuz döngüye karşı: bozuk bir `parent_key` zinciri kilitlenmemeli. */
    private const int MAX_DEPTH = 8;

    public function __construct(
        private readonly ContentLibraryPort $library,
        private readonly UrlNormalizer $normalizer,
    ) {}

    /** @return list<Breadcrumb> kökten yaprağa */
    public function handle(ContentPage $page, PageEnvironment $environment): array
    {
        $chain = [$page];
        $current = $page;

        while (count($chain) < self::MAX_DEPTH && $current->parent_key !== null) {
            $parent = ContentPage::query()
                ->where('page_key', $current->parent_key)
                ->where('locale', $page->locale)
                ->first();

            if ($parent === null) {
                break;
            }

            array_unshift($chain, $parent);
            $current = $parent;
        }

        $trail = [];

        foreach ($chain as $step) {
            $trail[] = new Breadcrumb(
                label: $this->labelFor($step),
                /*
                    Adres URL MOTORUNDAN geçirilir, kütükten olduğu gibi
                    alınmaz. Kütük yolları site haritasının yazımıyla, sondaki
                    bölü çizgisiyle tutuyor; sunucu ise `config/url-policy.php`
                    gereği o çizgiyi 301 ile atıyor. Ham yolu bağlantı yapmak,
                    her iç bağlantıyı bir yönlendirmeye çarptırırdı — ziyaretçi
                    için fazladan bir gidiş-dönüş, arama motoru için "kanonik
                    adres bile kendine götürmüyor" demek.
                */
                path: $this->isLinkable($step, $environment)
                    ? $this->normalizer->normalize($step->canonical_path)->target()
                    : null,
            );
        }

        return $trail;
    }

    private function labelFor(ContentPage $page): string
    {
        $content = $this->library->find($page->page_key, $page->locale);

        if ($content !== null) {
            return $content->metadata->breadcrumbTitle;
        }

        /*
            İçeriği olmayan ata için kütükteki başlığa düşülür. Site haritasının
            başlıkları yer yer bir ad değil bir TARİFTİR ("QR, dijital, mobil ve
            temassız menü özelliklerini tek sayfada anlatır"); o sayfanın içeriği
            yazıldığında kendi kısa adını getirir ve bu düşüş kendiliğinden
            devreden çıkar.
        */
        return $page->title;
    }

    private function isLinkable(ContentPage $page, PageEnvironment $environment): bool
    {
        if ($page->is_template || $page->is_external) {
            return false;
        }

        return PageGate::decide($page->status(), $environment, false, $page->was_ever_published)
            ->isLinkable();
    }
}
