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
use App\Infrastructure\Content\Pages\MultipleBranchesPage;
use App\Infrastructure\Content\Pages\PricingPage;
use App\Infrastructure\Content\Pages\ProductOverviewPage;
use App\Infrastructure\Content\Pages\QrMenuPage;
use App\Infrastructure\Content\Pages\SolutionsPage;
use App\Infrastructure\Content\Pages\StockStatusPage;
use App\Infrastructure\Content\Pages\TablesAndQrPage;
use App\Infrastructure\Content\Pages\ZabunoAiPage;

/**
 * Yazılmış kurumsal sayfaların içeriği — FF-191 (dalga 1), FF-192 (dalga 2),
 * FF-203 (dalga 3).
 *
 * Dalga 2, kütüphanenin ürün sayfasına özel OLMADIĞINI ölçtü: çözümler girişi
 * ve fiyatlandırma `urun` türünde değil ve aynı blok modelinden çiziliyorlar.
 * Şablon dilden bağımsızdı; artık türden de bağımsız olduğu gösterildi.
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
 * **Dil kararı (`docs/118` E4).** Burada yalnız İNGİLİZCE içerik var; Türkçe
 * yuva bilerek BOŞ. Kurumsal sitenin ilk içerik dili sahibin açık kararını
 * bekleyen tek noktadır ve o karar geldiğinde değişecek tek katman burasıdır:
 * blok modeli, şablon, şema üreticisi, kırıntı ve kapı dilden bağımsızdır.
 * Bu pakette hiçbir çeviri üretilmedi ve hiçbir çeviri işi kuyruklanmadı.
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
        ] as $content) {
            $index[$content->locale.'|'.$content->pageKey] = $content;
        }

        return $this->cache = $index;
    }
}
