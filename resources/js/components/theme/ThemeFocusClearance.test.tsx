import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ThemeRoot } from './ThemeRoot';

/**
 * ZABUNO_THEME_FOCUS_CLEARANCE_RED
 *
 * Authenticated local measurement (806x914 CSS px, Chromium/macOS) found a
 * focusable "Add product" control (rect top 836.943 / bottom 872.939)
 * overlapping the fixed Theme radiogroup (rect top 848.525 / bottom
 * 901.758) by 24.414px. An independent read-only WCAG auditor classified
 * this as requiring a bounded correction under WCAG 2.2 AA 2.4.11 (Focus
 * Not Obscured) and 1.4.10 (Reflow).
 *
 * Contract under test: ThemeRoot must own a global, non-interactive bottom
 * clearance region that reserves at least the fixed theme control's height
 * (44px, matching the radiogroup's own min-h-11 buttons) plus the same
 * safe-area-inset-bottom offset the radiogroup uses, so any scrollable
 * child content (AppShell/Workspace/auth) never places a focusable control
 * under the fixed selector. The clearance must be inert to focus and
 * assistive tech (aria-hidden, unfocusable) and must sit in document flow
 * immediately before/adjacent to the fixed theme radiogroup, regardless of
 * which children ThemeRoot renders.
 *
 * ThemeRoot does not render this clearance element yet — the query below
 * is expected to fail RED (returns null) until the clearance is added.
 */

function resetDocumentThemeState() {
    document.documentElement.classList.remove('dark');
    document.documentElement.removeAttribute('data-theme');
    window.localStorage.clear();
}

describe('ThemeRoot — global bottom focus clearance for the fixed theme control', () => {
    beforeEach(() => {
        resetDocumentThemeState();
    });

    afterEach(() => {
        resetDocumentThemeState();
    });

    // Bu test eskiden SABİT bir alt çubuğun altında yer ayrılmasını
    // donduruyordu. Sonra çubuk sabit olmaktan çıktı; şimdi çubuk da yok.
    //
    // 320×480'de (iPhone 4) sabit bir alt çubuk ekranın kalıcı olarak
    // %12'sini kaplıyor ve içeriğin üstüne biniyor. Küçük ekranda en pahalı
    // şey dikey alandır; bir tema seçici onu kalıcı olarak satın alamaz
    // (UX raporu §8.1 ve Definition of Done: "Theme selector içerik üzerine
    // binmiyor").
    //
    // Kontrol artık hesap menüsünde (`docs/63`). `ThemeRoot` yalnız tercihi
    // TUTAR ve belgeye uygular; sayfaya hiçbir şey çizmez.
    it('draws no control of its own, so there is nothing to reserve space for', () => {
        render(
            <ThemeRoot>
                <main>
                    <button type="button">Add product</button>
                </main>
            </ThemeRoot>,
        );

        // Ne yüzen bir çubuk, ne akış içinde bir seçici.
        expect(screen.queryByRole('radiogroup', { name: /theme/i })).toBeNull();
        expect(document.querySelector('[data-theme-focus-clearance]')).toBeNull();

        // Ama tercih UYGULANMAYA devam eder: kabuk temasız kalmaz.
        expect(document.documentElement.getAttribute('data-theme')).not.toBeNull();

        // Ve sarmalanan içerik olduğu gibi çizilir.
        expect(screen.getByRole('button', { name: 'Add product' })).toBeInTheDocument();
    });
});
