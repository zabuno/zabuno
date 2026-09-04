<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Domain\Content\PageEnvironment;
use App\Domain\Content\PageGate;
use App\Domain\Content\PagePublicationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PAGE-GATE-01 — FF-117, yönerge §7.
 *
 * Tek bir karar noktası: bir sayfanın durumu, ortamı ve önizleme yetkisi
 * verildiğinde ne görünecek, hangi HTTP kodu dönecek, robots ne diyecek,
 * sitemap'e ve menüye girecek mi.
 *
 * En kritik karar: **yayınlanmamış yüzlerce URL'ye `200` ile aynı
 * "hazırlanıyor" metnini vermek yasaktır.** Bu soft-404 ve kopya/ince içerik
 * üretir; 414 sayfalık bir sitede alan adının kalitesini topluca düşürür.
 */
final class PageGateTest extends TestCase
{
    public function test_a_published_page_is_the_only_thing_that_gets_indexed(): void
    {
        $decision = PageGate::decide(PagePublicationStatus::Published, PageEnvironment::Production, false, true);

        self::assertSame('content', $decision->mode);
        self::assertSame(200, $decision->statusCode);
        self::assertSame('index,follow', $decision->robots);
        self::assertTrue($decision->includeInSitemap);
        self::assertTrue($decision->includeInNavigation);
    }

    /** @return list<array{0: PagePublicationStatus}> */
    public static function unpublishedStatuses(): array
    {
        return array_map(
            static fn (PagePublicationStatus $status): array => [$status],
            [
                PagePublicationStatus::Planned,
                PagePublicationStatus::Scaffolded,
                PagePublicationStatus::ContentDraft,
                PagePublicationStatus::ContentReview,
                PagePublicationStatus::DesignReview,
                PagePublicationStatus::SeoReview,
                PagePublicationStatus::Qa,
                PagePublicationStatus::Approved,
            ],
        );
    }

    #[DataProvider('unpublishedStatuses')]
    public function test_an_unpublished_page_is_a_real_404_not_a_soft_one(PagePublicationStatus $status): void
    {
        $decision = PageGate::decide($status, PageEnvironment::Production, false, true);

        self::assertSame('construction', $decision->mode);
        self::assertSame(
            404,
            $decision->statusCode,
            'PAGE-GATE-01: yayınlanmamış yüzlerce URL 200 dönerse soft-404 ve ince içerik üretilir.',
        );
        self::assertSame('noindex,follow', $decision->robots);
        self::assertFalse($decision->includeInSitemap);
        self::assertFalse($decision->includeInNavigation);
    }

    public function test_approved_is_not_published(): void
    {
        // "Onaylandı" bir yayın kararı DEĞİLDİR. Aradaki farkı silmek,
        // kalite kapısını atlamanın en kolay yolu olurdu.
        self::assertSame(
            404,
            PageGate::decide(PagePublicationStatus::Approved, PageEnvironment::Production, false, true)->statusCode,
        );
    }

    public function test_preview_shows_the_draft_without_letting_it_be_indexed(): void
    {
        $decision = PageGate::decide(PagePublicationStatus::ContentDraft, PageEnvironment::Production, true, true);

        self::assertSame('preview', $decision->mode);
        self::assertSame(200, $decision->statusCode);
        self::assertSame('noindex,nofollow', $decision->robots);
        self::assertFalse($decision->includeInSitemap);
    }

    public function test_maintenance_is_only_for_a_page_that_really_worked_before(): void
    {
        /*
            503 "bu sayfa vardı, kısa süreliğine yok" demektir. Hiç
            yayınlanmamış bir sayfada kullanmak, arama motoruna var olmayan
            bir şeyin geri geleceğini söylemektir.
        */
        $wasPublished = PageGate::decide(PagePublicationStatus::Maintenance, PageEnvironment::Production, false, true);

        self::assertSame('maintenance', $wasPublished->mode);
        self::assertSame(503, $wasPublished->statusCode);

        $neverPublished = PageGate::decide(PagePublicationStatus::Maintenance, PageEnvironment::Production, false, false);

        self::assertSame(404, $neverPublished->statusCode);
        self::assertSame('construction', $neverPublished->mode);
    }

    public function test_a_retired_page_is_gone_not_hidden(): void
    {
        $decision = PageGate::decide(PagePublicationStatus::Retired, PageEnvironment::Production, false, true);

        self::assertSame('not-found', $decision->mode);
        self::assertSame(404, $decision->statusCode);
        self::assertFalse($decision->includeInNavigation);
    }

    public function test_staging_shows_everything_and_indexes_nothing(): void
    {
        $decision = PageGate::decide(PagePublicationStatus::ContentDraft, PageEnvironment::Staging, false, false);

        self::assertSame(200, $decision->statusCode);
        self::assertSame('noindex,nofollow', $decision->robots);
        self::assertFalse($decision->includeInSitemap);
        // Staging'de menü çalışır: ekip sayfaları gezerek kontrol eder.
        self::assertTrue($decision->includeInNavigation);
    }

    public function test_an_unpublished_page_never_receives_an_internal_link(): void
    {
        /*
            YÖNERGENİN KENDİ İÇİNDEKİ ÇELİŞKİ (`docs/105` §2.2, madde 3).

            Plan hem "yayınlanmamış sayfa 404 döner" hem de CI'da "broken link
            scan" istiyor. Menüde ya da içerik içinde duran her yayınlanmamış
            bağlantı, kendi CI'ını kırar. Kural tek bir yerde yaşamalı:
            bağlantı verilebilirlik, yayınlanmışlıkla aynı şeydir.
        */
        foreach (self::unpublishedStatuses() as [$status]) {
            self::assertFalse(
                PageGate::decide($status, PageEnvironment::Production, false, false)->isLinkable(),
                "PAGE-GATE-01: '{$status->value}' durumundaki sayfaya iç bağlantı verilemez.",
            );
        }

        self::assertTrue(
            PageGate::decide(PagePublicationStatus::Published, PageEnvironment::Production, false, true)->isLinkable(),
        );
    }
}
