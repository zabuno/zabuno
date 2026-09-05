<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Breadcrumb;
use App\Domain\Content\PageContent;

/**
 * Kurumsal sayfaların MERKEZÎ şema üreticisi — yönerge §14.
 *
 * Şablon başına elle yazılmış JSON-LD, beşinci sayfada birinin oraya bir
 * `aggregateRating` eklemesiyle biterdi; sahte bir puan, §13.8'in yasakladığı
 * yanıltıcı structured data'nın ta kendisidir. Karar bu yüzden TEK yerde:
 * sayfa türüne göre yalnız geçerli şema üretilir ve üretilmeyenler de bir
 * karardır.
 *
 * **Değişmez kural: görünmeyen bilgi işaretlenmez.** Şemadaki her yetenek
 * adı sayfada okunabilen bir yetenek adıdır, her soru sayfada duran bir
 * sorudur. Aksi hâlde iki kaynak zamanla ayrışır ve işaretleme, ziyaretçinin
 * gördüğünden başka bir şey ilan eder (`MenuStructuredData` ile aynı disiplin).
 *
 * Burada ÜRETİLMEYENLER ve sebepleri:
 *
 * - `Organization` ve `WebSite` — site GENELİ işaretlemedir; sayfa başına
 *   üretmek aynı gerçeği yüzlerce kez ilan etmek olurdu. Kabuğun işidir.
 * - `Offers` / fiyat — bu sayfalarda görünen bir fiyat yok. Fiyat
 *   `/pricing` sayfasında yaşar ve orada işaretlenir.
 * - `aggregateRating` / `review` — bugün tek bir gerçek değerlendirme yok.
 */
final class CorporatePageStructuredData
{
    /**
     * `SoftwareApplication` üretilecek sayfa TÜRLERİ.
     *
     * İzin listesi, reddetme listesi değil: bilinmeyen bir tür yarın
     * eklendiğinde sessizce yanlış bir şema almasın diye. Yönergenin §14
     * tablosundaki diğer türler (rehber, blog, müşteri hikayesi) kendi
     * paketlerinde açılır — ve o gün ne olduğunu bilerek açılır.
     */
    private const array APPLICATION_PAGE_TYPES = ['urun'];

    /**
     * @param  list<Breadcrumb>  $trail
     * @return array<string, mixed>
     */
    public static function forPage(
        string $contentType,
        PageContent $content,
        string $canonicalUrl,
        string $siteUrl,
        array $trail,
    ): array {
        $graph = [];

        if (count($trail) >= 2) {
            $graph[] = self::breadcrumbList($trail, $siteUrl);
        }

        if (in_array($contentType, self::APPLICATION_PAGE_TYPES, true)) {
            $graph[] = self::application($content, $siteUrl);
        }

        $faq = self::faqPage($content, $canonicalUrl);

        if ($faq !== null) {
            $graph[] = $faq;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * @param  list<Breadcrumb>  $trail
     * @return array<string, mixed>
     */
    private static function breadcrumbList(array $trail, string $siteUrl): array
    {
        $items = [];

        foreach ($trail as $index => $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb->label,
            ];

            /*
                Yayınlanmamış bir basamak ADRES TAŞIMAZ. Kırıntıda adıyla
                durur — hiyerarşi eksik gösterilmez — ama arama motoruna
                "şuraya git" denmez, çünkü orası bugün 404'tür.
            */
            if ($crumb->isLinkable()) {
                $item['item'] = rtrim($siteUrl, '/').$crumb->path;
            }

            $items[] = $item;
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private static function application(PageContent $content, string $siteUrl): array
    {
        $features = [];

        $capabilities = $content->block(BlockType::Capabilities);

        if ($capabilities !== null) {
            foreach ($capabilities->entries as $entry) {
                if ($entry->term !== null) {
                    $features[] = $entry->term;
                }
            }
        }

        $answer = $content->block(BlockType::DirectAnswer);

        return [
            '@type' => 'SoftwareApplication',
            'name' => 'Zabuno',
            'url' => rtrim($siteUrl, '/').'/',
            // Bir restoran yazılımı bir oyun ya da bir yardımcı program
            // değildir; kategori uydurulmaz, schema.org'un kendi değeri
            // kullanılır.
            'applicationCategory' => 'BusinessApplication',
            // Kurulacak bir şey yok: ürün tarayıcıda çalışıyor ve sayfanın
            // kendisi de bunu söylüyor.
            'operatingSystem' => 'Web browser',
            'description' => $answer?->entries[0]->text ?? $content->metadata->metaDescription,
            'featureList' => $features,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function faqPage(PageContent $content, string $canonicalUrl): ?array
    {
        $faq = $content->block(BlockType::Faq);

        if ($faq === null) {
            // `FAQPage` YALNIZ görünür soru-cevap varsa üretilir (yönerge §14).
            // Görünmeyen bir SSS işaretlemek, sayfada olmayan içeriği ilan
            // etmektir.
            return null;
        }

        $questions = [];

        foreach ($faq->entries as $entry) {
            if ($entry->term === null) {
                continue;
            }

            $questions[] = [
                '@type' => 'Question',
                'name' => $entry->term,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $entry->text,
                ],
            ];
        }

        if ($questions === []) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => $canonicalUrl.'#faq',
            'mainEntity' => $questions,
        ];
    }
}
