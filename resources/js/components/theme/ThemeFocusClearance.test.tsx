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

    it('reserves an inert, non-focusable bottom clearance sized to the theme control height + safe-area offset, in flow before the radiogroup, regardless of child route content', () => {
        render(
            <ThemeRoot>
                <main>
                    <button type="button">Add product</button>
                </main>
            </ThemeRoot>,
        );

        const radiogroup = screen.getByRole('radiogroup', { name: /theme/i });

        const clearance = document.querySelector('[data-theme-focus-clearance]');
        expect(clearance).not.toBeNull();

        const clearanceEl = clearance as HTMLElement;

        // Inert to focus and assistive tech — this is a layout reservation,
        // not a control.
        expect(clearanceEl.getAttribute('aria-hidden')).toBe('true');
        expect(clearanceEl).not.toHaveAttribute('tabindex');
        expect(clearanceEl.querySelectorAll('button, a, input, [tabindex]').length).toBe(0);

        // Reserves at least the fixed control's own height (44px, matching
        // the radiogroup buttons' min-h-11) plus the *same full* bottom
        // offset the radiogroup itself uses — bottom-[max(0.75rem,env(safe-area-inset-bottom))] —
        // not merely the raw env() term with its 0.75rem fallback dropped.
        const minHeight = clearanceEl.style.minHeight;
        expect(minHeight).toMatch(/44px/);
        expect(minHeight).toMatch(/max\(/);
        expect(minHeight).toMatch(/0\.75rem/);
        expect(minHeight).toMatch(/env\(safe-area-inset-bottom\)/);

        // Sits in document flow immediately adjacent to (before) the fixed
        // radiogroup, so it applies regardless of which child route is
        // mounted above it.
        const position = clearanceEl.compareDocumentPosition(radiogroup);
        expect(Boolean(position & Node.DOCUMENT_POSITION_FOLLOWING)).toBe(true);
        expect(clearanceEl.nextElementSibling).toBe(radiogroup);
    });
});
