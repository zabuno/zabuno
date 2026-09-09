<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use Tests\TestCase;

final class DesktopLayeredFooterTest extends TestCase
{
    private function footer(): \DOMXPath
    {
        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);

        return new \DOMXPath($dom);
    }

    public function test_desktop_has_five_open_navigation_columns_and_preserves_every_mobile_target(): void
    {
        $xpath = $this->footer();
        self::assertSame(1, $xpath->query('//footer')->length);
        self::assertSame(5, $xpath->query('//*[@data-desktop-footer]//*[@data-footer-column]')->length);
        self::assertSame(0, $xpath->query('//*[@data-desktop-footer]//details')->length);
        $desktop = [];
        foreach ($xpath->query('//*[@data-desktop-footer]//a') as $link) {
            $desktop[] = $link->getAttribute('href');
            self::assertNotSame('-1', $link->getAttribute('tabindex'));
        }
        foreach ($xpath->query('//*[@data-mobile-footer]//a') as $link) {
            self::assertContains($link->getAttribute('href'), $desktop);
        }
        self::assertContains('/register', $desktop);
        self::assertContains('/app', $desktop);
        self::assertContains('#main-content', $desktop);
    }

    public function test_mobile_keeps_native_closed_legal_and_identity_disclosures(): void
    {
        /* C3: künye satırı `/information-society-services` sayfasına taşındı;
           katlanabilir kalan tek satır yasal satırdır. */
        $xpath = $this->footer();
        foreach (['site-footer-legal'] as $class) {
            $nodes = $xpath->query('//*[@data-mobile-footer]//*[contains(concat(" ", @class, " "), " '.$class.' ")]//details');
            self::assertGreaterThan(0, $nodes->length);
            foreach ($nodes as $details) {
                self::assertFalse($details->hasAttribute('open'));
                self::assertSame(1, $xpath->query('./summary', $details)->length);
            }
        }
    }

    public function test_the_desktop_footer_leads_to_the_seller_identity_page_instead_of_repeating_it(): void
    {
        /*
            C3: künye iki altbilgi sunumundan da KALDIRILDI ve kendi
            sayfasına taşındı. Geniş sunum onu tekrar etmez, ona BAĞLANIR —
            bağlantı da elle yazılmadı, `SiteNavigation`ün şirket grubundan
            geliyor, dolayısıyla iki sunum ayrışamaz.
        */
        $xpath = $this->footer();

        self::assertSame(0, $xpath->query('//*[@data-desktop-footer]//*[@data-company-identity]')->length);
        self::assertSame(
            1,
            $xpath->query('//*[@data-desktop-footer]//a[@href="/information-society-services"]')->length
        );

        /* Eksik alanlar hâlâ görünür — yalnız artık kendi sayfasında. */
        $page = (string) $this->get('/information-society-services')->assertOk()->getContent();
        self::assertStringContainsString('data-missing="true"', $page);
        self::assertStringContainsString('data-legal-alert="seller-identity"', $page);
    }

    public function test_only_one_footer_presentation_is_displayed_and_no_native_details_are_forced_open(): void
    {
        $css = (string) file_get_contents(resource_path('css/site-shell.css'));
        self::assertMatchesRegularExpression('/\.site-desktop-footer\s*\{\s*display:\s*none;/s', $css);
        self::assertMatchesRegularExpression('/@media\s*\(min-width:\s*64rem\)\s*\{\s*\.site-mobile-footer\s*\{\s*display:\s*none;/s', $css);
        self::assertMatchesRegularExpression('/\.site-desktop-footer\s*\{\s*display:\s*block;/s', $css);
        self::assertStringNotContainsString('::details-content', $css);
    }
}
