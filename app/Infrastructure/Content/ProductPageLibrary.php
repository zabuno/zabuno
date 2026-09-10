<?php

declare(strict_types=1);

namespace App\Infrastructure\Content;

use App\Application\Content\Port\ContentLibraryPort;
use App\Domain\Content\PageContent;
use App\Infrastructure\Content\Pages\AnalyticsPage;
use App\Infrastructure\Content\Pages\DesignAndBrandingPage;
use App\Infrastructure\Content\Pages\ImagesAndMediaPage;
use App\Infrastructure\Content\Pages\LanguagesAndCurrencyPage;
use App\Infrastructure\Content\Pages\MenuCategoriesPage;
use App\Infrastructure\Content\Pages\MenuDishesPage;
use App\Infrastructure\Content\Pages\MenuManagementPage;
use App\Infrastructure\Content\Pages\MenuPricesPage;
use App\Infrastructure\Content\Pages\MenuVersionsPage;
use App\Infrastructure\Content\Pages\MultipleBranchesPage;
use App\Infrastructure\Content\Pages\OrderingPage;
use App\Infrastructure\Content\Pages\PricingPage;
use App\Infrastructure\Content\Pages\ProductOverviewPage;
use App\Infrastructure\Content\Pages\QrMenuPage;
use App\Infrastructure\Content\Pages\SolutionsPage;
use App\Infrastructure\Content\Pages\StockStatusPage;
use App\Infrastructure\Content\Pages\TablesAndQrPage;
use App\Infrastructure\Content\Pages\Tr\AnalyticsPage as TrAnalyticsPage;
use App\Infrastructure\Content\Pages\Tr\DesignAndBrandingPage as TrDesignAndBrandingPage;
use App\Infrastructure\Content\Pages\Tr\ImagesAndMediaPage as TrImagesAndMediaPage;
use App\Infrastructure\Content\Pages\Tr\LanguagesAndCurrencyPage as TrLanguagesAndCurrencyPage;
use App\Infrastructure\Content\Pages\Tr\MenuCategoriesPage as TrMenuCategoriesPage;
use App\Infrastructure\Content\Pages\Tr\MenuDishesPage as TrMenuDishesPage;
use App\Infrastructure\Content\Pages\Tr\MenuManagementPage as TrMenuManagementPage;
use App\Infrastructure\Content\Pages\Tr\MenuPricesPage as TrMenuPricesPage;
use App\Infrastructure\Content\Pages\Tr\MenuVersionsPage as TrMenuVersionsPage;
use App\Infrastructure\Content\Pages\Tr\MultipleBranchesPage as TrMultipleBranchesPage;
use App\Infrastructure\Content\Pages\Tr\OrderingPage as TrOrderingPage;
use App\Infrastructure\Content\Pages\Tr\PricingPage as TrPricingPage;
use App\Infrastructure\Content\Pages\Tr\ProductOverviewPage as TrProductOverviewPage;
use App\Infrastructure\Content\Pages\Tr\QrMenuPage as TrQrMenuPage;
use App\Infrastructure\Content\Pages\Tr\SolutionsPage as TrSolutionsPage;
use App\Infrastructure\Content\Pages\Tr\StockStatusPage as TrStockStatusPage;
use App\Infrastructure\Content\Pages\Tr\TablesAndQrPage as TrTablesAndQrPage;
use App\Infrastructure\Content\Pages\Tr\ZabunoAiPage as TrZabunoAiPage;
use App\Infrastructure\Content\Pages\ZabunoAiPage;

