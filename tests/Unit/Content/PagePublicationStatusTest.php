<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Domain\Content\PagePublicationStatus;
use PHPUnit\Framework\TestCase;

/**
 * PAGE-STATE-01 — FF-117, yönerge §6.
 *
 * Durum makinesi bir liste değil bir SÖZLEŞMEDİR: bir sayfa taslaktan doğrudan
 * yayına atlayamaz. Atlayabilseydi kalite kapısı (içerik, tasarım, SEO,
 * erişilebilirlik) bir tavsiye olurdu.
 */
final class PagePublicationStatusTest extends TestCase
{
    public function test_the_happy_path_goes_through_every_gate(): void
    {
        $path = [
            PagePublicationStatus::Planned,
            PagePublicationStatus::Scaffolded,
            PagePublicationStatus::ContentDraft,
            PagePublicationStatus::ContentReview,
            PagePublicationStatus::DesignReview,
            PagePublicationStatus::SeoReview,
            PagePublicationStatus::Qa,
            PagePublicationStatus::Approved,
            PagePublicationStatus::Published,
        ];

        for ($index = 0; $index < count($path) - 1; $index++) {
            self::assertTrue(
                $path[$index]->canMoveTo($path[$index + 1]),
                "PAGE-STATE-01: {$path[$index]->value} → {$path[$index + 1]->value} geçişi kapalı.",
            );
        }
    }

    public function test_a_draft_cannot_jump_to_published(): void
    {
        self::assertFalse(PagePublicationStatus::ContentDraft->canMoveTo(PagePublicationStatus::Published));
        self::assertFalse(PagePublicationStatus::Planned->canMoveTo(PagePublicationStatus::Published));
    }

    public function test_a_failed_review_can_go_back(): void
    {
        // Kalite kapısı tek yönlü değildir: QA başarısızsa sayfa geri döner.
        self::assertTrue(PagePublicationStatus::Qa->canMoveTo(PagePublicationStatus::ContentDraft));
        self::assertTrue(PagePublicationStatus::SeoReview->canMoveTo(PagePublicationStatus::ContentDraft));
    }

    public function test_a_published_page_can_go_into_maintenance_and_come_back(): void
    {
        self::assertTrue(PagePublicationStatus::Published->canMoveTo(PagePublicationStatus::Maintenance));
        self::assertTrue(PagePublicationStatus::Maintenance->canMoveTo(PagePublicationStatus::Published));
        self::assertTrue(PagePublicationStatus::Published->canMoveTo(PagePublicationStatus::Retired));
    }

    public function test_a_retired_page_is_a_dead_end(): void
    {
        foreach (PagePublicationStatus::cases() as $target) {
            self::assertFalse(
                PagePublicationStatus::Retired->canMoveTo($target),
                'PAGE-STATE-01: emekli sayfa geri döndürülemez — geri gelmesi gereken bir sayfa emekli edilmemeliydi.',
            );
        }
    }

    public function test_every_status_has_a_sentence_a_visitor_can_read(): void
    {
        // Ziyaretçiye `content_draft` yazılmaz. Teknik durum adı bir
        // ziyaretçiye hiçbir şey anlatmaz ve ürünü içeriden konuşur gösterir.
        foreach (PagePublicationStatus::cases() as $status) {
            self::assertNotSame('', $status->translationKey());
            self::assertStringStartsWith('site.pageState.', $status->translationKey());
        }
    }
}
