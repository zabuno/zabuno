<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CONTENT-STATUS-01 — FF-191, yönerge §6 ve §20.
 *
 * "İçeriği yazılan sayfa `content_draft` olsun" bir cümle olarak kaldığı
 * sürece elle uygulanır ve bir gün yanlış uygulanır. Komut onu ÖLÇÜLEBİLİR
 * yapar: durum, içerik kütüğünün gerçekten ne taşıdığından türer.
 *
 * En önemli davranışı yaptığı şey değil, YAPMADIĞI şeydir: bu komut hiçbir
 * sayfayı `content_draft`ın ötesine taşımaz. Kalite kapısı (içerik onayı,
 * tasarım, SEO, erişilebilirlik, QA) insan işidir ve bir betiğin atlayabildiği
 * bir kapı, kapı değildir.
 *
 * Bugünkü sonucu da bir ölçümdür: kütük tek dilli (Türkçe) ve Türkçe içerik
 * yuvası `docs/118` E4 gereği bilerek boş; dolayısıyla komut bugün hiçbir
 * kaydı ilerletmez. Sahip dil kararını verdiği gün aynı komut çalışır.
 */
final class SyncContentStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $pageKey, string $locale, PagePublicationStatus $status): ContentPage
    {
        return ContentPage::query()->create([
            'page_key' => $pageKey,
            'locale' => $locale,
            'canonical_path' => '/'.$locale.'/x/'.$pageKey.'/',
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'Title',
            'priority' => 'P0',
            'publication_status' => $status->value,
        ]);
    }

    public function test_a_planned_page_with_written_content_reaches_content_draft(): void
    {
        $page = $this->page('urun.qr-menu', 'en', PagePublicationStatus::Planned);

        $this->artisan('site:sync-content-status')->assertSuccessful();

        self::assertSame(
            PagePublicationStatus::ContentDraft->value,
            $page->refresh()->publication_status,
        );
    }

    public function test_the_command_never_pushes_a_page_past_content_draft(): void
    {
        $page = $this->page('urun.analitik', 'en', PagePublicationStatus::ContentDraft);

        $this->artisan('site:sync-content-status')->assertSuccessful();

        self::assertSame(
            PagePublicationStatus::ContentDraft->value,
            $page->refresh()->publication_status,
        );
    }

    public function test_a_page_already_in_review_is_left_alone(): void
    {
        // Geri çekmek, bir insanın verdiği kararı bir betikle geri almaktır.
        $page = $this->page('urun.zabuno-ai', 'en', PagePublicationStatus::SeoReview);

        $this->artisan('site:sync-content-status')->assertSuccessful();

        self::assertSame(
            PagePublicationStatus::SeoReview->value,
            $page->refresh()->publication_status,
        );
    }

    public function test_a_page_whose_own_locale_has_no_content_is_not_advanced(): void
    {
        /*
            `docs/118` E4. Türkçe kayıt, Türkçe yuva boş olduğu için
            ilerlemez. İlerletseydik kütük "Türkçe taslak hazır" derdi ve bu
            yalan, kalite kapısının ilk maddesinin ta kendisini boşa çıkarırdı.
        */
        $page = $this->page('urun.qr-menu', 'tr', PagePublicationStatus::Planned);

        $this->artisan('site:sync-content-status')->assertSuccessful();

        self::assertSame(PagePublicationStatus::Planned->value, $page->refresh()->publication_status);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $page = $this->page('urun.menu-yonetimi', 'en', PagePublicationStatus::Planned);

        $this->artisan('site:sync-content-status', ['--dry-run' => true])->assertSuccessful();

        self::assertSame(PagePublicationStatus::Planned->value, $page->refresh()->publication_status);
    }

    public function test_a_published_page_is_never_walked_backwards(): void
    {
        $page = $this->page('urun.masa-ve-qr-yonetimi', 'en', PagePublicationStatus::Published);

        $this->artisan('site:sync-content-status')->assertSuccessful();

        self::assertSame(PagePublicationStatus::Published->value, $page->refresh()->publication_status);
    }
}
