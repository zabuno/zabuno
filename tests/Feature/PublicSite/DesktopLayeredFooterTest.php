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
        $xpath = $this->footer();
        foreach (['site-footer-legal', 'site-footer-identity'] as $class) {
            $nodes = $xpath->query('//*[@data-mobile-footer]//*[contains(concat(" ", @class, " "), " '.$class.' ")]//details');
            self::assertGreaterThan(0, $nodes->length);
            foreach ($nodes as $details) {
                self::assertFalse($details->hasAttribute('open'));
                self::assertSame(1, $xpath->query('./summary', $details)->length);
            }
        }
    }

    public function test_missing_seller_information_is_visible_in_the_desktop_footer(): void
    {
        $xpath = $this->footer();
        self::assertSame(1, $xpath->query('//*[@data-desktop-footer]//*[@data-company-identity]')->length);
        self::assertGreaterThan(0, $xpath->query('//*[@data-desktop-footer]//*[@data-missing="true"]')->length);
        self::assertSame(1, $xpath->query('//*[@data-desktop-footer]//*[@data-footer-identity-warning]')->length);
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