/**
 * Yazılmış kurumsal sayfaların içeriği — FF-191 (dalga 1), FF-192 (dalga 2),
 * FF-203 (dalga 3).
 *
 * Dalga 2, kütüphanenin ürün sayfasına özel OLMADIĞINI ölçtü: çözümler girişi
 * ve fiyatlandırma `urun` türünde değil ve aynı blok modelinden çiziliyorlar.
 * Şablon dilden bağımsızdı; artık türden de bağımsız olduğu gösterildi.
 *
 * FF-229 (dalga 4) yazılmamış olmanın en pahalı hâlini kapattı: parası
 * ALINAN ama hiçbir yerde ANLATILMAYAN yetenekler (`docs/137` §5). Masadan
 * sipariş `ordering.basic` hakkıyla satılıyordu ve kütükte tek satırı yoktu;
 * yayın/geri alma yalnız `/help` içinde bir paragraftı. İkisinin de sayfası
 * artık burada.
 *
 * Dalga 3, kütüphanenin DERİNLİKTEN de bağımsız olduğunu ölçtü: ürün genel
 * bakışı (ata) ve menü yönetiminin dört alt sayfası (üç kademeli anahtar)
 * aynı modelden çiziliyor. Alt sayfa, ebeveyninin bir kopyası değildir;
 * `ProductPageLibraryTest` aynı sorunun iki sayfada sorulmasını kırar.
 *
 * İçerik BUGÜN kodda yaşıyor ve bu bilinçli bir başlangıç: kütük ve kapı
 * çalışıyor, editoryal tablo henüz yok, ve içeriği bir tabloya koymak onu
 * testin ve kod incelemesinin dışına çıkarırdı. Kanıt zinciri (`BlockEntry`
 * `source` alanı) ancak testin okuyabildiği bir yerde anlam taşır.
 *
 * **Dil kararı — SAHİBİN AÇIK KARARIYLA GÜNCELLENDİ (2026-09-10).** Önceki
 * hâlinde burada yalnız İngilizce içerik vardı ve Türkçe yuva bilerek boştu;
 * gerekçe, kurumsal sitenin ilk içerik dilinin sahibin açık kararını
 * beklemesiydi (`docs/118` E4). O karar GELDİ ve açıktır: **ana dil
 * İngilizce eksiksiz bitecek, Türkçe ikinci dil olacak ve çevirilerin eksiği
 * kalmayacak.** Değişen tek katman, tam da söz verildiği gibi, burasıdır:
 * blok modeli, şablon, şema üreticisi, kırıntı ve kapı hiç kıpırdamadı.
 *
 * Türkçe içerik bir ÖZET DEĞİLDİR. Yayında olan on sekiz İngilizce sayfanın
 * her birinin Türkçe karşılığı aynı blokları, aynı satır sayısını, aynı
 * kanıt yollarını ve aynı doğruluk sınırlarını taşır. Eksik bırakılan bir
 * sınırlama satırı, Türkçe okuyan kişiye ürünün YAPMADIĞI bir şeyi yapıyor
 * gibi gösterirdi; yani çeviri eksikliği doğrudan bir dürüstlük eksikliğine
 * dönüşürdü. `ProductPageLibraryTest` bu eşitliği blok blok ölçer.
 *
 * İngilizce asıllar bu pakette DEĞİŞMEDİ: ikinci dili açmak, birinci dilin
 * metnine dokunmayı gerektirmez.
 */
final class ProductPageLibrary implements ContentLibraryPort
{
    /** @var array<string, PageContent>|null */
    private ?array $cache = null;

    public function find(string $pageKey, string $locale): ?PageContent
    {
        return $this->indexed()[$locale.'|'.$pageKey] ?? null;
    }

    /** @return list<PageContent> */
    public function all(): array
    {
        return array_values($this->indexed());
    }

    /** @return array<string, PageContent> */
    private function indexed(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $index = [];

        foreach ([
            QrMenuPage::content(),
            MenuManagementPage::content(),
            TablesAndQrPage::content(),
            AnalyticsPage::content(),
            ZabunoAiPage::content(),
            ImagesAndMediaPage::content(),
            LanguagesAndCurrencyPage::content(),
            MultipleBranchesPage::content(),
            SolutionsPage::content(),
            PricingPage::content(),
            ProductOverviewPage::content(),
            DesignAndBrandingPage::content(),
            MenuCategoriesPage::content(),
            MenuDishesPage::content(),
            MenuPricesPage::content(),
            StockStatusPage::content(),
            OrderingPage::content(),
            MenuVersionsPage::content(),

            /*
                TÜRKÇE — aynı on sekiz anahtar, aynı sırada.

                Sıra bilerek İngilizceyle birebir aynı: iki listeyi yan yana
                okuyan biri eksik bir satırı tek bakışta görür. Ayrı bir dizi
                yerine aynı döngüde durmalarının sebebi de bu — `$index`
                anahtarı zaten `locale|pageKey`, dolayısıyla iki dil
                birbirinin üstüne yazamaz.
            */
            TrQrMenuPage::content(),
            TrMenuManagementPage::content(),
            TrTablesAndQrPage::content(),
            TrAnalyticsPage::content(),
            TrZabunoAiPage::content(),
            TrImagesAndMediaPage::content(),
            TrLanguagesAndCurrencyPage::content(),
            TrMultipleBranchesPage::content(),
            TrSolutionsPage::content(),
            TrPricingPage::content(),
            TrProductOverviewPage::content(),
            TrDesignAndBrandingPage::content(),
            TrMenuCategoriesPage::content(),
            TrMenuDishesPage::content(),
            TrMenuPricesPage::content(),
            TrStockStatusPage::content(),
            TrOrderingPage::content(),
            TrMenuVersionsPage::content(),
        ] as $content) {
            $index[$content->locale.'|'.$content->pageKey] = $content;
        }

        return $this->cache = $index;
    }
}
