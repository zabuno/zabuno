<?php

declare(strict_types=1);

namespace Tests\Feature\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ZABUNO_THEME_FOUNDATION_RED
 *
 * Freezes the smallest contract for the pre-paint theme bootstrap: every
 * live HTML surface (the public root shell, the workspace app shell, and
 * the four Fortify auth views) must emit an inline bootstrap <script>
 * before any @vite output (the CSS <link> and JS <script type="module">
 * tags), so a stored or OS-derived theme is applied to <html> before Vite's
 * CSS or React ever paints — no flash of the wrong theme.
 *
 * None of these six views currently emit any such script, so every
 * assertion below is expected to fail RED until S1-WP01B implements the
 * bootstrap partial and includes it in each view.
 */
final class ThemeBootstrapViewTest extends TestCase
{
    private const TEST_APP_KEY = 'base64:KioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKio=';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => self::TEST_APP_KEY]);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function liveViewProvider(): array
    {
        return [
            // Herkese açık kabuk artık sunucuda üretilir; React montaj
            // görünümü (`app`) kaldırıldı (bkz. HOME-NO-REACT-05).
            'public home' => ['public.home', ['coreModuleCount' => 7, 'canonicalUrl' => 'https://zabuno.test/', 'anchorPrefix' => '']],
            'public legal' => ['public.legal', ['coreModuleCount' => 7, 'canonicalUrl' => 'https://zabuno.test/terms', 'anchorPrefix' => '/', 'title' => 'Terms']],
            'workspace app shell' => ['workspace-app', []],
            'auth: login' => ['auth.login', []],
            'auth: register' => ['auth.register', []],
            'auth: verify (verification pending)' => ['auth.verify', ['email' => 'owner@example.test']],
            'auth: verified' => ['auth.verified', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('liveViewProvider')]
    public function test_view_emits_a_pre_paint_theme_bootstrap_script_before_any_vite_output(string $view, array $data): void
    {
        $html = view($view, $data)->render();

        $bootstrapPosition = strpos($html, 'data-zabuno-theme-bootstrap');
        self::assertNotFalse(
            $bootstrapPosition,
            "View '{$view}' does not emit an inline script marked data-zabuno-theme-bootstrap."
        );

        $firstStylesheetPosition = strpos($html, '<link');
        $firstModuleScriptPosition = strpos($html, '<script type="module"');

        if ($firstStylesheetPosition !== false) {
            self::assertLessThan(
                $firstStylesheetPosition,
                $bootstrapPosition,
                "View '{$view}': the theme bootstrap script must appear before the first Vite <link> stylesheet tag."
            );
        }

        if ($firstModuleScriptPosition !== false) {
            self::assertLessThan(
                $firstModuleScriptPosition,
                $bootstrapPosition,
                "View '{$view}': the theme bootstrap script must appear before the first Vite <script type=\"module\"> tag."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('liveViewProvider')]
    public function test_bootstrap_script_reads_the_stable_storage_key_and_falls_back_to_system_media_query(string $view, array $data): void
    {
        $html = view($view, $data)->render();

        $scriptStart = strpos($html, 'data-zabuno-theme-bootstrap');
        self::assertNotFalse($scriptStart, "View '{$view}' does not emit the theme bootstrap script; cannot inspect its body.");

        $scriptTagStart = strrpos(substr($html, 0, $scriptStart), '<script');
        $scriptTagEnd = strpos($html, '</script>', $scriptStart);
        self::assertNotFalse($scriptTagStart);
        self::assertNotFalse($scriptTagEnd);

        $scriptBody = substr($html, $scriptTagStart, $scriptTagEnd - $scriptTagStart);

        self::assertStringContainsString(
            'zabuno-theme',
            $scriptBody,
            "View '{$view}': bootstrap script must reference the stable 'zabuno-theme' storage key."
        );
        self::assertStringContainsString(
            'prefers-color-scheme',
            $scriptBody,
            "View '{$view}': bootstrap script must fall back to the OS prefers-color-scheme media query."
        );
        self::assertStringContainsString(
            'documentElement',
            $scriptBody,
            "View '{$view}': bootstrap script must set the theme on document.documentElement before paint."
        );
    }
}
